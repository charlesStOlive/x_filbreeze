<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\EditInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\RelationManagers\AgentsRelationManager;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\AgentLink;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Les membres du Support : une personne de l'équipe du maître, qui a aussi un compte dans une application
 * cliente, y agit pour le Support, en son nom du maître, jamais comme demandeur.
 */
class AgentsTest extends TmaTestCase
{
    private function person(string $email, string ...$permissions): User
    {
        $user = User::factory()->withPermissions('panels.admin.access', ...$permissions)->create(['email' => $email, 'name' => 'Charles']);
        $user->assignRole(Role::findOrCreate('agents', 'web'));

        return $user;
    }

    private function personalAccessClient(): void
    {
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'TMA', '--provider' => 'users', '--no-interaction' => true]);
    }

    public function test_the_master_links_an_agent_and_sends_a_token_limited_to_this_application(): void
    {
        $this->asMaster();
        $this->personalAccessClient();
        $instance = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        $instance->ensureServiceAccount();
        $charles = $this->person('charles@notilac.fr', 'tma.*');
        Http::fake(['desapp.test/api/tma/v1/agents' => Http::response(['name' => 'Charles'])]);
        $this->actingAs($this->agent('tma.instance.*'));

        Livewire::test(AgentsRelationManager::class, ['ownerRecord' => $instance, 'pageClass' => EditInstance::class])
            ->callTableAction('linkAgent', data: ['user_id' => $charles->getKey()])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(AgentLink::query()->sole()->user->is($charles));
        $this->assertSame(1, $charles->tokens()->where('name', 'tma-agent:'.self::CLIENT_UID)->where('revoked', false)->count());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://desapp.test/api/tma/v1/agents'
            && $request['email'] === 'charles@notilac.fr' && $request['master_uid'] === self::MASTER_UID && strlen($request['token']) > 100);
    }

    public function test_an_agent_token_only_reaches_the_tickets_of_its_application_with_the_agents_own_rights(): void
    {
        $this->asMaster();
        $this->personalAccessClient();
        $charles = $this->person('charles@notilac.fr', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.comment');
        $token = \CharlesStOlive\FilamentTma\Support\ServiceAccount::agentToken($charles, self::CLIENT_UID);
        $mine = $this->serviceDeskTicket('submitted');
        $other = $this->serviceDeskTicket('submitted', 'EDM-0001');
        $other->forceFill(['source_instance_uid' => '01JTESTOTHER00000000000000'])->save();
        $this->withHeader('Authorization', 'Bearer '.$token);

        $refs = array_column($this->getJson('/api/tma/v1/agent/tickets')->assertOk()->json('data'), 'ref');
        $this->assertSame(['DES-0001'], $refs);
        $this->getJson('/api/tma/v1/agent/tickets/EDM-0001')->assertNotFound();

        // Ses droits à lui : il commente, mais ne prend pas en charge sans le droit de le faire.
        $this->postJson('/api/tma/v1/agent/tickets/DES-0001/entries', ['type' => 'comment', 'body' => '<p>Je regarde.</p>'])
            ->assertCreated()
            ->assertJsonPath('data.snapshot.ticket.number', 'DES-0001');
        $this->postJson('/api/tma/v1/agent/tickets/DES-0001/moves', ['move' => 'take-charge'])->assertForbidden();
        $this->assertSame('submitted', $mine->refresh()->state::getMorphClass());
        $this->assertSame('Charles', $mine->entries()->sole()->author->name);
        $this->assertSame(self::MASTER_UID, $mine->entries()->sole()->author->instance_uid);
    }

    public function test_in_the_client_an_agent_acts_for_the_service_desk_never_as_requester(): void
    {
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton-instance']);
        $charles = $this->person('charles@notilac.fr', 'tma.*');
        Agent::create(['user_id' => $charles->getKey(), 'email' => $charles->email, 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'jeton-agent-charles', 'linked_at' => now()]);
        $ticket = $this->draft($this->agent());
        $ticket->forceFill(['handling_mode' => 'service_desk', 'state' => 'awaiting_requester'])->save();

        // Le demandeur, c'est l'application ; lui est l'intervenant.
        $this->assertFalse($this->workflow()->allows($ticket, 'answer', Context::ui($charles)));
        $this->assertTrue($this->workflow()->allows($ticket, 'resume', Context::ui($charles)));

        // Un passage part dans le maître, avec SON jeton, et l'état revient.
        Http::fake(['master.test/api/tma/v1/agent/tickets/*/moves' => function (Request $request) use ($ticket) {
            $snapshot = Payload::snapshot($ticket);
            $snapshot['ticket']['state'] = 'in_progress';
            $snapshot['transitions'][] = ['ulid' => $request['transition_ulid'], 'move' => 'resume', 'from_state' => 'awaiting_requester', 'to_state' => 'in_progress',
                'actor' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'api'];

            return Http::response(['data' => ['snapshot' => $snapshot]]);
        }, 'master.test/api/tma/v1/agent/tickets/*/entries' => function (Request $request) use ($ticket) {
            $entry = ['ulid' => strtolower((string) Str::ulid()), 'type' => $request['type'], 'body' => $request['body'],
                'author' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'api'];
            $snapshot = Payload::snapshot($ticket->refresh());
            $snapshot['entries'][] = $entry;

            return Http::response(['data' => ['entry' => $entry, 'snapshot' => $snapshot]], 201);
        }]);

        $this->workflow()->run($ticket, 'resume', [], Context::ui($charles));
        $this->assertSame('in_progress', $ticket->refresh()->state::getMorphClass());
        $this->assertSame(self::MASTER_UID, $ticket->transitions()->sole()->actor->instance_uid);

        $entry = app(Timeline::class)->add($ticket, EntryType::Comment, '<p>C’est corrigé.</p>', Context::ui($charles));
        $this->assertSame(self::MASTER_UID, $entry->author->instance_uid);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-agent-charles'));
        Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-instance'));

        // Il ne transmet pas une demande au Support : il en fait partie.
        $draft = $this->draft($charles);
        $this->assertFalse($this->workflow()->allows($draft, 'transmit', Context::ui($charles)));
    }

    public function test_the_client_receives_its_agents_and_hides_them_from_the_requesters(): void
    {
        $this->asClient();
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        $charles = $this->person('charles@notilac.fr', 'tma.ticket.viewany', 'tma.ticket.workflow.transmit');
        $ada = User::factory()->withPermissions('tma.ticket.viewany', 'tma.ticket.workflow.transmit')->create(['name' => 'Ada']);
        Passport::actingAs(User::factory()->create(['email' => 'filbreeze@api.test']), [], 'api');

        $payload = ['email' => 'charles@notilac.fr', 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => 1, 'token' => str_repeat('t', 40)];
        $this->postJson('/api/tma/v1/agents', $payload)->assertOk();
        $this->postJson('/api/tma/v1/agents', [...$payload, 'email' => 'inconnu@notilac.fr'])->assertNotFound();

        $this->assertSame(str_repeat('t', 40), Agent::query()->sole()->token);
        $this->assertSame([(string) $ada->getKey()], array_column($this->getJson('/api/tma/v1/users')->json('data'), 'id'));

        $this->postJson('/api/tma/v1/agents/unlink', ['email' => 'charles@notilac.fr'])->assertOk();
        $this->assertSame(0, Agent::query()->count());
    }
}
