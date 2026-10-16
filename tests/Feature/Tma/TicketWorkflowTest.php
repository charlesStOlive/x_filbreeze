<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Cancelled;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Support\TimelineItems;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Le workflow des tickets (addendum §3) : un seul circuit, du client au Support ; le tour, qu'aucun droit ne
 * contourne ; les forçages, chacun avec son droit ; la prise de main ; les messages comme tours de parole.
 */
class TicketWorkflowTest extends TmaTestCase
{
    public function test_a_request_starts_as_a_draft_of_a_correspondent_with_a_readable_number(): void
    {
        $this->asClient();
        $user = $this->correspondent();

        $first = $this->draft($user);
        $second = $this->draft($user);

        $this->assertInstanceOf(Draft::class, $first->state);
        $this->assertSame('DES-0001', $first->number);
        $this->assertSame('DES-0002', $second->number);
        $this->assertSame(self::CLIENT_UID, $first->source_instance_uid);
        $this->assertSame(26, strlen($first->ulid));
        $this->assertTrue($first->requester->isUser($user));
        $this->assertFalse($first->isShared());

        // Sans le droit de correspondant du Support, pas de demande, même avec celui de créer.
        $plain = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.create')->create();

        try {
            $this->draft($plain);
            $this->fail('Seul un correspondant du Support fait une demande.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        // Le Support, lui, prépare un ticket pour un client (`createForClient()`).
        $this->asMaster();
        $this->expectException(AuthorizationException::class);
        $this->draft($this->agent());
    }

    public function test_html_is_cleaned_on_the_server(): void
    {
        $this->asClient();

        $ticket = $this->draft($this->correspondent(), [
            'description' => '<p onclick="alert(1)">Bonjour <a href="javascript:alert(1)">ici</a> <a href="https://exemple.fr">là</a></p><script>alert(1)</script><iframe src="https://x"></iframe><svg onload="alert(1)"></svg>',
        ]);

        $this->assertStringNotContainsString('onclick', $ticket->description);
        $this->assertStringNotContainsString('javascript:', $ticket->description);
        $this->assertStringNotContainsString('<script', $ticket->description);
        $this->assertStringNotContainsString('<iframe', $ticket->description);
        $this->assertStringNotContainsString('<svg', $ticket->description);
        $this->assertStringContainsString('href="https://exemple.fr"', $ticket->description);
    }

    public function test_the_full_cycle_in_the_master_follows_the_turn(): void
    {
        $this->asMaster();
        Event::fake([TicketTransitioned::class]);
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');

        $this->assertSame([Side::Handler], $ticket->sidesHere());
        // Pas de « prise en charge » : le Support répond tout de suite ; sans contact, il le devient.
        $this->assertArrayNotHasKey('take-charge', $this->workflow()->moves());

        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Sur quel navigateur ?</p>'], Context::ui($support));
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->state);
        $this->assertTrue($ticket->assignee->isUser($support));
        $this->assertNotNull($ticket->taken_at);
        $this->assertSame(Side::Requester, $ticket->sideInCharge());

        // Le demandeur répond depuis son application : le maître fait le passage.
        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Firefox.</p>'], $this->fromClient());
        $this->assertInstanceOf(InProgress::class, $ticket->state);
        $this->assertSame('<p>Firefox.</p>', Entry::query()->where('meta->question', true)->sole()->replies()->sole()->body);

        $this->workflow()->run($ticket, 'submit-for-validation', ['message' => '<p>Corrigé.</p>'], Context::ui($support));
        $this->workflow()->run($ticket, 'reopen', ['message' => '<p>Toujours cassé sur mobile.</p>'], $this->fromClient());
        $this->assertInstanceOf(InProgress::class, $ticket->state);

        $this->workflow()->run($ticket, 'submit-for-validation', [], Context::ui($support));
        $this->assertInstanceOf(PendingValidation::class, $ticket->state);
        $this->workflow()->run($ticket, 'validate', ['message' => '<p>Parfait, merci.</p>'], $this->fromClient());
        $this->assertInstanceOf(Closed::class, $ticket->state);
        $this->assertNotNull($ticket->closed_at);
        $this->assertSame('<p>Parfait, merci.</p>', $ticket->entries()->latest('id')->first()->body);

        $this->assertSame(
            ['request-answer', 'answer', 'submit-for-validation', 'reopen', 'submit-for-validation', 'validate'],
            $ticket->transitions()->orderBy('id')->pluck('move')->all(),
        );
        $last = $ticket->transitions()->latest('id')->first();
        $this->assertSame(['pending_validation', 'closed'], [$last->from_state, $last->to_state]);
        $this->assertSame(self::CLIENT_UID, $last->origin_instance_uid);
        Event::assertDispatchedTimes(TicketTransitioned::class, 6);
    }

    public function test_nobody_acts_out_of_turn_whatever_their_rights(): void
    {
        $this->asMaster();
        $everything = $this->agent();
        $support = $this->supportMember();
        $benoit = $this->clientActor('8', 'Benoît Desjonquères');
        $ticket = $this->serviceDeskTicket('awaiting_requester');
        $available = fn (Context $context): array => array_keys($this->workflow()->available($ticket, $context));

        // Le tour du demandeur : le Support ne répond pas à sa place, même avec tous les droits ; restent les forçages.
        $this->assertSame([], $available(Context::ui($support)));
        $this->assertEqualsCanonicalizing(['force-return', 'force-close'], $available(Context::ui($everything)));
        // Le demandeur répond ; une autre personne de son application, non.
        $this->assertTrue($this->workflow()->allows($ticket, 'answer', $this->fromClient()));
        $this->assertFalse($this->workflow()->allows($ticket, 'answer', $this->fromClient($benoit)));

        // Le tour du Support : le demandeur ne reprend pas la main.
        $ticket->forceFill(['state' => 'in_progress'])->save();
        $this->assertSame([], array_values(array_diff($available($this->fromClient()), ['force-close'])));
        $this->assertEqualsCanonicalizing(['request-answer', 'submit-for-validation', 'pause'], $available(Context::ui($support)));

        // Sans le droit de membre du Support, rien.
        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create();
        $this->assertSame([], $available(Context::ui($viewer)));

        // Dans l'application cliente, tous les droits ne donnent que le forçage : le tour est au Support.
        $this->asClient();
        $this->assertSame(['force-close'], $available(Context::ui($this->agent())));
        $this->assertSame([], $available(Context::ui($this->correspondent())));

        $this->asMaster();
        $this->expectException(AuthorizationException::class);
        $this->workflow()->run($ticket->forceFill(['state' => 'awaiting_requester']), 'answer', ['answer' => '<p>Je réponds à sa place.</p>'], Context::ui($everything));
    }

    public function test_the_requester_withdraws_only_while_the_request_is_new(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket('submitted');

        try {
            $this->workflow()->run($ticket, 'cancel', [], $this->fromClient());
            $this->fail('Le motif est obligatoire.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        // Le Support ne retire pas une demande : il la refuse.
        $this->assertFalse($this->workflow()->allows($ticket, 'cancel', Context::ui($this->agent())));
        $this->assertTrue($this->workflow()->allows($ticket, 'refuse', Context::ui($this->supportMember())));

        $this->workflow()->run($ticket, 'cancel', ['reason' => 'Doublon de DES-0003'], $this->fromClient());
        $this->assertInstanceOf(Cancelled::class, $ticket->state);
        $this->assertSame('Doublon de DES-0003', $ticket->transitions()->sole()->reason);

        $taken = $this->serviceDeskTicket('in_progress', 'DES-0002');
        $this->assertFalse($this->workflow()->allows($taken, 'cancel', $this->fromClient()));
    }

    public function test_forcing_needs_its_own_right(): void
    {
        $this->asMaster();
        $support = $this->supportMember();
        $forcer = $this->supportMember('tma.ticket.force-close', 'tma.ticket.force-return');

        foreach (['submitted', 'in_progress', 'paused', 'awaiting_requester', 'pending_validation'] as $i => $state) {
            $ticket = $this->serviceDeskTicket($state, 'DES-001'.$i);

            $this->assertFalse($this->workflow()->allows($ticket, 'force-close', Context::ui($support)), $state);
            $this->assertTrue($this->workflow()->allows($ticket, 'force-close', Context::ui($forcer)), $state);
            $this->assertSame(in_array($state, ['awaiting_requester', 'pending_validation'], true), $this->workflow()->allows($ticket, 'force-return', Context::ui($forcer)), $state);
            $this->assertFalse($this->workflow()->allows($ticket, 'force-return', Context::ui($support)), $state);
            // « Reprendre » ne part plus que d'une pause.
            $this->assertSame($state === 'paused', $this->workflow()->allows($ticket, 'resume', Context::ui($support)), $state);
        }

        $sleeping = $this->serviceDeskTicket('awaiting_requester', 'DES-0020');

        try {
            $this->workflow()->run($sleeping, 'force-close', [], Context::ui($forcer));
            $this->fail('Le motif est obligatoire.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $this->workflow()->run($sleeping, 'force-close', ['reason' => 'Sans nouvelles depuis deux mois.'], Context::ui($forcer));
        $this->assertInstanceOf(Closed::class, $sleeping->state);
        $this->assertNotNull($sleeping->closed_at);

        $waiting = $this->serviceDeskTicket('pending_validation', 'DES-0021');
        $this->workflow()->run($waiting, 'force-return', [], Context::ui($forcer));
        $this->assertInstanceOf(InProgress::class, $waiting->state);
    }

    public function test_a_principal_recipient_takes_over_and_becomes_the_requester(): void
    {
        $this->asMaster();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('awaiting_requester');
        [$ada, $benoit, $claire] = [$this->clientActor(), $this->clientActor('8', 'Benoît Desjonquères'), $this->clientActor('9', 'Claire Martin')];
        app(Recipients::class)->add($ticket, $ada);
        app(Recipients::class)->add($ticket, $benoit);
        app(Recipients::class)->add($ticket, $claire, RecipientRole::Secondary);

        $this->assertFalse($this->workflow()->allows($ticket, 'take-over', $this->fromClient($ada)), 'déjà le demandeur');
        $this->assertFalse($this->workflow()->allows($ticket, 'take-over', $this->fromClient($claire)), 'secondaire');
        $this->assertFalse($this->workflow()->allows($ticket, 'take-over', Context::ui($this->agent())), 'jamais le Support');

        $transition = $this->workflow()->run($ticket, 'take-over', [], $this->fromClient($benoit));

        // L'état ne change pas ; c'est désormais Benoît qu'on attend.
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->state);
        $this->assertSame(['awaiting_requester', 'awaiting_requester'], [$transition->from_state, $transition->to_state]);
        $this->assertTrue($ticket->requester->is($benoit));
        $this->assertFalse($this->workflow()->allows($ticket, 'answer', $this->fromClient($ada)));

        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Le Crédit Mutuel, depuis lundi.</p>'], $this->fromClient($benoit));
        $this->assertInstanceOf(InProgress::class, $ticket->state);
        $this->assertFalse($this->workflow()->allows($ticket, 'take-over', $this->fromClient($ada)), 'le tour est au Support');

        // Le Support lui répond : c'est encore lui qu'on attend ; l'ancien demandeur peut reprendre la main de même.
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Et les virements SEPA ?</p>'], Context::ui($support));
        $this->assertTrue($this->workflow()->allows($ticket, 'answer', $this->fromClient($benoit)));
        $this->assertTrue($this->workflow()->allows($ticket, 'take-over', $this->fromClient($ada)));
    }

    public function test_a_message_always_goes_with_a_move(): void
    {
        $this->asMaster();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('in_progress');
        $timeline = app(Timeline::class);

        // Pas de message à part, même à son tour : le Support pose une question, ou marque comme résolu ; pour un point
        // d'étape, une note.
        foreach (['submitted', 'in_progress', 'awaiting_requester'] as $state) {
            $ticket->forceFill(['state' => $state])->save();
            $this->assertFalse($timeline->canAdd($ticket, EntryType::Comment, Context::ui($support)), $state);
            $this->assertTrue($timeline->canAdd($ticket, EntryType::Note, Context::ui($support)), $state);
        }

        try {
            $timeline->add($ticket, EntryType::Comment, '<p>Je regarde.</p>', Context::ui($support));
            $this->fail('Un message va avec un passage.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('va toujours avec un passage', $e->getMessage());
        }

        // La relance reste, à qui a le tour.
        $timeline->add($ticket, EntryType::Comment, '<p>Avez-vous pu regarder ?</p>', Context::ui($support), meta: ['reminder' => true]);
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->refresh()->state);

        // Dans l'application cliente, de même : le demandeur parle en répondant ; à part, une note ou une relance.
        $this->asClient();
        $requester = $this->correspondent();
        $mine = $this->serviceDeskTicket('in_progress', 'DES-0002');
        $mine->forceFill(['requester_actor_id' => Actor::forUser($requester)->getKey()])->save();

        $this->assertFalse($timeline->canAdd($mine, EntryType::Comment, Context::ui($requester)));
        $this->assertTrue($timeline->canAdd($mine, EntryType::Note, Context::ui($requester)));
        $this->assertTrue($timeline->canAdd($mine, EntryType::Comment, Context::ui($requester), reminder: true));
    }

    public function test_only_the_last_message_can_be_changed_by_its_author(): void
    {
        $this->asMaster();
        $support = $this->supportMember();
        $colleague = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        $timeline = app(Timeline::class);

        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Sur quel navigateur ?</p>'], Context::ui($support));
        $question = $ticket->entries()->messages()->sole();
        $this->assertTrue($timeline->canEdit($question, Context::ui($support)));
        $this->assertFalse($timeline->canEdit($question, Context::ui($colleague)));

        $this->travel(1)->minute();
        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Firefox.</p>'], $this->fromClient());
        $answer = $ticket->entries()->where('meta->answer', true)->sole();

        // Un message suivi d'un autre ne se touche plus, même par son auteur.
        $this->assertFalse($timeline->canEdit($question->refresh(), Context::ui($support)));

        // Le dernier, si, par son auteur seul, que ce soit son tour ou non ; une note ne le fige pas.
        $timeline->add($ticket, EntryType::Note, '<p>À vérifier.</p>', Context::ui($colleague), $question);
        $timeline->edit($answer, '<p>Firefox 131.</p>', $this->fromClient());
        $this->assertSame('<p>Firefox 131.</p>', $answer->refresh()->body);
        $this->assertNotNull($answer->edited_at);

        // Un message du Support le fige.
        $this->travel(1)->minute();
        $this->workflow()->run($ticket->refresh(), 'request-answer', ['question' => '<p>Et sur mobile ?</p>'], Context::ui($support));
        $this->assertFalse($timeline->canEdit($answer->refresh(), $this->fromClient()));

        $this->expectException(AuthorizationException::class);
        $timeline->edit($answer, '<p>Trop tard.</p>', $this->fromClient());
    }

    public function test_the_demand_is_changed_by_its_author_until_a_message_follows(): void
    {
        $this->asMaster();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');

        // La demande compte comme le premier message : son auteur la modifie, même au tour du Support.
        $this->assertTrue($this->workflow()->mayEditDemand($ticket, $this->fromClient()));
        $this->assertFalse($this->workflow()->mayEditDemand($ticket, $this->fromClient($this->clientActor('8', 'Benoît Desjonquères'))));
        $this->assertFalse($this->workflow()->mayEditDemand($ticket, Context::ui($support)));
        // Le Support retouche le titre et la priorité, à son tour.
        $this->assertTrue($this->workflow()->mayQualify($ticket, Context::ui($support)));

        // Une note ne compte pas ; la question du Support, si.
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Je regarde.</p>', Context::ui($support));
        $this->assertTrue($this->workflow()->mayEditDemand($ticket->refresh(), $this->fromClient()));
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Depuis quand ?</p>'], Context::ui($support));
        $this->assertFalse($this->workflow()->mayEditDemand($ticket->refresh(), $this->fromClient()));
        $this->assertFalse($this->workflow()->mayQualify($ticket, Context::ui($support)));
    }

    public function test_a_move_is_tied_to_the_message_it_writes_and_only_that_one(): void
    {
        $this->asMaster();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        $apart = app(Timeline::class)->add($ticket, EntryType::Comment, '<p>Où en êtes-vous ?</p>', Context::ui($support), meta: ['reminder' => true]);
        $ask = $this->workflow()->run($ticket->refresh(), 'request-answer', ['question' => '<p>Sur quel navigateur ?</p>'], Context::ui($support));
        $reply = $this->workflow()->run($ticket->refresh(), 'answer', ['answer' => '<p>Firefox.</p>'], $this->fromClient());
        $question = $ticket->entries()->where('meta->question', true)->sole();
        $answer = $ticket->entries()->where('meta->answer', true)->sole();

        // Le message écrit par le passage le cite ; il voyage avec (l'autre application les montre aussi ensemble).
        $this->assertSame($ask->ulid, $question->meta['transition']);
        $this->assertSame($ask->ulid, Payload::entry($question)['meta']['transition']);
        $moves = TimelineItems::movesOfMessages($ticket->timelineEntries(), $ticket->transitions()->get());
        $this->assertTrue($moves->get($question->getKey())->is($ask));
        $this->assertTrue($moves->get($answer->getKey())->is($reply));
        $this->assertFalse($moves->has($apart->getKey()));

        // Un message plus ancien, sans lien, retrouve le passage qui l'a écrit : même auteur, même moment.
        $question->forceFill(['meta' => ['question' => true]])->save();
        $this->assertTrue(TimelineItems::movesOfMessages($ticket->timelineEntries(), $ticket->transitions()->get())->get($question->getKey())->is($ask));
    }

    public function test_notes_and_reminders_need_their_permission_and_stop_with_the_ticket(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $support = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.view', 'tma.ticket.handle')->create();
        $timeline = app(Timeline::class);

        $this->assertFalse($timeline->canAdd($ticket, EntryType::Note, Context::ui($support)));
        $this->assertFalse($timeline->canAdd($ticket, EntryType::Comment, Context::ui($support), reminder: true));

        $writer = $this->supportMember();
        $this->assertTrue($timeline->canAdd($ticket, EntryType::Note, Context::ui($writer)));
        $this->assertTrue($timeline->canAdd($ticket, EntryType::Comment, Context::ui($writer), reminder: true));

        $ticket->forceFill(['state' => 'closed'])->save();
        $this->assertTrue($timeline->canAdd($ticket, EntryType::Note, Context::ui($writer)));
        $this->expectException(AuthorizationException::class);
        $timeline->add($ticket, EntryType::Comment, '<p>Encore une chose.</p>', Context::ui($writer), meta: ['reminder' => true]);
    }

    public function test_notes_are_numbered_and_comment_a_message_or_the_whole_ticket(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $timeline = app(Timeline::class);

        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Je reproduis le problème : sur quelle fiche ?</p>'], Context::ui($agent));
        $message = $ticket->entries()->messages()->sole();
        $onTicket = $timeline->add($ticket, EntryType::Note, '<p>Le client a déjà eu ce bug en mars.</p>', Context::ui($agent));
        $onMessage = $timeline->add($ticket, EntryType::Note, '<p>Même cause qu’en mars.</p>', Context::ui($agent), $message);

        $this->assertSame([1, 2], [$onTicket->number, $onMessage->number]);
        $this->assertNull($onTicket->parent_id);
        $this->assertTrue($onMessage->parent->is($message));

        // L'historique ne montre que les messages, chacun avec ses notes ; toutes les notes sont dans l'état envoyé
        // à l'application cliente : plus rien n'est interne.
        $this->assertTrue($ticket->timelineEntries()->sole()->notes->sole()->is($onMessage));
        $this->assertCount(2, $ticket->noteEntries($agent));
        $this->assertSame(['comment', 'note', 'note'], array_column(Payload::snapshot($ticket)['entries'], 'type'));

        // Une note commente un message de premier niveau, pas une autre note.
        try {
            $timeline->add($ticket, EntryType::Note, '<p>Sur une note ?</p>', Context::ui($agent), $onTicket);
            $this->fail('Une note ne commente pas une note.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        // Quiconque écrit des notes en archive une ; seul son auteur la supprime, et son numéro n'est pas redonné.
        $colleague = $this->agent();
        $timeline->archive($onTicket, true, Context::ui($colleague));
        $this->assertCount(1, $ticket->noteEntries($agent));
        $this->assertCount(2, $ticket->noteEntries($agent, withArchived: true));
        $this->assertFalse($timeline->canDelete($onMessage, Context::ui($colleague)));
        $this->assertFalse($timeline->canEdit($onMessage, Context::ui($colleague)));
        $timeline->delete($onMessage, Context::ui($agent));
        $this->assertSoftDeleted($onMessage);
        $this->assertSame(3, $timeline->add($ticket, EntryType::Note, '<p>Encore une piste.</p>', Context::ui($agent))->number);

        // Une note peut n'être qu'une image ; vide, elle est refusée.
        $this->expectException(ValidationException::class);
        $timeline->add($ticket, EntryType::Note, '<p> </p>', Context::ui($agent));
    }

    public function test_a_private_note_shows_only_to_its_author(): void
    {
        $this->asMaster();
        $author = $this->agent();
        $colleague = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $ticket->forceFill(['last_activity_at' => now()->subDay()])->saveQuietly();
        $timeline = app(Timeline::class);

        // Elle ne change pas la dernière activité du ticket, que tous voient.
        $private = $timeline->add($ticket, EntryType::Note, '<p>À vérifier avant d’en parler.</p>', Context::ui($author), private: true);
        $this->assertTrue($ticket->refresh()->last_activity_at->lt(now()->subHours(12)));
        $public = $timeline->add($ticket, EntryType::Note, '<p>Pour tous.</p>', Context::ui($colleague));

        // Elle prend un numéro comme les autres, mais ne se montre qu'à son auteur.
        $this->assertSame([1, 2], [$private->number, $public->number]);
        $this->assertTrue($private->isVisibleTo($author));
        $this->assertFalse($private->isVisibleTo($colleague));
        $this->assertSame([1, 2], $ticket->noteEntries($author)->pluck('number')->all());
        $this->assertSame([2], $ticket->noteEntries($colleague)->pluck('number')->all());

        // Les autres ne l'archivent pas ; son auteur, si.
        $this->assertFalse($timeline->canArchive($private, Context::ui($colleague)));
        $this->assertTrue($timeline->canArchive($private, Context::ui($author)));

        // Elle part quand même vers l'autre application, qui la garde privée : son auteur l'y retrouve.
        $sent = collect(Payload::snapshot($ticket)['entries'])->firstWhere('ulid', $private->ulid);
        $this->assertTrue($sent['private']);
        $this->assertNull($sent['shared_at']);
        $private->forceDelete();
        $this->assertTrue(Mirror::entry($ticket, $sent)->private);

        // Son auteur la montre à tous plus tard.
        $timeline->edit($private = Entry::query()->where('ulid', $sent['ulid'])->sole(), '<p>Vérifié : on en parle.</p>', Context::ui($author), private: false);
        $this->assertTrue($private->refresh()->isVisibleTo($colleague));
        $this->assertFalse(Payload::entry($private)['private']);
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

    public function test_an_empty_message_is_refused(): void
    {
        $this->asMaster();
        $this->expectException(ValidationException::class);

        $this->workflow()->run($this->serviceDeskTicket(), 'request-answer', ['question' => '<p> </p><script>x</script>'], Context::ui($this->agent()));
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
