<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Recipient;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/**
 * Les destinataires d'un ticket ou d'une tâche : principaux et secondaires, choisis tout au long de sa vie ; l'auteur
 * d'office ; qui prend en charge confirme les siens ; le Support adressé par le compte passerelle du client, que le
 * maître traduit en ses destinataires par défaut.
 */
class RecipientsTest extends TmaTestCase
{
    /** @return array<string, string> les noms des destinataires, par rôle */
    private function roles($subject): array
    {
        return $subject->recipients()->with('actor')->orderBy('id')->get()
            ->mapWithKeys(fn (Recipient $r): array => [$r->actor->name => $r->role->value])->all();
    }

    public function test_the_author_is_always_primary_and_the_support_contact_is_chosen_at_transmission(): void
    {
        Notification::fake();
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton']);
        $leo = $this->correspondent();
        $leo->forceFill(['name' => 'Léo'])->save();
        $draft = $this->draft($leo);
        $this->assertEquals(['Léo' => 'primary'], $this->roles($draft));

        // Un seul membre du Support relié : il est le contact d'office, sans rien demander.
        Agent::create(['user_id' => User::factory()->create()->getKey(), 'email' => 'charles@notilac.fr', 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'x', 'linked_at' => now()]);
        $this->assertSame([], $this->workflow()->move('transmit')->formSchema($draft));
        $this->assertSame('Charles', Addressables::supportContact(null)?->name);

        // Plusieurs : le demandeur choisit.
        Agent::create(['user_id' => User::factory()->create()->getKey(), 'email' => 'ada@notilac.fr', 'name' => 'Ada', 'master_uid' => self::MASTER_UID, 'master_user_id' => '2', 'token' => 'y', 'linked_at' => now()]);
        $this->assertSame(['2' => 'Ada', '1' => 'Charles'], Addressables::supportContacts());
        $this->assertNull(Addressables::supportContact(null));

        Http::fake(['master.test/*' => fn (Request $request) => Http::response(['kind' => 'ticket', 'ticket' => $request['ticket'], 'entries' => [], 'transitions' => [], 'recipients' => $request['recipients']], 201)]);
        $this->actingAs($leo);
        Livewire::test(ViewTicket::class, ['record' => $draft->getKey()])
            ->mountAction('move_transmit')
            ->assertMountedActionModalSee(['Contact au Support', 'Ada', 'Charles'])
            ->setActionData(['contact' => 2])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Ticket DES-0001 : Nouveau');

        $draft->refresh();
        $this->assertSame('Ada', $draft->assignee->name);
        $this->assertSame(self::MASTER_UID, $draft->assignee->instance_uid);
        $this->assertEquals('primary', $this->roles($draft)['Ada']);
        Http::assertSent(fn (Request $request): bool => $request['ticket']['assignee']['id'] === '2');

        // Dans le maître, sans contact choisi, le seul membre du Support par défaut le devient.
        $this->asMaster();
        $charles = $this->supportMember();
        config(['filament-tma.recipients.support_defaults' => [$charles->email]]);
        $master = User::factory()->create();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'api_user_id' => $master->getKey()]);
        Passport::actingAs($master, [], 'api');
        $requester = ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'];
        $this->postJson('/api/tma/v1/tickets', [
            'ticket' => ['ulid' => strtolower((string) Str::ulid()), 'number' => 'DES-0099', 'source_instance_uid' => self::CLIENT_UID, 'created_on_instance_uid' => self::CLIENT_UID,
                'title' => 'Sans contact', 'state' => 'draft', 'requester' => $requester, 'created_by' => $requester, 'version' => 0],
            'entries' => [],
            'transition' => ['ulid' => strtolower((string) Str::ulid()), 'move' => 'transmit', 'from_state' => 'draft', 'to_state' => 'submitted', 'actor' => $requester, 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui'],
        ])->assertCreated();
        $this->assertTrue(Ticket::query()->where('number', 'DES-0099')->sole()->assignee->isUser($charles));
    }

    public function test_recipients_change_along_the_way_and_new_ones_are_told(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $requester->forceFill(['name' => 'Ada'])->save();
        $grace = $this->agent();
        $grace->forceFill(['name' => 'Grace'])->save();
        $alan = $this->agent();
        $alan->forceFill(['name' => 'Alan'])->save();
        $ticket = $this->draft($requester);
        $this->actingAs($requester);

        $editor = TestAction::make('recipients')->schemaComponent('recipients', schema: 'sideInfolist');

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible($editor)
            ->callAction($editor, ['primary_recipients' => ['u:'.$grace->getKey()], 'secondary_recipients' => ['u:'.$alan->getKey()]])
            ->assertHasNoActionErrors()
            ->assertSee(['Grace', 'Alan']);

        // L'auteur reste principal, même retiré de la liste.
        $this->assertEquals(['Ada' => 'primary', 'Grace' => 'primary', 'Alan' => 'secondary'], $this->roles($ticket));

        // Grace (principale) l'apprend dans la cloche ; Alan (secondaire) n'a rien à apprendre (il verra le ticket « En
        // copie » dans son bilan) ; Ada, l'auteur du changement, rien.
        Notification::assertSentTo($grace, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Recipient
            && $mail->via($grace) === ['database']
            && $mail->notice->data['line'] === 'Ada vous a ajouté(e) aux destinataires principaux.');
        $this->assertFalse(Notice::query()->for($alan)->exists());
        Notification::assertNotSentTo($requester, NoticeNotification::class);

        // « Tout le monde » remplit la liste ; sur un ticket, pas d'équipe projet.
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->mountAction($editor)
            ->assertActionDoesNotExist(TestAction::make('team_primary_recipients')->schemaComponent('primary_recipients', schema: 'mountedActionSchema0'))
            ->assertActionVisible(TestAction::make('clear_primary_recipients')->schemaComponent('primary_recipients', schema: 'mountedActionSchema0'))
            ->callAction(TestAction::make('everyone_secondary_recipients')->schemaComponent('secondary_recipients', schema: 'mountedActionSchema0'))
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertSame('secondary', $this->roles($ticket)['Alan']);
        $this->assertSame('primary', $this->roles($ticket)['Grace']);
    }

    public function test_a_client_addresses_the_support_through_its_gateway_account(): void
    {
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton', 'filament-tma.api.master_user' => 'support@tma.local', 'filament-tma.recipients.support_members' => ['charles@notilac.fr']]);
        $gateway = User::factory()->create(['email' => 'support@tma.local', 'name' => 'Support (TMA)']);
        $charles = $this->agent();
        $charles->forceFill(['email' => 'charles@notilac.fr', 'name' => 'Charles'])->save();
        Agent::create(['user_id' => $charles->getKey(), 'email' => 'charles@notilac.fr', 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'x', 'linked_at' => now()]);
        $team = $this->agent();
        $team->forceFill(['name' => 'Guillaume'])->save();
        $requester = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create', 'tma.ticket.correspond')->create(['name' => 'Léo']);
        User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create')->create(['name' => 'Mathilde']);
        User::factory()->withPermissions('panels.admin.access')->create(['name' => 'Sans rôle']);

        // Le Support (son compte passerelle, les membres reliés que l'application permet) et les correspondants du Support.
        $options = Addressables::options(null);
        $this->assertSame(['Support', 'Correspondants du Support'], array_keys($options));
        $this->assertSame(['support' => 'Support (toute l’équipe)', 'm:1' => 'Charles (Support)'], $options['Support']);
        $this->assertSame(['u:'.$team->getKey() => 'Guillaume', 'u:'.$requester->getKey() => 'Léo'], $options['Correspondants du Support']);
        $this->assertSame(['u:'.$team->getKey(), 'u:'.$requester->getKey()], Addressables::everyone($options));

        // Transmettre : qui transmet et le Support (le compte passerelle) deviennent principaux, et partent au maître.
        Http::fake(['master.test/*' => function (Request $request) {
            return Http::response(['kind' => 'ticket', 'ticket' => $request['ticket'], 'entries' => [], 'transitions' => [], 'recipients' => $request['recipients']], 201);
        }]);
        $ticket = $this->draft($requester);
        $this->workflow()->run($ticket, 'transmit', [], Context::ui($requester));

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/tickets')
            && collect($request['recipients'])->contains(fn (array $item): bool => $item['support'] === true && $item['actor']['id'] === (string) $gateway->getKey()));
        // Le seul membre du Support relié (Charles) en est d'office le contact, et principal.
        $this->assertEquals(['Léo' => 'primary', 'Charles' => 'primary', 'Support (TMA)' => 'primary'], $this->roles($ticket->refresh()));
        $this->assertSame('Charles', $ticket->assignee->name);
    }

    public function test_the_master_turns_the_support_into_its_default_recipients(): void
    {
        $this->asMaster();
        $charles = $this->agent();
        config(['filament-tma.recipients.support_defaults' => [$charles->email]]);
        $ticket = $this->serviceDeskTicket();
        $gateway = ['instance_uid' => self::CLIENT_UID, 'id' => '20', 'name' => 'Support (TMA)'];
        $ada = ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'];
        $leo = ['instance_uid' => self::CLIENT_UID, 'id' => '5', 'name' => 'Léo'];

        app(Recipients::class)->fromClient($ticket, self::CLIENT_UID, [
            ['actor' => $ada, 'role' => 'primary', 'support' => false],
            ['actor' => $leo, 'role' => 'secondary', 'support' => false],
            ['actor' => $gateway, 'role' => 'primary', 'support' => true],
        ]);
        $this->assertEquals(['Ada Lovelace' => 'primary', 'Léo' => 'secondary', $charles->name => 'primary'], $this->roles($ticket));

        // Le client change les siens ; il ne retire pas un membre du Support.
        app(Recipients::class)->fromClient($ticket, self::CLIENT_UID, [['actor' => $ada, 'role' => 'primary', 'support' => false]]);
        $this->assertEquals(['Ada Lovelace' => 'primary', $charles->name => 'primary'], $this->roles($ticket));

        // La liste voyage avec le ticket.
        $this->assertEqualsCanonicalizing(['7', (string) $charles->getKey()], collect(Payload::snapshot($ticket)['recipients'])->pluck('actor.id')->all());
    }

    public function test_the_master_only_sees_the_correspondents_of_the_support(): void
    {
        $this->asClient();
        $master = User::factory()->create(['email' => 'filbreeze@api.test']);
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        $correspondent = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create', 'tma.ticket.correspond')->create();
        User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create')->create();
        $support = $this->agent();
        Agent::create(['user_id' => $support->getKey(), 'email' => $support->email, 'name' => $support->name, 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'x', 'linked_at' => now()]);
        Passport::actingAs($master, [], 'api');

        $users = collect($this->getJson('/api/tma/v1/users')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame([(string) $correspondent->getKey()], $users->keys()->map(fn ($id): string => (string) $id)->all());
        $this->assertTrue($users[(string) $correspondent->getKey()]['service_desk']);

        // Ni les membres du Support reliés, ni le compte du maître.
        $this->assertFalse($users->has((string) $support->getKey()));
        $this->assertFalse($users->has((string) $master->getKey()));
    }

    public function test_a_task_goes_to_its_primaries_at_the_client(): void
    {
        $this->asMaster();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        $project = Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID]);
        Http::fake([
            'desapp.test/api/tma/v1/users*' => Http::response(['data' => [
                ['id' => '7', 'name' => 'Ada Lovelace', 'email' => 'ada@client.test', 'service_desk' => true],
                ['id' => '8', 'name' => 'Grace Hopper', 'email' => 'grace@client.test', 'service_desk' => false],
            ]]),
            'desapp.test/*' => Http::response(['missing_media' => []]),
        ]);
        $author = User::factory()->withPermissions('panels.admin.access', 'tma.task.*')->create(['name' => 'Charles']);
        $this->actingAs($author);

        // Sans principal chez le client, la tâche ne s'enregistre pas ; « Tout le monde » est là pour ça.
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Export des parrains', 'project_id' => $project->getKey()])
            ->call('create')
            ->assertHasFormErrors(['primary_recipients']);

        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Export des parrains', 'project_id' => $project->getKey(), 'primary_recipients' => ['c:7'], 'secondary_recipients' => ['c:8']])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Publiée chez EDM');

        $task = ProjectTask::query()->sole();
        $this->assertEquals(['Charles' => 'primary', 'Ada Lovelace' => 'primary', 'Grace Hopper' => 'secondary'], $this->roles($task));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync') && count($request['recipients']) === 3);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])->assertSee(['Ada Lovelace', 'Grace Hopper']);

        // Publier un brouillon sans principal chez le client : il faut en choisir un.
        $draft = app(TaskWorkflow::class)->create(['title' => 'Revue', 'project_id' => $project->getKey(), 'publish_at' => now()->addDay()], Context::ui($author));
        Livewire::test(ViewTask::class, ['record' => $draft->getKey()])
            ->mountAction('task_publish')
            ->assertMountedActionModalSee('Destinataires principaux chez le client')
            ->callMountedAction()
            ->assertHasActionErrors(['recipients' => 'required']);

        // Chez le client : Ada (principale) dans la cloche, sans courriel, et dans son bilan de la semaine ; Grace
        // (secondaire) dans son bilan seulement ; les autres, rien.
        $snapshot = Payload::snapshot($task->refresh());
        $task->recipients()->delete();
        $task->transitions()->delete();
        $task->forceDelete();
        Notice::query()->delete();

        $this->asClient();
        Notification::fake();
        $ada = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.*')->create(['id' => 7, 'name' => 'Ada Lovelace']);
        $grace = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.*')->create(['id' => 8, 'name' => 'Grace Hopper']);
        $leo = $this->agent();
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        Passport::actingAs(User::factory()->create(['email' => 'filbreeze@api.test']), [], 'api');
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        Notification::assertSentTo($ada, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Task
            && $mail->via($ada) === ['database']);
        Notification::assertNotSentTo($grace, NoticeNotification::class);
        $this->assertTrue(Notice::query()->pending()->weekly()->for($ada)->where('event', NoticeEvent::Task->value)->exists());
        $this->assertTrue(Notice::query()->pending()->weekly()->for($grace)->where('event', NoticeEvent::Task->value)->exists());
        $this->assertFalse(Notice::query()->for($leo)->exists());
        $this->assertSame(RecipientRole::Primary, ProjectTask::query()->where('ulid', $snapshot['task']['ulid'])->sole()->recipients()->whereHas('actor', fn ($q) => $q->where('instance_uid', self::CLIENT_UID)->where('remote_id', '7'))->sole()->role);
    }

    public function test_after_a_take_over_the_new_requester_is_awaited_and_the_former_one_is_told(): void
    {
        Notification::fake();
        $this->asClient();
        config(['filament-tma.api.master_user' => 'support@tma.local']);
        $gateway = User::factory()->create(['email' => 'support@tma.local', 'name' => 'Support (TMA)']);
        $leo = $this->correspondent();
        $leo->forceFill(['name' => 'Léo'])->save();
        $benoit = $this->correspondent();
        $benoit->forceFill(['name' => 'Benoît'])->save();
        $ticket = $this->draft($leo);
        $ticket->forceFill(['state' => 'awaiting_requester'])->save();
        app(Recipients::class)->add($ticket, Actor::forUser($benoit));

        // Benoît a pris la main chez le maître : l'état revient, avec son passage.
        Passport::actingAs($gateway, [], 'api');
        $snapshot = Payload::snapshot($ticket);
        $snapshot['ticket']['requester'] = Payload::actor(Actor::forUser($benoit));
        $snapshot['ticket']['version'] = $ticket->sync_version + 1;
        $snapshot['transitions'][] = ['ulid' => strtolower((string) Str::ulid()), 'move' => 'take-over', 'from_state' => 'awaiting_requester', 'to_state' => 'awaiting_requester',
            'actor' => Payload::actor(Actor::forUser($benoit)), 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui'];
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        $ticket->refresh();
        $this->assertTrue($ticket->requester->isUser($benoit));
        Notification::assertSentTo($leo, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->data['line'] === 'Benoît a pris la main : le Support attend désormais sa réponse.');
        Notification::assertNotSentTo($benoit, NoticeNotification::class);

        // C'est lui qu'on attend, lui qui répond ; Léo peut reprendre la main de la même façon.
        $this->assertTrue($this->workflow()->allows($ticket, 'answer', Context::ui($benoit)));
        $this->assertFalse($this->workflow()->allows($ticket, 'answer', Context::ui($leo)));
        $this->assertTrue($this->workflow()->allows($ticket, 'take-over', Context::ui($leo)));
        $this->assertTrue(Ticket::query()->awaiting($benoit)->whereKey($ticket->getKey())->exists());
        $this->assertFalse(Ticket::query()->awaiting($leo)->whereKey($ticket->getKey())->exists());
    }
}
