<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Cancelled;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/** Le workflow des tickets (addendum §3) : états, parties, main, droits, chronologie. */
class TicketWorkflowTest extends TmaTestCase
{
    public function test_a_ticket_starts_as_a_draft_with_a_readable_number_of_its_source_application(): void
    {
        $this->asClient();
        $user = $this->agent();

        $first = $this->draft($user);
        $second = $this->draft($user);

        $this->assertInstanceOf(Draft::class, $first->state);
        $this->assertSame('DES-0001', $first->number);
        $this->assertSame('DES-0002', $second->number);
        $this->assertSame(self::CLIENT_UID, $first->source_instance_uid);
        $this->assertSame(26, strlen($first->ulid));
        $this->assertTrue($first->requester->isUser($user));
    }

    public function test_html_is_cleaned_on_the_server(): void
    {
        $this->asClient();

        $ticket = $this->draft($this->agent(), [
            'description' => '<p onclick="alert(1)">Bonjour <a href="javascript:alert(1)">ici</a> <a href="https://exemple.fr">là</a></p><script>alert(1)</script><iframe src="https://x"></iframe><svg onload="alert(1)"></svg>',
        ]);

        $this->assertStringNotContainsString('onclick', $ticket->description);
        $this->assertStringNotContainsString('javascript:', $ticket->description);
        $this->assertStringNotContainsString('<script', $ticket->description);
        $this->assertStringNotContainsString('<iframe', $ticket->description);
        $this->assertStringNotContainsString('<svg', $ticket->description);
        $this->assertStringContainsString('href="https://exemple.fr"', $ticket->description);
    }

    public function test_the_full_local_cycle_with_question_answer_and_reopening(): void
    {
        $this->asClient();
        Event::fake([TicketTransitioned::class]);
        $requester = $this->agent();
        $handler = $this->agent();
        $ticket = $this->draft($requester);

        $this->workflow()->run($ticket, 'submit', ['assignee_user_id' => $handler->getKey()], Context::ui($requester));
        $this->assertInstanceOf(Submitted::class, $ticket->state);
        $this->assertTrue($ticket->assignee->isUser($handler));

        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($handler));
        $this->assertInstanceOf(InProgress::class, $ticket->state);

        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Sur quel navigateur ?</p>'], Context::ui($handler));
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->state);
        $this->assertSame(Side::Requester, $ticket->sideInCharge());

        // Un commentaire pendant l'attente ne rend pas la main (CDC §7.5, §9.4).
        app(Timeline::class)->add($ticket, EntryType::Comment, '<p>Je regarde.</p>', Context::ui($requester));
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->refresh()->state);

        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Firefox.</p>'], Context::ui($requester));
        $this->assertInstanceOf(InProgress::class, $ticket->state);

        $question = Entry::query()->where('meta->question', true)->sole();
        $this->assertSame('<p>Firefox.</p>', $question->replies()->sole()->body);

        $this->workflow()->run($ticket, 'submit-for-validation', ['message' => '<p>Corrigé.</p>'], Context::ui($handler));
        $this->workflow()->run($ticket, 'reopen', ['message' => '<p>Toujours cassé sur mobile.</p>'], Context::ui($requester));
        $this->assertInstanceOf(InProgress::class, $ticket->state);

        $this->workflow()->run($ticket, 'submit-for-validation', [], Context::ui($handler));
        $this->assertInstanceOf(PendingValidation::class, $ticket->state);
        $this->workflow()->run($ticket, 'validate', [], Context::ui($requester));
        $this->assertInstanceOf(Closed::class, $ticket->state);
        $this->assertNotNull($ticket->closed_at);

        $this->assertSame(
            ['submit', 'take-charge', 'request-answer', 'answer', 'submit-for-validation', 'reopen', 'submit-for-validation', 'validate'],
            $ticket->transitions()->orderBy('id')->pluck('move')->all(),
        );
        $last = $ticket->transitions()->latest('id')->first();
        $this->assertSame(['pending_validation', 'closed'], [$last->from_state, $last->to_state]);
        $this->assertSame(self::CLIENT_UID, $last->origin_instance_uid);
        Event::assertDispatchedTimes(TicketTransitioned::class, 8);
    }

    public function test_a_move_needs_its_permission(): void
    {
        $this->asClient();
        $requester = $this->agent();
        $ticket = $this->draft($requester);
        $this->workflow()->run($ticket, 'submit', [], Context::ui($requester));

        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create();
        $this->assertFalse($this->workflow()->allows($ticket, 'take-charge', Context::ui($viewer)));
        $this->expectException(AuthorizationException::class);

        try {
            $this->workflow()->run($ticket, 'take-charge', [], Context::ui($viewer));
        } finally {
            $handler = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.view', 'tma.ticket.workflow.take-charge')->create();
            $this->assertTrue($this->workflow()->allows($ticket, 'take-charge', Context::ui($handler)));
        }
    }

    public function test_the_master_acts_as_handler_only_on_service_desk_tickets(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('submitted');

        $this->assertSame([Side::Handler], $ticket->sidesHere());
        $this->assertTrue($this->workflow()->allows($ticket, 'take-charge', Context::ui($agent)));
        // Le demandeur est dans l'application cliente : le maître ne répond ni ne valide à sa place.
        $this->assertFalse($this->workflow()->allows($ticket->forceFill(['state' => 'awaiting_requester']), 'answer', Context::ui($agent)));
        $this->assertFalse($this->workflow()->allows($ticket->forceFill(['state' => 'pending_validation']), 'validate', Context::ui($agent)));

        // Un ticket resté en traitement local chez le client ne regarde pas le maître.
        $local = $this->serviceDeskTicket('submitted', 'DES-0002')->forceFill(['handling_mode' => 'local']);
        $local->save();
        $this->assertSame([], $local->sidesHere());
        $this->assertSame([], $this->workflow()->available($local, Context::ui($agent)));
    }

    public function test_fields_are_editable_only_by_the_party_that_has_the_hand(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket('in_progress');
        $this->assertTrue($ticket->fieldsEditableHere());

        $ticket->forceFill(['state' => 'awaiting_requester']);
        $this->assertFalse($ticket->fieldsEditableHere());

        $this->asClient();
        // Côté client, une fois transmis, on ne fait plus qu'ajouter.
        $this->assertFalse($ticket->fieldsEditableHere());
        $this->assertFalse($ticket->forceFill(['state' => 'in_progress'])->fieldsEditableHere());
    }

    public function test_the_requester_cancels_only_before_it_is_taken_in_charge_and_the_handler_gives_a_reason(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('in_progress');

        try {
            $this->workflow()->run($ticket, 'cancel', [], Context::ui($agent));
            $this->fail('Le motif est obligatoire.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $this->workflow()->run($ticket, 'cancel', ['reason' => 'Doublon de DES-0003'], Context::ui($agent));
        $this->assertInstanceOf(Cancelled::class, $ticket->state);
        $this->assertSame('Doublon de DES-0003', $ticket->transitions()->sole()->reason);

        $this->asClient();
        $requester = $this->agent();
        $mine = $this->draft($requester);
        $this->workflow()->run($mine, 'submit', [], Context::ui($requester));
        $this->workflow()->run($mine, 'take-charge', [], Context::ui($requester));
        $this->assertSame(Side::Handler, $this->workflow()->sideFor($mine, $this->workflow()->move('cancel')));
        $mine->forceFill(['handling_mode' => 'service_desk']);
        $this->assertNull($this->workflow()->sideFor($mine, $this->workflow()->move('cancel')));
    }

    public function test_a_pause_needs_a_future_date_and_resumes_by_itself(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('in_progress');

        try {
            $this->workflow()->run($ticket, 'pause', ['until' => now()->subDay()->toDateTimeString()], Context::ui($agent));
            $this->fail('Une date passée est refusée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('until', $e->errors());
        }

        $this->workflow()->run($ticket, 'pause', ['until' => now()->addDay()->toDateTimeString()], Context::ui($agent));
        $this->assertInstanceOf(Paused::class, $ticket->state);

        // Pas encore l'heure ; et la reprise automatique n'est pas un bouton.
        $this->assertFalse($this->workflow()->allows($ticket, 'auto-resume', Context::scheduler()));
        $this->travel(2)->days();
        $this->assertFalse($this->workflow()->allows($ticket, 'auto-resume', Context::ui($agent)));

        $this->workflow()->run($ticket, 'auto-resume', [], Context::scheduler());
        $this->assertInstanceOf(InProgress::class, $ticket->state);
        $this->assertNull($ticket->paused_until);
    }

    public function test_notes_stay_in_their_application_until_shared(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $timeline = app(Timeline::class);

        $note = $timeline->add($ticket, EntryType::Note, '<p>Le client a déjà eu ce bug en mars.</p>', Context::ui($agent));
        $this->assertCount(1, $ticket->timelineEntries(withNotes: true));
        $this->assertCount(0, $ticket->timelineEntries(withNotes: false));

        // Vue du client : la note du maître n'existe pas tant qu'elle n'est pas partagée.
        $this->asClient();
        $this->assertCount(0, $ticket->timelineEntries(withNotes: true));
        $this->asMaster();

        $timeline->share($note, Context::ui($agent));
        $this->asClient();
        $this->assertCount(1, $ticket->timelineEntries(withNotes: false));
        $this->asMaster();

        // On répond dans le fil d'une note partagée : la réponse l'est aussi.
        $reply = $timeline->add($ticket, EntryType::Comment, '<p>Oui, même cause.</p>', Context::ui($agent), $note);
        $this->assertSame(EntryType::Note, $reply->type);
        $this->assertNotNull($reply->shared_at);
    }

    public function test_notes_need_their_permission_and_comments_stop_with_the_ticket(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $commenter = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.view', 'tma.ticket.comment')->create();
        $timeline = app(Timeline::class);

        $this->assertTrue($timeline->canAdd($ticket, EntryType::Comment, Context::ui($commenter)));
        $this->assertFalse($timeline->canAdd($ticket, EntryType::Note, Context::ui($commenter)));
        $this->assertFalse($timeline->canReadNotes($ticket, Context::ui($commenter)));

        $ticket->forceFill(['state' => 'closed'])->save();
        $this->expectException(AuthorizationException::class);
        $timeline->add($ticket, EntryType::Comment, '<p>Encore une chose.</p>', Context::ui($commenter));
    }

    public function test_an_empty_message_is_refused(): void
    {
        $this->asMaster();
        $this->expectException(ValidationException::class);

        app(Timeline::class)->add($this->serviceDeskTicket(), EntryType::Comment, '<p> </p><script>x</script>', Context::ui($this->agent()));
    }

    public function test_auto_close_is_for_the_scheduler_only(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket('awaiting_requester');

        $this->assertFalse($this->workflow()->allows($ticket, 'auto-close', Context::ui($this->agent())));
        $this->workflow()->run($ticket, 'auto-close', [], Context::scheduler());

        $this->assertInstanceOf(Closed::class, $ticket->state);
        $transition = $ticket->transitions()->sole();
        $this->assertNull($transition->actor_id);
        $this->assertSame('scheduler', $transition->origin->value);
        $this->assertStringContainsString('28', $transition->reason);
    }
}
