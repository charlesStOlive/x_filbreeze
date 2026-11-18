<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Support\Commit;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * « En cours » (addendum §3.2) : le Support dit où il en est sans rendre la main ; le demandeur n'ajoute que des notes ;
 * le dernier message du Support reste modifiable. Un commit peut porter un message du Support.
 */
class TicketProgressTest extends TmaTestCase
{
    private function desapp(): Instance
    {
        return Instance::query()->firstOrCreate(['uid' => self::CLIENT_UID], ['name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
    }

    private static function composer(string $name): TestAction
    {
        return TestAction::make($name)->schemaComponent('composerActions', schema: 'composer');
    }

    public function test_the_support_starts_a_request_without_a_message_and_keeps_the_hand(): void
    {
        $this->asMaster();
        Http::fake();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');

        // Le demandeur n'a pas ce passage : ce n'est pas son tour.
        $this->assertFalse($this->workflow()->allows($ticket, 'progress', $this->fromClient()));

        $this->workflow()->run($ticket, 'progress', [], Context::ui($support));

        $this->assertInstanceOf(InProgress::class, $ticket->state);
        $this->assertTrue($ticket->assignee->isUser($support));
        $this->assertNotNull($ticket->taken_at);
        $this->assertSame(0, $ticket->entries()->count());
        $transition = $ticket->transitions()->sole();
        $this->assertSame(['progress', 'submitted', 'in_progress'], [$transition->move, $transition->from_state, $transition->to_state]);
    }

    public function test_in_progress_the_support_writes_and_edits_its_last_message_and_the_requester_only_adds_notes(): void
    {
        $this->asMaster();
        Http::fake();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        $timeline = app(Timeline::class);

        $this->workflow()->run($ticket, 'progress', ['message' => '<p>Je regarde l’export.</p>'], Context::ui($support));
        $first = $ticket->entries()->messages()->sole();
        $this->assertTrue($first->meta['progress']);

        // En cours, le passage n'est que son message : sans texte, rien.
        try {
            $this->workflow()->run($ticket, 'progress', ['message' => '<p></p>'], Context::ui($support));
            $this->fail('Un « En cours » sur un ticket déjà en cours a un message.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->workflow()->run($ticket->refresh(), 'progress', ['message' => '<p>Le tri est corrigé, reste le format des dates.</p>'], Context::ui($support));
        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
        $second = $ticket->entries()->messages()->latest('id')->first();
        $this->assertSame(['in_progress', 'in_progress'], [$ticket->transitions()->latest('id')->first()->from_state, $ticket->transitions()->latest('id')->first()->to_state]);

        // Le demandeur n'écrit pas de message pendant ce temps ; une note, si.
        $this->assertFalse($timeline->canAdd($ticket, EntryType::Comment, $this->fromClient()));
        $timeline->add($ticket, EntryType::Note, '<p>Pensez aussi à l’export des filleuls.</p>', $this->fromClient());

        // Le dernier message du Support reste le dernier : il le modifie ; pas le précédent.
        $this->assertTrue($timeline->canEdit($second->refresh(), Context::ui($support)));
        $this->assertFalse($timeline->canEdit($first->refresh(), Context::ui($support)));
        $timeline->edit($second, '<p>Tri et format des dates corrigés.</p>', Context::ui($support));
        $this->assertSame('<p>Tri et format des dates corrigés.</p>', $second->refresh()->body);
        $this->assertNotNull($second->edited_at);
    }

    public function test_a_commit_carries_a_support_message_and_the_ticket_lists_them(): void
    {
        $this->asMaster();
        Http::fake();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        $url = 'https://github.com/charlesStOlive/desapp/commit/704f0e3e576f9f631557036ea4c3322f0b6a5110';

        $this->workflow()->run($ticket, 'progress', ['message' => '<p>Premier correctif.</p>', 'commit' => ' 704F0E3 '], Context::ui($support));
        $this->assertSame('704f0e3', $ticket->entries()->messages()->sole()->meta['commit']);

        // Un commit porte un message ; ce n'est qu'une empreinte ou un lien.
        foreach ([['message' => '', 'commit' => 'abcdef1'], ['message' => '<p>Suite.</p>', 'commit' => 'pas un commit']] as $data) {
            try {
                $this->workflow()->run($ticket->refresh(), 'progress', $data, Context::ui($support));
                $this->fail('Commit refusé : '.json_encode($data));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->workflow()->run($ticket->refresh(), 'submit-for-validation', ['message' => '<p>Corrigé.</p>', 'commit' => $url], Context::ui($support));
        $resolved = $ticket->entries()->messages()->latest('id')->first();
        $this->assertSame($url, Commit::of($resolved));
        $this->assertSame('704f0e3', Commit::label($url));
        $this->assertSame($url, Commit::url($url));
        $this->assertNull(Commit::url('704f0e3'));

        // Les commits du ticket, chacun une fois, du premier au dernier.
        $this->assertSame(['704f0e3', $url], Commit::forTicket($ticket)->pluck('commit')->all());

        // Il voyage avec le message jusqu'au client.
        $sent = collect(Payload::snapshot($ticket->refresh())['entries'])->firstWhere('ulid', $resolved->ulid);
        $this->assertSame($url, $sent['meta']['commit']);

        // Son auteur le change ou le retire avec le message, tant qu'il est le dernier.
        $timeline = app(Timeline::class);
        $timeline->edit($resolved, '<p>Corrigé.</p>', Context::ui($support), commit: 'ABCDEF1234');
        $this->assertSame('abcdef1234', Commit::of($resolved->refresh()));
        $timeline->edit($resolved, '<p>Corrigé.</p>', Context::ui($support), commit: '');
        $this->assertNull(Commit::of($resolved->refresh()));
        $timeline->edit($resolved, '<p>Corrigé, voir la fiche.</p>', Context::ui($support));
        $this->assertNull(Commit::of($resolved->refresh()));

        // Le demandeur, lui, n'en met pas sur ses messages.
        $this->workflow()->run($ticket->refresh(), 'reopen', ['message' => '<p>Toujours cassé.</p>'], $this->fromClient());
        $reopen = $ticket->entries()->messages()->latest('id')->first();
        $this->assertFalse(Commit::allowedOn($reopen));
        $this->expectException(ValidationException::class);
        $timeline->edit($reopen, '<p>Toujours cassé.</p>', $this->fromClient(), commit: 'abcdef1');
    }

    public function test_progress_news_go_to_the_bell_and_the_morning_digest_never_as_a_turn(): void
    {
        Notification::fake();
        $this->asMaster();
        Http::fake();
        $this->desapp();
        $handler = $this->supportMember();
        $colleague = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        $ticket->forceFill(['title' => 'Bouton d’export', 'assignee_actor_id' => Actor::forUser($handler)->getKey()])->save();
        app(Recipients::class)->add($ticket, Actor::forUser($handler));
        app(Recipients::class)->add($ticket, Actor::forUser($colleague));

        // Un collègue du Support fait le point : le contact l'apprend, pour information, sans que rien ne l'attende.
        $this->workflow()->run($ticket, 'progress', ['message' => '<p>Le tri est corrigé.</p>', 'commit' => 'abcdef1'], Context::ui($colleague));

        $notice = Notice::query()->for($handler)->sole();
        $this->assertSame(NoticeEvent::Progress, $notice->event);
        $this->assertSame('info', $notice->data['tone']);
        $this->assertSame(NoticeMode::Grouped, $notice->mode);
        $this->assertStringContainsString('a commencé à traiter la demande', $notice->data['line']);
        $this->assertSame('Le tri est corrigé.', $notice->data['excerpt']);
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->via($handler) === ['database']);
        $this->assertFalse(Notice::query()->for($colleague)->exists());

        // Les nouvelles suivantes, sur un ticket déjà en cours.
        $this->workflow()->run($ticket->refresh(), 'progress', ['message' => '<p>Reste le format des dates.</p>'], Context::ui($colleague));
        $this->assertStringContainsString('donne des nouvelles', Notice::query()->for($handler)->latest('id')->first()->data['line']);

        // Et le bilan du matin les reprend.
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($handler, DailyDigest::class, function (DailyDigest $mail) use ($handler): bool {
            $html = html_entity_decode((string) $mail->toMail($handler)->render());

            return str_contains($html, 'a commencé à traiter la demande') && str_contains($html, 'Reste le format des dates.');
        });
    }

    public function test_the_reply_area_offers_in_progress_with_a_commit(): void
    {
        $this->asMaster();
        Http::fake();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        $this->actingAs($support);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible(self::composer('composerReply_progress'))
            ->assertSchemaComponentExists('commit', 'composer')
            ->fillForm(['body' => '<p>Je regarde l’export.</p>', 'commit' => 'https://github.com/charlesStOlive/desapp/commit/abcdef1234567'], 'composer')
            ->callAction(self::composer('composerReply_progress'))
            ->assertHasNoErrors()
            ->assertSee('abcdef1')
            // Toujours son tour : « En cours » reste là pour le message suivant.
            ->assertActionVisible(self::composer('composerReply_progress'));

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
        $this->assertSame('https://github.com/charlesStOlive/desapp/commit/abcdef1234567', Commit::of($ticket->entries()->messages()->sole()));
    }

    public function test_an_ai_writes_in_progress_with_its_commit_from_the_command_line(): void
    {
        $this->asMaster();
        Http::fake();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('submitted');
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'Commandes TMA', '--provider' => 'users', '--no-interaction' => true]);
        $token = $agent->createToken('tma-cli')->accessToken;

        $this->artisan('tma:comment', ['ref' => 'DES-0001', '--body' => 'Export corrigé, en test.', '--commit' => 'ABCDEF1', '--token' => $token])
            ->expectsOutputToContain('message « En cours » ajouté(e)')
            ->assertSuccessful();

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
        $message = $ticket->entries()->messages()->sole();
        $this->assertEqualsCanonicalizing(['progress' => true, 'commit' => 'abcdef1'], array_diff_key($message->meta, ['transition' => true]));

        // Une note ne porte pas de commit.
        $this->artisan('tma:comment', ['ref' => 'DES-0001', '--body' => 'À surveiller.', '--note' => true, '--commit' => 'abcdef1', '--token' => $token])
            ->expectsOutputToContain('porte un commit')
            ->assertFailed();
        $this->assertSame(1, Entry::query()->count());
    }
}
