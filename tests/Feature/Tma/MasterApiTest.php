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
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Http\Client\Request;
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
                'handling_mode' => 'local',
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
            $this->entryPayload('comment', ['files' => [[
                'uuid' => 'f1', 'name' => 'capture', 'file_name' => 'capture.png', 'mime_type' => 'image/png', 'size' => 70,
                'content' => base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=')),
            ]]]),
            $this->entryPayload('note', ['shared_at' => now()->toIso8601String()]),
        ]);

        $this->postJson('/api/tma/v1/tickets', $payload)->assertCreated()->assertJsonPath('ticket.state', 'submitted');

        $ticket = Ticket::query()->sole();
        $this->assertInstanceOf(Submitted::class, $ticket->state);
        $this->assertSame('service_desk', $ticket->handling_mode->value);
        $this->assertSame(self::CLIENT_UID, $ticket->source_instance_uid);
        $this->assertTrue($ticket->client->is($this->instance->client));
        $this->assertStringNotContainsString('<script', $ticket->description);
        $this->assertSame(2, $ticket->entries()->count());
        $this->assertCount(1, Entry::query()->where('type', 'comment')->sole()->getMedia('attachments'));
        $this->assertSame($payload['transition']['ulid'], $ticket->transitions()->sole()->ulid);

        // Rejouée (réseau…), la transmission ne crée rien de plus.
        $this->postJson('/api/tma/v1/tickets', $payload)->assertOk();
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, $ticket->transitions()->count());
        $this->assertSame(2, $ticket->entries()->count());
        $this->assertSame(1, Entry::query()->where('type', 'comment')->sole()->getMedia('attachments')->count());
    }

    public function test_an_internal_note_is_never_received(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/tma/v1/tickets', $this->transmission(entries: [$this->entryPayload('note')]))
            ->assertUnprocessable();
        $this->assertSame(0, Ticket::query()->count());
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
        $this->actingAsClient();
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid = strtolower((string) Str::ulid())]))->assertCreated();
        $ticket = Ticket::query()->sole();

        $actor = ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'];

        // Prendre en charge, c'est l'intervenant : le maître, pas l'application cliente.
        $this->postJson("/api/tma/v1/tickets/{$ulid}/moves", ['move' => 'take-charge', 'actor' => $actor, 'transition_ulid' => strtolower((string) Str::ulid())])
            ->assertForbidden();

        $agent = $this->agent();
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($agent));
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel export ?</p>'], Context::ui($agent));

        $move = ['move' => 'answer', 'data' => ['answer' => '<p>Celui des parrains actifs.</p>'], 'actor' => $actor, 'transition_ulid' => strtolower((string) Str::ulid())];
        $this->postJson("/api/tma/v1/tickets/{$ulid}/moves", $move)->assertOk()->assertJsonPath('ticket.state', 'in_progress');
        $this->postJson("/api/tma/v1/tickets/{$ulid}/moves", $move)->assertOk();

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
        $this->assertSame(1, $ticket->transitions()->where('move', 'answer')->count());
        $answer = $ticket->entries()->whereNotNull('parent_id')->sole();
        $this->assertSame(self::CLIENT_UID, $answer->author->instance_uid);
    }

    public function test_entries_from_the_client_are_received_once(): void
    {
        Event::fake([EntryAdded::class]);
        $this->actingAsClient();
        $this->postJson('/api/tma/v1/tickets', $this->transmission(['ulid' => $ulid = strtolower((string) Str::ulid())]))->assertCreated();

        $comment = $this->entryPayload('comment');
        $this->postJson("/api/tma/v1/tickets/{$ulid}/entries", $comment)->assertCreated();
        $this->postJson("/api/tma/v1/tickets/{$ulid}/entries", $comment)->assertOk();

        $this->assertSame(1, Entry::query()->count());
        Event::assertDispatchedTimes(EntryAdded::class, 1);
    }

    public function test_changes_made_here_are_pushed_to_the_client(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $ticket = $this->serviceDeskTicket('submitted');

        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($this->agent()));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://desapp.test/api/tma/v1/sync'
            && $request->hasHeader('Authorization', 'Bearer jeton-desapp')
            && $request['ticket']['state'] === 'in_progress'
            && $request['transitions'][0]['move'] === 'take-charge');

        $this->assertSame('synced', $ticket->refresh()->sync_status->value);
        $this->assertSame(1, $ticket->sync_version);
    }

    public function test_internal_notes_never_leave_and_files_follow_on_demand(): void
    {
        $ticket = $this->serviceDeskTicket('in_progress');
        $agent = $this->agent();
        // Le client dit qu'il lui manque les fichiers de la dernière entrée reçue.
        Http::fake([
            'desapp.test/api/tma/v1/sync' => fn (Request $request) => Http::response(['missing_media' => [collect($request['entries'])->last()['ulid']]]),
            'desapp.test/api/tma/v1/media/*' => Http::response(['files_count' => 1]),
        ]);

        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Interne.</p>', Context::ui($agent));
        Http::assertNothingSent();

        Storage::fake('local');
        Storage::disk('local')->put('tma-uploads/log.txt', 'erreur 500');

        app(Timeline::class)->add($ticket, EntryType::Comment, '<p>Voici le journal.</p>', Context::ui($agent), uploads: [['path' => 'tma-uploads/log.txt', 'name' => 'log.txt']]);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/media/entry/')
            && base64_decode($request['files'][0]['content']) === 'erreur 500');
        Http::assertNotSent(fn (Request $request): bool => str_contains(json_encode($request->data()), 'Interne.'));
    }

    public function test_a_client_that_does_not_answer_puts_the_ticket_in_error(): void
    {
        Event::fake([TicketSyncFailed::class]);
        Http::fake(['desapp.test/*' => Http::response(['message' => 'Maintenance'], 503)]);
        $ticket = $this->serviceDeskTicket('submitted');

        // L'action du centre de service n'échoue pas pour autant.
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($this->agent()));

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
        $this->assertSame('error', $ticket->sync_status->value);
        $this->assertStringContainsString('503', $ticket->last_sync_error);
        Event::assertDispatched(TicketSyncFailed::class);
    }
}
