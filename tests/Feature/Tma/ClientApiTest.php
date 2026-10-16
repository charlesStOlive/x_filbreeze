<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\AutoCloseWarning;
use CharlesStOlive\FilamentTma\Notifications\TicketAutoClosed;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/** Une application cliente : elle transmet au maître, lui délègue, reçoit ce qu'il envoie (addendum §7). */
class ClientApiTest extends TmaTestCase
{
    private User $masterUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient();

        $this->masterUser = User::factory()->create(['email' => 'filbreeze@api.test']);
        config([
            'filament-tma.master.url' => 'https://master.test',
            'filament-tma.master.token' => 'jeton-maitre',
            'filament-tma.api.master_user' => 'filbreeze@api.test',
        ]);
    }

    /** Le maître répond à une transmission comme il le ferait : le ticket, passé « Nouveau » au Support. */
    private function fakeMasterAccepting(): void
    {
        Http::fake(['master.test/api/tma/v1/tickets' => fn (Request $request) => Http::response([
            'ticket' => [...$request['ticket'], 'state' => 'submitted', 'handling_mode' => 'service_desk'],
            'entries' => collect($request['entries'])->map(fn (array $entry): array => [...$entry, 'files' => []])->all(),
            'transitions' => [[...$request['transition'], 'to_state' => 'submitted']],
        ], 201)]);
    }

    private function transmittedTicket(User $requester, string $state): Ticket
    {
        $ticket = $this->draft($requester);
        $ticket->forceFill(['handling_mode' => 'service_desk', 'state' => $state, 'sync_status' => 'synced'])->save();

        return $ticket;
    }

    /** Un utilisateur du client qui n'est pas en lien avec le Support : il confie ses demandes à l'équipe EDM. */
    private function memberWithoutSupport(): User
    {
        return User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.create',
            'tma.ticket.edit', 'tma.ticket.comment', 'tma.ticket.workflow.submit', 'tma.ticket.workflow.answer',
            'tma.ticket.workflow.validate', 'tma.ticket.workflow.reopen', 'tma.ticket.workflow.cancel')->create();
    }

    public function test_the_client_team_transmits_its_requests_and_the_others_confide_theirs(): void
    {
        $team = $this->agent();
        $member = $this->memberWithoutSupport();

        // L'équipe EDM (le droit de transmettre) envoie ses demandes au Support, sans les confier à elle-même.
        $own = $this->draft($team);
        $this->assertTrue($this->workflow()->allows($own, 'transmit', Context::ui($team)));
        $this->assertFalse($this->workflow()->allows($own, 'submit', Context::ui($team)));

        // Les autres la lui confient.
        $theirs = $this->draft($member);
        $this->assertFalse($this->workflow()->allows($theirs, 'transmit', Context::ui($member)));
        $this->assertTrue($this->workflow()->allows($theirs, 'submit', Context::ui($member)));

        // L'équipe la reçoit, la prend en charge, et peut la transmettre au Support.
        $this->workflow()->run($theirs, 'submit', [], Context::ui($member));
        $this->workflow()->run($theirs, 'take-charge', [], Context::ui($team));
        $this->assertTrue($this->workflow()->allows($theirs->refresh(), 'transmit', Context::ui($team)));
    }

    public function test_the_client_team_answers_the_support_for_the_requester(): void
    {
        $member = $this->memberWithoutSupport();
        $ticket = $this->transmittedTicket($member, 'awaiting_requester');

        $answer = TestAction::make('composerReply_answer')->schemaComponent('composerActions', schema: 'composer');

        // Un correspondant du Support répond à la place du demandeur.
        $this->actingAs($this->agent());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertActionVisible($answer);

        // Le demandeur, interne, non : sa demande transmise, il n'échange plus avec le Support ; un autre interne non plus.
        // (une action masquée d'un schéma n'est même pas trouvée)
        foreach ([$member, $this->memberWithoutSupport()] as $internal) {
            $this->actingAs($internal);
            try {
                Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertActionHidden($answer);
            } catch (ActionNotResolvableException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_transmitting_sends_the_ticket_with_what_is_shared_and_follows_the_master(): void
    {
        Storage::fake('local');
        $this->fakeMasterAccepting();
        $requester = $this->agent();
        $ticket = $this->draft($requester);
        $timeline = app(Timeline::class);
        Storage::disk('local')->put('tma-uploads/capture.png', 'png');
        $timeline->add($ticket, EntryType::Comment, '<p>Capture jointe.</p>', Context::ui($requester), uploads: [['path' => 'tma-uploads/capture.png', 'name' => 'capture.png']]);
        $timeline->add($ticket, EntryType::Note, '<p>Une note : elle part aussi.</p>', Context::ui($requester));

        $this->assertTrue($this->workflow()->allows($ticket, 'transmit', Context::ui($requester)));
        $this->workflow()->run($ticket, 'transmit', [], Context::ui($requester));

        $this->assertInstanceOf(Submitted::class, $ticket->state);
        $this->assertSame('service_desk', $ticket->handling_mode->value);
        $this->assertSame('synced', $ticket->sync_status->value);
        $this->assertSame(1, $ticket->transitions()->count());

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-maitre')
            && count($request['entries']) === 2
            && $request['entries'][0]['files'][0]['file_name'] === 'capture.png'
            && $request['entries'][1]['type'] === 'note' && $request['entries'][1]['number'] === 1);

        // Désormais, c'est le maître qui a la main : plus de modification ici.
        $this->assertFalse($ticket->fieldsEditableHere());
        $this->assertFalse($this->workflow()->allows($ticket, 'transmit', Context::ui($requester)));
    }

    public function test_a_failed_transmission_leaves_the_ticket_as_it_was(): void
    {
        Http::fake(['master.test/*' => Http::response(['message' => 'Erreur'], 500)]);
        $requester = $this->agent();
        $ticket = $this->draft($requester);

        try {
            $this->workflow()->run($ticket, 'transmit', [], Context::ui($requester));
            $this->fail('La transmission aurait dû échouer.');
        } catch (SyncException $e) {
            $this->assertStringContainsString('500', $e->getMessage());
        }

        $ticket->refresh();
        $this->assertInstanceOf(Draft::class, $ticket->state);
        $this->assertSame('local', $ticket->handling_mode->value);
        $this->assertSame(0, $ticket->transitions()->count());
    }

    public function test_without_master_the_service_desk_is_closed(): void
    {
        config(['filament-tma.master.url' => null]);
        $requester = $this->agent();

        $this->assertFalse($this->workflow()->allows($this->draft($requester), 'transmit', Context::ui($requester)));
    }

    public function test_a_requester_move_on_a_service_desk_ticket_is_done_by_the_master(): void
    {
        $requester = $this->agent();
        $ticket = $this->transmittedTicket($requester, 'awaiting_requester');

        Http::fake(['master.test/api/tma/v1/tickets/*/moves' => function (Request $request) use ($ticket) {
            $snapshot = Payload::snapshot($ticket);
            $snapshot['ticket']['state'] = 'in_progress';
            $snapshot['transitions'][] = [
                'ulid' => $request['transition_ulid'], 'move' => $request['move'], 'from_state' => 'awaiting_requester',
                'to_state' => 'in_progress', 'actor' => $request['actor'], 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui',
            ];
            $snapshot['entries'][] = [
                'ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => $request['data']['answer'],
                'author' => $request['actor'], 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui',
            ];

            return Http::response($snapshot);
        }]);

        $files = [['uuid' => 'u-1', 'name' => 'capture', 'file_name' => 'capture.png', 'mime_type' => 'image/png', 'content' => base64_encode('png')]];
        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Sous Firefox.</p>', 'files' => $files], Context::ui($requester));

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
        $this->assertSame('answer', $ticket->transitions()->sole()->move);
        $this->assertSame('<p>Sous Firefox.</p>', $ticket->entries()->sole()->body);
        // Les fichiers de la réponse partent avec le passage.
        Http::assertSent(fn (Request $request): bool => $request['move'] === 'answer' && $request['actor']['instance_uid'] === self::CLIENT_UID
            && $request['data']['files'][0]['file_name'] === 'capture.png');

        // L'intervenant, c'est le maître : pas ici.
        $this->assertFalse($this->workflow()->allows($ticket, 'request-answer', Context::ui($this->agent())));
    }

    public function test_a_comment_on_a_service_desk_ticket_is_kept_only_if_the_master_got_it(): void
    {
        $requester = $this->agent();
        $ticket = $this->transmittedTicket($requester, 'in_progress');
        $timeline = app(Timeline::class);

        $masterUp = false;
        Http::fake(['master.test/*' => function (Request $request) use (&$masterUp) {
            // Le maître a déjà trois notes : celle du client prend le n° 4.
            return $masterUp
                ? Http::response(['entry' => ['number' => $request['type'] === 'note' ? 4 : null]], 201)
                : Http::response(['message' => 'indisponible'], 503);
        }]);

        try {
            $timeline->add($ticket, EntryType::Comment, '<p>Un détail.</p>', Context::ui($requester));
            $this->fail('Le commentaire aurait dû échouer.');
        } catch (SyncException) {
            $this->assertSame(0, Entry::query()->count());
        }

        $masterUp = true;
        $timeline->add($ticket, EntryType::Comment, '<p>Un détail.</p>', Context::ui($requester));
        $this->assertSame(1, Entry::query()->count());

        // Une note part comme un message, et prend le numéro que le maître lui donne.
        $message = Entry::query()->messages()->sole();
        $note = $timeline->add($ticket, EntryType::Note, '<p>Pour tous.</p>', Context::ui($requester), $message);
        Http::assertSentCount(3);
        $this->assertSame(4, $note->refresh()->number);
        Http::assertSent(fn (Request $request): bool => $request['type'] === 'note' && $request['parent_ulid'] === $message->ulid);

        // Modifier, archiver, supprimer : la même entrée repart.
        $timeline->edit($message, '<p>Un détail, précisé.</p>', Context::ui($requester));
        $timeline->archive($note, true, Context::ui($requester));
        $timeline->delete($note, Context::ui($requester));
        Http::assertSentCount(6);
        Http::assertSent(fn (Request $request): bool => $request['ulid'] === $message->ulid && $request['edited_at'] !== null && $request['body'] === '<p>Un détail, précisé.</p>');
        Http::assertSent(fn (Request $request): bool => $request['ulid'] === $note->ulid && $request['archived_at'] !== null && $request['deleted_at'] === null);
        Http::assertSent(fn (Request $request): bool => $request['ulid'] === $note->ulid && $request['deleted_at'] !== null);

        // Si le maître ne répond pas, rien ne change ici.
        $masterUp = false;
        try {
            $timeline->edit($message, '<p>Encore.</p>', Context::ui($requester));
            $this->fail('La modification aurait dû échouer.');
        } catch (SyncException) {
            $this->assertSame('<p>Un détail, précisé.</p>', $message->refresh()->body);
        }
    }

    public function test_the_master_pushes_and_the_requester_is_told_here(): void
    {
        Notification::fake();
        $requester = $this->agent();
        $ticket = $this->transmittedTicket($requester, 'awaiting_requester');
        Passport::actingAs($this->masterUser, [], 'api');

        $snapshot = Payload::snapshot($ticket);
        $snapshot['ticket'] = [...$snapshot['ticket'], 'auto_close_warned_at' => now()->toIso8601String(), 'version' => 3, 'time' => ['total_minutes' => 150, 'by_month' => ['2026-10' => 150]]];

        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk()->assertJsonPath('missing_media', []);
        Notification::assertSentTo($requester, AutoCloseWarning::class);
        $this->assertSame(150, $ticket->refresh()->time_total_minutes);

        $snapshot['ticket'] = [...$snapshot['ticket'], 'state' => 'closed', 'version' => 4];
        $snapshot['transitions'][] = [
            'ulid' => strtolower((string) Str::ulid()), 'move' => 'auto-close', 'from_state' => 'awaiting_requester', 'to_state' => 'closed',
            'actor' => null, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'scheduler', 'reason' => 'Clôture automatique',
        ];
        $snapshot['entries'][] = [
            'ulid' => $entryUlid = strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>Voir la capture.</p>',
            'author' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID,
            'origin' => 'ui', 'files_count' => 1,
        ];

        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk()->assertJsonPath('missing_media', [$entryUlid]);
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        $this->assertInstanceOf(Closed::class, $ticket->refresh()->state);
        Notification::assertSentToTimes($requester, TicketAutoClosed::class, 1);
        $this->assertSame(1, $ticket->entries()->count());
        $this->assertSame('Charles', $ticket->entries()->sole()->author->name);

        // Puis les fichiers qui manquaient.
        $this->postJson("/api/tma/v1/media/entry/{$entryUlid}", ['files' => [[
            'uuid' => 'u1', 'name' => 'capture', 'file_name' => 'capture.txt', 'mime_type' => 'text/plain', 'size' => 4, 'content' => base64_encode('test'),
        ]]])->assertOk()->assertJsonPath('files_count', 1);

        // Une version plus ancienne ne revient pas en arrière.
        $snapshot['ticket'] = [...$snapshot['ticket'], 'state' => 'in_progress', 'version' => 2];
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $this->assertInstanceOf(Closed::class, $ticket->refresh()->state);
    }

    public function test_only_the_master_may_push_and_only_tickets_of_this_application(): void
    {
        $ticket = $this->transmittedTicket($this->agent(), 'in_progress');
        $snapshot = Payload::snapshot($ticket);

        Passport::actingAs(User::factory()->create(), [], 'api');
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertForbidden();

        Passport::actingAs($this->masterUser, [], 'api');
        $snapshot['ticket']['source_instance_uid'] = '01JTESTOTHER00000000000000';
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertUnprocessable();

        // Côté maître, ces routes n'existent pas.
        $this->asMaster();
        $this->getJson('/api/tma/v1/users')->assertNotFound();
    }

    public function test_the_users_of_the_service_desk_without_the_local_only_ones(): void
    {
        $desk = User::factory()->withPermissions('tma.ticket.viewany', 'tma.ticket.workflow.transmit')->create(['name' => 'Ada']);
        $localOnly = User::factory()->withPermissions('tma.ticket.viewany', 'tma.ticket.workflow.submit')->create(['name' => 'Bob']);
        User::factory()->create(['name' => 'Zoé']);
        Passport::actingAs($this->masterUser, [], 'api');

        $this->getJson('/api/tma/v1/users')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $desk->getKey())
            ->assertJsonPath('data.0.service_desk', true);

        $all = $this->getJson('/api/tma/v1/users?include_local_only=1')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([(string) $desk->getKey(), (string) $localOnly->getKey()], array_column($all, 'id'));
    }
}
