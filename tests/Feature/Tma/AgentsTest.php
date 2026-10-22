<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Livewire\AgentTimeEntries;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\EditInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\RelationManagers\AgentsRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\AgentLink;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\ServiceAccount;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
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
        $charles = $this->person('charles@notilac.fr', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.note');
        $token = ServiceAccount::agentToken($charles, self::CLIENT_UID);
        $mine = $this->serviceDeskTicket('submitted');
        $other = $this->serviceDeskTicket('submitted', 'EDM-0001');
        $other->forceFill(['source_instance_uid' => '01JTESTOTHER00000000000000'])->save();
        $this->withHeader('Authorization', 'Bearer '.$token);

        $refs = array_column($this->getJson('/api/tma/v1/agent/tickets')->assertOk()->json('data'), 'ref');
        $this->assertSame(['DES-0001'], $refs);
        $this->getJson('/api/tma/v1/agent/tickets/EDM-0001')->assertNotFound();

        // Ses droits à lui : il écrit une note, mais sans être membre du Support (`handle`), ni message ni prise en charge.
        $this->postJson('/api/tma/v1/agent/tickets/DES-0001/entries', ['type' => 'note', 'body' => '<p>Je regarde.</p>'])
            ->assertCreated()
            ->assertJsonPath('data.snapshot.ticket.number', 'DES-0001');
        $this->postJson('/api/tma/v1/agent/tickets/DES-0001/entries', ['type' => 'comment', 'body' => '<p>Je regarde.</p>'])->assertForbidden();
        $this->postJson('/api/tma/v1/agent/tickets/DES-0001/moves', ['move' => 'request-answer', 'data' => ['question' => '<p>Laquelle ?</p>']])->assertForbidden();
        $this->assertSame('submitted', $mine->refresh()->state::getMorphClass());
        $this->assertSame('Charles', $mine->entries()->sole()->author->name);
        $this->assertSame(self::MASTER_UID, $mine->entries()->sole()->author->instance_uid);

        // Il modifie sa note, en son nom ; l'état revient avec.
        $ulid = $mine->entries()->sole()->ulid;
        $this->patchJson("/api/tma/v1/agent/tickets/DES-0001/entries/{$ulid}", ['body' => '<p>Je regarde ce soir.</p>'])
            ->assertOk()
            ->assertJsonPath('data.snapshot.entries.0.body', '<p>Je regarde ce soir.</p>');
        $this->assertNotNull($mine->entries()->sole()->edited_at);
    }

    public function test_a_support_member_prepares_a_ticket_at_the_support_from_the_client(): void
    {
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton-instance']);
        $charles = $this->person('charles@notilac.fr', 'tma.*');
        Agent::create(['user_id' => $charles->getKey(), 'email' => $charles->email, 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'jeton-agent-charles', 'linked_at' => now()]);
        $task = ProjectTask::query()->create(['ulid' => strtolower((string) Str::ulid()), 'number' => 'T-0001', 'target_instance_uid' => self::CLIENT_UID, 'title' => 'Maquettes', 'state' => 'in_progress', 'published_at' => now()]);

        // Il n'est jamais demandeur ici : « Nouvelle demande chez le Support » ouvre la création dans le maître.
        $this->actingAs($charles);
        $this->assertFalse(TicketResource::canCreate());
        Livewire::test(ListTickets::class)
            ->assertActionHidden('create')
            ->assertActionHasUrl('createAtSupport', 'https://master.test/admin/tma/tickets/create?instance='.self::CLIENT_UID);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionHidden('createTicket')
            ->assertActionHasUrl('createTicketAtSupport', 'https://master.test/admin/tma/tickets/create?task='.$task->ulid);
        // L'adresse du panneau, telle que le navigateur la joint, se règle à part.
        config(['filament-tma.master.panel_url' => 'http://localhost:8080/admin/']);
        Livewire::test(ListTickets::class)->assertActionHasUrl('createAtSupport', 'http://localhost:8080/admin/tma/tickets/create?instance='.self::CLIENT_UID);

        // Un correspondant n'a que « Nouvelle demande ».
        $this->actingAs($this->correspondent());
        Livewire::test(ListTickets::class)->assertActionVisible('create')->assertActionHidden('createAtSupport');

        // Dans le maître, l'application est choisie d'avance ; depuis une tâche (par son ulid), le ticket en part.
        $this->asMaster();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        Http::fake(['desapp.test/*' => Http::response(['data' => []])]);
        $this->actingAs($this->agent('tma.task.view', 'tma.task.viewany'));
        Livewire::withQueryParams(['instance' => self::CLIENT_UID])->test(CreateTicket::class)
            ->assertSchemaStateSet(['instance_uid' => self::CLIENT_UID]);
        Livewire::withQueryParams(['task' => $task->ulid])->test(CreateTicket::class)
            ->assertSchemaStateSet(['title' => 'Maquettes', 'instance_uid' => self::CLIENT_UID])
            ->assertSee('À partir de la tâche T-0001');
    }

    public function test_an_agent_reads_changes_and_deletes_time_in_the_master(): void
    {
        $this->asMaster();
        $this->personalAccessClient();
        $charles = $this->person('charles@notilac.fr', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.time', 'tma.ticket.handle');
        $token = ServiceAccount::agentToken($charles, self::CLIENT_UID);
        $ticket = $this->serviceDeskTicket();
        $time = app(TimeLog::class)->add($ticket, 60, '2026-10-01', Context::ui($charles));
        $invoiced = app(TimeLog::class)->add($ticket, 30, '2026-10-02', Context::ui($charles));
        $invoiced->forceFill(['invoice_type' => 'invoice', 'invoice_id' => 1])->saveQuietly();
        $this->withHeader('Authorization', 'Bearer '.$token);

        $this->getJson('/api/tma/v1/agent/tickets/DES-0001/time')
            ->assertOk()
            ->assertJsonPath('data.time_entries.0.ulid', $invoiced->ulid)
            ->assertJsonPath('data.time_entries.0.invoiced', true)
            ->assertJsonPath('data.time_entries.1.minutes', 60)
            ->assertJsonPath('data.time_entries.1.actor', 'Charles');

        $this->patchJson("/api/tma/v1/agent/tickets/DES-0001/time/{$time->ulid}", ['minutes' => 90, 'billable' => false])
            ->assertOk()
            ->assertJsonPath('data.snapshot.ticket.time.total_minutes', 120);
        $this->assertSame([90, false], [$time->refresh()->minutes, $time->billable]);

        // Une saisie facturée ne bouge plus ; au-delà de 24 h, non plus.
        $this->patchJson("/api/tma/v1/agent/tickets/DES-0001/time/{$invoiced->ulid}", ['delete' => true])->assertStatus(422);
        $this->patchJson("/api/tma/v1/agent/tickets/DES-0001/time/{$time->ulid}", ['minutes' => 1500])->assertUnprocessable();

        $this->patchJson("/api/tma/v1/agent/tickets/DES-0001/time/{$time->ulid}", ['delete' => true])->assertOk();
        $this->assertSoftDeleted($time);

        // Sans le droit de saisir du temps, rien.
        $grace = $this->person('grace@notilac.fr', 'tma.ticket.viewany', 'tma.ticket.view');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.ServiceAccount::agentToken($grace, self::CLIENT_UID));
        $this->getJson('/api/tma/v1/agent/tickets/DES-0001/time')->assertForbidden();
    }

    public function test_in_the_client_an_agent_acts_for_the_service_desk_never_as_requester(): void
    {
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton-instance']);
        $charles = $this->person('charles@notilac.fr', 'tma.*');
        Agent::create(['user_id' => $charles->getKey(), 'email' => $charles->email, 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'jeton-agent-charles', 'linked_at' => now()]);
        $ticket = $this->draft($this->agent());
        $ticket->forceFill(['state' => 'awaiting_requester'])->save();

        // Le demandeur, c'est l'application ; lui est le Support (ici, avec ses droits du maître : un forçage).
        $this->assertFalse($this->workflow()->allows($ticket, 'answer', Context::ui($charles)));
        $this->assertTrue($this->workflow()->allows($ticket, 'force-return', Context::ui($charles)));

        // Un passage part dans le maître, avec SON jeton, et l'état revient.
        Http::fake(['master.test/api/tma/v1/agent/tickets/*/moves' => function (Request $request) use ($ticket) {
            $snapshot = Payload::snapshot($ticket);
            $snapshot['ticket']['state'] = 'in_progress';
            $snapshot['transitions'][] = ['ulid' => $request['transition_ulid'], 'move' => 'force-return', 'from_state' => 'awaiting_requester', 'to_state' => 'in_progress',
                'actor' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'api'];

            return Http::response(['data' => ['snapshot' => $snapshot]]);
        }, 'master.test/api/tma/v1/agent/tickets/*/entries' => function (Request $request) use ($ticket) {
            $entry = ['ulid' => strtolower((string) Str::ulid()), 'type' => $request['type'], 'body' => $request['body'],
                'author' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'api'];
            $snapshot = Payload::snapshot($ticket->refresh());
            $snapshot['entries'][] = $entry;

            return Http::response(['data' => ['entry' => $entry, 'snapshot' => $snapshot]], 201);
        }]);

        $this->workflow()->run($ticket, 'force-return', [], Context::ui($charles));
        $this->assertSame('in_progress', $ticket->refresh()->state::getMorphClass());
        $this->assertSame(self::MASTER_UID, $ticket->transitions()->sole()->actor->instance_uid);

        // Un message va avec un passage : à part, il écrit une note, chez le Support et en son nom de là-bas.
        $this->assertFalse(app(Timeline::class)->canAdd($ticket, EntryType::Comment, Context::ui($charles)));
        $entry = app(Timeline::class)->add($ticket, EntryType::Note, '<p>C’est corrigé.</p>', Context::ui($charles));
        $this->assertSame(self::MASTER_UID, $entry->author->instance_uid);

        // Il modifie sa note dans le maître, toujours avec son jeton ; la note modifiée revient.
        Http::fake(['master.test/api/tma/v1/agent/tickets/*/entries/*' => function (Request $request) use ($ticket, $entry) {
            $snapshot = Payload::snapshot($ticket->refresh());
            $snapshot['entries'] = collect($snapshot['entries'])
                ->map(fn (array $payload): array => $payload['ulid'] === $entry->ulid ? [...$payload, 'body' => $request['body'], 'edited_at' => now()->toIso8601String()] : $payload)
                ->all();

            return Http::response(['data' => ['snapshot' => $snapshot]]);
        }]);
        $this->assertTrue(app(Timeline::class)->canEdit($entry, Context::ui($charles)));
        app(Timeline::class)->edit($entry, '<p>C’est corrigé, et testé.</p>', Context::ui($charles));
        $this->assertSame('<p>C’est corrigé, et testé.</p>', $entry->refresh()->body);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && $request->hasHeader('Authorization', 'Bearer jeton-agent-charles'));

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-agent-charles'));
        Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-instance'));

        // Il ne fait pas de demande au Support : il en fait partie.
        $this->expectException(AuthorizationException::class);
        $this->draft($charles);
    }

    public function test_the_client_receives_its_agents_and_hides_them_from_the_requesters(): void
    {
        $this->asClient();
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        $charles = $this->person('charles@notilac.fr', 'tma.ticket.viewany', 'tma.ticket.correspond');
        $ada = User::factory()->withPermissions('tma.ticket.viewany', 'tma.ticket.correspond')->create(['name' => 'Ada']);
        Passport::actingAs(User::factory()->create(['email' => 'filbreeze@api.test']), [], 'api');

        $payload = ['email' => 'charles@notilac.fr', 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => 1, 'token' => str_repeat('t', 40)];
        $this->postJson('/api/tma/v1/agents', $payload)->assertOk();
        $this->postJson('/api/tma/v1/agents', [...$payload, 'email' => 'inconnu@notilac.fr'])->assertNotFound();

        $this->assertSame(str_repeat('t', 40), Agent::query()->sole()->token);
        $this->assertSame([(string) $ada->getKey()], array_column($this->getJson('/api/tma/v1/users')->json('data'), 'id'));

        $this->postJson('/api/tma/v1/agents/unlink', ['email' => 'charles@notilac.fr'])->assertOk();
        $this->assertSame(0, Agent::query()->count());
    }

    public function test_in_the_client_time_is_read_only_except_for_a_support_member(): void
    {
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton-instance']);
        $charles = $this->person('charles@notilac.fr', 'tma.*');
        Agent::create(['user_id' => $charles->getKey(), 'email' => $charles->email, 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'jeton-agent-charles', 'linked_at' => now()]);
        $requester = $this->agent();
        $ticket = $this->draft($requester);
        $ticket->forceFill(['state' => 'in_progress', 'time_total_minutes' => 90])->save();

        // Un utilisateur du client : les totaux, en lecture, en bas du volet.
        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSeeInOrder(['Suivi', 'Temps passé', 'Ce ticket : 1 h 30'])
            ->assertDontSeeLivewire(TimeEntriesRelationManager::class)
            ->assertDontSee('Saisir du temps');

        // Un membre du Support y voit le détail, lu dans le maître avec son jeton ; il en saisit, modifie, supprime.
        $snapshot = function (int $total) use ($ticket): array {
            $snapshot = Payload::snapshot($ticket->refresh());
            $snapshot['ticket']['version'] = $ticket->sync_version + 1;
            $snapshot['ticket']['time'] = ['total_minutes' => $total, 'by_month' => [now()->format('Y-m') => $total]];

            return ['data' => ['snapshot' => $snapshot]];
        };
        Http::fake([
            'master.test/api/tma/v1/agent/tickets/*/time/*' => fn () => Http::response($snapshot(60)),
            'master.test/api/tma/v1/agent/tickets/*/time' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['data' => ['time_entries' => [
                    ['ulid' => 'time-ada', 'actor' => 'Ada', 'worked_on' => '2026-10-01', 'minutes' => 60, 'billable' => true, 'invoiced' => false, 'entry_ulid' => null],
                    ['ulid' => 'time-old', 'actor' => 'Grace', 'worked_on' => '2026-09-01', 'minutes' => 30, 'billable' => false, 'invoiced' => true, 'entry_ulid' => null],
                ]]])
                : Http::response($snapshot(150), 201),
        ]);
        $this->actingAs($charles);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSeeLivewire(AgentTimeEntries::class)
            ->assertDontSeeLivewire(TimeEntriesRelationManager::class);

        Livewire::test(AgentTimeEntries::class, ['record' => $ticket])
            ->assertSee(['Ce ticket : 1 h 30', 'Ada', '1 h', 'Grace', '30 min', 'Non facturable · Facturée'])
            // Une saisie facturée ne bouge plus.
            ->assertActionVisible(TestAction::make('edit')->table('time-ada'))
            ->assertActionHidden(TestAction::make('edit')->table('time-old'))
            ->assertActionHidden(TestAction::make('delete')->table('time-old'))
            ->callAction(TestAction::make('logTime')->table(), ['worked_on' => now()->toDateString(), 'duration_hours' => 1, 'billable' => true])
            ->assertNotified('Temps enregistré chez le Support');
        $this->assertSame(150, $ticket->refresh()->time_total_minutes);
        // Sur un ticket, le temps n'a pas de texte : ici, il porte sur la fiche entière.
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer jeton-agent-charles')
            && $request['minutes'] === 60 && ! isset($request['summary']) && ! isset($request['entry_ulid']));

        Livewire::test(AgentTimeEntries::class, ['record' => $ticket])
            ->mountAction(TestAction::make('edit')->table('time-ada'))
            ->assertSchemaStateSet(['duration_days' => null, 'duration_hours' => 1, 'duration_minutes' => null, 'worked_on' => '2026-10-01'], 'mountedActionSchema0');
        Livewire::test(AgentTimeEntries::class, ['record' => $ticket])
            ->callAction(TestAction::make('edit')->table('time-ada'), ['duration_minutes' => 30])
            ->assertNotified('Temps modifié chez le Support')
            ->callAction(TestAction::make('delete')->table('time-ada'))
            ->assertNotified('Saisie supprimée chez le Support');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && str_ends_with($request->url(), '/time/time-ada') && ($request->data()['minutes'] ?? null) === 90);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && ($request->data()['delete'] ?? null) === true);
        $this->assertSame(60, $ticket->refresh()->time_total_minutes);
    }
}
