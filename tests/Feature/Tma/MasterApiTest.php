<?php

namespace Tests\Feature\Tma;

use App\Models\Company;
use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\TicketSyncFailed;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/** L'API du maître, appelée par une application cliente ; et l'envoi du maître vers elle (addendum §7). */
class MasterApiTest extends TmaTestCase
{
    private Instance $instance;

    private User $apiUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();

        $this->apiUser = User::factory()->create(['email' => 'desapp@api.test']);
        $this->instance = Instance::create([
            'uid' => self::CLIENT_UID,
            'name' => 'Desapp',
            'url' => 'https://desapp.test',
            'client_type' => (new Company)->getMorphClass(),
            'client_id' => Company::create(['title' => 'DES', 'slug' => 'des'])->getKey(),
            'api_user_id' => $this->apiUser->getKey(),
            'token' => 'jeton-desapp',
        ]);
    }

    private function actingAsClient(?User $user = null): void
    {
        Passport::actingAs($user ?? $this->apiUser, [], 'api');
    }

    /** @return array<string, mixed> ce qu'envoie Desapp en transmettant un ticket */
    private function transmission(array $ticket = [], array $entries = []): array
    {
        $requester = ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace', 'email' => 'ada@client.test'];

        return [
            'ticket' => [
                'ulid' => $ticket['ulid'] ?? strtolower((string) Str::ulid()),
                'number' => 'DES-0042',
                'source_instance_uid' => self::CLIENT_UID,
                'created_on_instance_uid' => self::CLIENT_UID,
                'title' => 'Export des parrains',
                'description' => '<p>Le CSV est vide.</p><script>x</script>',
                'priority' => 'high',
                'state' => 'draft',
                'requester' => $requester,
                'created_by' => $requester,
                'version' => 0,
                ...$ticket,
            ],
            'entries' => $entries,
            'transition' => [
                'ulid' => strtolower((string) Str::ulid()),
                'move' => 'transmit',
                'from_state' => 'draft',
                'to_state' => 'submitted',
                'actor' => $requester,
                'origin_instance_uid' => self::CLIENT_UID,
                'origin' => 'ui',
            ],
        ];
    }

    private function entryPayload(string $type, array $more = []): array
    {
        return [
            'ulid' => strtolower((string) Str::ulid()),
            'type' => $type,
            'body' => '<p>Contenu '.$type.'</p>',
            'author' => ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace', 'email' => 'ada@client.test'],
            'origin_instance_uid' => self::CLIENT_UID,
            'origin' => 'ui',
            ...$more,
        ];
    }

    public function test_a_transmission_creates_the_ticket_for_the_calling_application_only_once(): void
    {
        $this->actingAsClient();
        $payload = $this->transmission(entries: [
            $this->entryPayload('note', ['number' => 1, 'files' => [[
                'uuid' => 'f1', 'name' => 'capture', 'file_name' => 'capture.png', 'mime_type' => 'image/png', 'size' => 70,
                'content' => base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=')),
            ]]]),
            $this->entryPayload('note', ['number' => 2]),
        ]);

        $this->postJson('/api/tma/v1/tickets', $payload)->assertCreated()->assertJsonPath('ticket.state', 'submitted');

        $ticket = Ticket::query()->sole();
        $this->assertInstanceOf(Submitted::class, $ticket->state);
        $this->assertTrue($ticket->isShared());
        $this->assertSame(self::CLIENT_UID, $ticket->source_instance_uid);
        $this->assertTrue($ticket->client->is($this->instance->client));
        $this->assertStringNotContainsString('<script', $ticket->description);
        $this->assertSame(2, $ticket->entries()->count());
        $this->assertCount(1, Entry::query()->where('number', 1)->sole()->getMedia('attachments'));
        $this->assertSame($payload['transition']['ulid'], $ticket->transitions()->sole()->ulid);

        // Rejouée (réseau…), la transmission ne crée rien de plus.
        $this->postJson('/api/tma/v1/tickets', $payload)->assertOk();
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, $ticket->transitions()->count());
        $this->assertSame(2, $ticket->entries()->count());
        $this->assertSame(1, Entry::query()->where('number', 1)->sole()->getMedia('attachments')->count());
    }

    public function test_an_application_only_reaches_its_own_tickets(): void
    {
        $this->actingAsClient();
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid = strtolower((string) Str::ulid())]))->assertCreated();

        $otherUser = User::factory()->create();
        Instance::create(['uid' => '01JTESTOTHER00000000000000', 'name' => 'EDM', 'url' => 'https://edm.test', 'api_user_id' => $otherUser->getKey()]);
        $this->actingAsClient($otherUser);

        $this->getJson("/api/tma/v1/tickets/{$ulid}")->assertNotFound();
        $this->postJson("/api/tma/v1/tickets/{$ulid}/entries", $this->entryPayload('comment'))->assertNotFound();
        // Se faire passer pour une autre application dans le contenu ne sert à rien : c'est le jeton qui compte.
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid]))->assertUnprocessable();

        // Un jeton qui n'est celui d'aucune application cliente.
        $this->actingAsClient(User::factory()->create());
        $this->getJson("/api/tma/v1/tickets/{$ulid}")->assertForbidden();

        // Une application désactivée.
        $this->instance->update(['is_active' => false]);
        $this->actingAsClient();
        $this->getJson("/api/tma/v1/tickets/{$ulid}")->assertForbidden();
    }

    public function test_the_requester_moves_are_done_here_and_the_others_refused(): void
    {
        Storage::fake('local');
        $this->actingAsClient();
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid = strtolower((string) Str::ulid())]))->assertCreated();
        $ticket = Ticket::query()->sole();

        $actor = ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'];

        // Demander des précisions, c'est le Support : le maître, pas l'application cliente.
        $this->postJson("/api/tma/v1/tickets/{$ulid}/moves", ['move' => 'request-answer', 'data' => ['question' => '<p>?</p>'], 'actor' => $actor, 'transition_ulid' => strtolower((string) Str::ulid())])
            ->assertForbidden();

        $agent = $this->agent();
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel export ?</p>'], Context::ui($agent));

        // Une autre personne de l'application ne répond pas à la place du demandeur.
        $benoit = ['instance_uid' => self::CLIENT_UID, 'id' => '8', 'name' => 'Benoît Desjonquères'];
        $this->postJson("/api/tma/v1/tickets/{$ulid}/moves", ['move' => 'answer', 'data' => ['answer' => '<p>Moi.</p>'], 'actor' => $benoit, 'transition_ulid' => strtolower((string) Str::ulid())])
            ->assertForbidden();

        // La réponse arrive avec sa capture : elle suit le passage (zone de réponse de l'application cliente).
        $capture = ['uuid' => (string) Str::uuid(), 'name' => 'capture', 'file_name' => 'capture.png', 'mime_type' => 'image/png',
            'content' => base64_encode(UploadedFile::fake()->image('capture.png', 20, 20)->getContent())];
        $move = ['move' => 'answer', 'data' => ['answer' => '<p>Celui des parrains actifs.</p>', 'files' => [$capture]], 'actor' => $actor, 'transition_ulid' => strtolower((string) Str::ulid())];
        $this->postJson("/api/tma/v1/tickets/{$ulid}/moves", $move)->assertOk()->assertJsonPath('ticket.state', 'in_progress');
        $this->postJson("/api/tma/v1/tickets/{$ulid}/moves", $move)->assertOk();

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
        $this->assertSame(1, $ticket->transitions()->where('move', 'answer')->count());
        $answer = $ticket->entries()->whereNotNull('parent_id')->sole();
        $this->assertSame(self::CLIENT_UID, $answer->author->instance_uid);
        $this->assertSame('capture.png', $answer->getFirstMedia('attachments')->file_name);
    }

    public function test_entries_from_the_client_are_received_once(): void
    {
        Event::fake([EntryAdded::class]);
        $this->actingAsClient();
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid = strtolower((string) Str::ulid())]))->assertCreated();

        // Le demandeur parle en répondant : un message à part n'est qu'une relance.
        $this->postJson("/api/tma/v1/tickets/{$ulid}/entries", $this->entryPayload('comment'))->assertForbidden();

        $reminder = $this->entryPayload('comment', ['meta' => ['reminder' => true]]);
        $this->postJson("/api/tma/v1/tickets/{$ulid}/entries", $reminder)->assertCreated();
        $this->postJson("/api/tma/v1/tickets/{$ulid}/entries", $reminder)->assertOk();

        $this->assertSame(1, Entry::query()->count());
        Event::assertDispatchedTimes(EntryAdded::class, 1);
    }

    public function test_a_client_changes_its_own_entries_and_only_archives_the_support_notes(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $this->actingAsClient();
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid = strtolower((string) Str::ulid())]))->assertCreated();
        $ticket = Ticket::query()->sole();
        $url = "/api/tma/v1/tickets/{$ulid}/entries";
        $supportNote = app(Timeline::class)->add($ticket, EntryType::Note, '<p>Du Support.</p>', Context::ui($this->agent()));

        // Le n° 1 est pris par la note du Support : celle du client prend le suivant, et le maître le lui dit.
        $message = $this->entryPayload('comment', ['meta' => ['reminder' => true]]);
        $note = $this->entryPayload('note', ['number' => 1, 'parent_ulid' => $message['ulid']]);
        $this->postJson($url, $message)->assertCreated();
        $this->postJson($url, $note)->assertCreated()->assertJsonPath('entry.number', 2);
        $this->assertSame($message['ulid'], Entry::query()->where('ulid', $note['ulid'])->sole()->parent->ulid);

        // Renvoyées, ses entrées changent : le texte d'un message, l'archivage puis la suppression d'une note.
        $this->postJson($url, [...$message, 'body' => '<p>Corrigé.</p>', 'edited_at' => now()->toIso8601String()])->assertOk();
        $this->assertSame('<p>Corrigé.</p>', Entry::query()->where('ulid', $message['ulid'])->sole()->body);
        $this->postJson($url, [...$note, 'archived_at' => now()->toIso8601String()])->assertOk();
        $this->assertNotNull(Entry::query()->where('ulid', $note['ulid'])->sole()->archived_at);
        $this->postJson($url, [...$note, 'deleted_at' => now()->toIso8601String()])->assertOk();
        $this->assertSoftDeleted('tma_entries', ['ulid' => $note['ulid']]);

        // Sur une note du Support, il ne fait qu'archiver : ni son texte, ni son auteur ne changent.
        $this->postJson($url, [...Payload::entry($supportNote), 'body' => '<p>Réécrit.</p>', 'archived_at' => now()->toIso8601String()])->assertOk();
        $supportNote->refresh();
        $this->assertSame('<p>Du Support.</p>', $supportNote->body);
        $this->assertNotNull($supportNote->archived_at);
        $this->assertSame(self::MASTER_UID, $supportNote->origin_instance_uid);

        // Un autre message l'a suivi (la question du Support) : il ne se modifie plus.
        $this->travel(1)->minute();
        $this->workflow()->run($ticket->refresh(), 'request-answer', ['question' => '<p>Bien reçu : lequel ?</p>'], Context::ui($this->agent()));
        $this->postJson($url, [...$message, 'body' => '<p>Encore corrigé.</p>'])->assertUnprocessable();
        $this->assertSame('<p>Corrigé.</p>', Entry::query()->where('ulid', $message['ulid'])->sole()->body);
    }

    public function test_the_author_changes_the_demand_until_a_message_follows(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $this->actingAsClient();
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid = strtolower((string) Str::ulid())]))->assertCreated();
        $ticket = Ticket::query()->sole();
        $ada = ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'];
        $demand = ['ticket' => ['title' => 'Export des parrains actifs', 'description' => '<p>Le CSV des actifs est vide.</p>', 'priority' => 'urgent'], 'actor' => $ada];

        $this->patchJson("/api/tma/v1/tickets/{$ulid}", $demand)->assertOk()->assertJsonPath('ticket.title', 'Export des parrains actifs');
        $this->assertSame('urgent', $ticket->refresh()->priority->value);

        // Pas une autre personne de l'application.
        $this->patchJson("/api/tma/v1/tickets/{$ulid}", [...$demand, 'actor' => [...$ada, 'id' => '8']])->assertForbidden();

        // Un message du Support a suivi : la demande ne se modifie plus.
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Lequel ?</p>'], Context::ui($this->agent()));
        $this->patchJson("/api/tma/v1/tickets/{$ulid}", $demand)->assertForbidden();
    }

    public function test_changes_made_here_are_pushed_to_the_client(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $ticket = $this->serviceDeskTicket('submitted');

        $this->workflow()->run($ticket, 'pause', ['until' => now()->addDay()->toDateTimeString()], Context::ui($this->agent()));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://desapp.test/api/tma/v1/sync'
            && $request->hasHeader('Authorization', 'Bearer jeton-desapp')
            && $request['ticket']['state'] === 'paused'
            && $request['transitions'][0]['move'] === 'pause');

        $this->assertSame('synced', $ticket->refresh()->sync_status->value);
        $this->assertSame(1, $ticket->sync_version);
    }

    public function test_notes_leave_too_and_files_follow_on_demand(): void
    {
        $ticket = $this->serviceDeskTicket('in_progress');
        $agent = $this->agent();
        // Le client dit qu'il lui manque les fichiers de la dernière entrée reçue.
        Http::fake([
            'desapp.test/api/tma/v1/sync' => fn (Request $request) => Http::response(['missing_media' => [collect($request['entries'])->last()['ulid']]]),
            'desapp.test/api/tma/v1/media/*' => Http::response(['files_count' => 1]),
        ]);

        // Une note part comme un message : plus rien n'est interne.
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Pour tous.</p>', Context::ui($agent));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/sync')
            && collect($request['entries'])->contains(fn (array $entry): bool => $entry['type'] === 'note' && $entry['number'] === 1));

        Storage::fake('local');
        Storage::disk('local')->put('tma-uploads/log.txt', 'erreur 500');

        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Voici le journal.</p>', Context::ui($agent), uploads: [['path' => 'tma-uploads/log.txt', 'name' => 'log.txt']]);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/media/entry/')
            && ($request['files'][0]['content'] ?? null) !== null
            && base64_decode($request['files'][0]['content']) === 'erreur 500');
    }

    public function test_a_client_that_does_not_answer_puts_the_ticket_in_error(): void
    {
        Event::fake([TicketSyncFailed::class]);
        Http::fake(['desapp.test/*' => Http::response(['message' => 'Maintenance'], 503)]);
        $ticket = $this->serviceDeskTicket('submitted');

        // L'action du Support n'échoue pas pour autant.
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Lequel ?</p>'], Context::ui($this->agent()));

        $this->assertSame('awaiting_requester', $ticket->refresh()->state::getMorphClass());
        $this->assertSame('error', $ticket->sync_status->value);
        $this->assertStringContainsString('503', $ticket->last_sync_error);
        Event::assertDispatched(TicketSyncFailed::class);
    }
}
