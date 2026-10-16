<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
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
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/**
 * Les destinataires d'un ticket ou d'une tâche : principaux et secondaires, choisis tout au long de sa vie ; l'auteur
 * d'office ; un principal exigé dans l'équipe qui reçoit, à l'envoi et à la prise en charge ; le Support adressé par
 * le compte passerelle du client, que le maître traduit en ses destinataires par défaut.
 */
class RecipientsTest extends TmaTestCase
{
    /** @return array<string, string> les noms des destinataires, par rôle */
    private function roles($subject): array
    {
        return $subject->recipients()->with('actor')->orderBy('id')->get()
            ->mapWithKeys(fn (Recipient $r): array => [$r->actor->name => $r->role->value])->all();
    }

    public function test_the_author_is_always_primary_and_the_receiving_team_needs_one(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.create', 'tma.ticket.comment', 'tma.ticket.workflow.submit')->create(['name' => 'Léo']);
        $guillaume = $this->agent();
        $guillaume->forceFill(['name' => 'Guillaume'])->save();
        $mathilde = $this->agent();
        $mathilde->forceFill(['name' => 'Mathilde'])->save();

        $ticket = $this->draft($requester);
        $this->assertEquals(['Léo' => 'primary'], $this->roles($ticket));

        // Confier : au moins un principal dans l'équipe (ou la personne désignée).
        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->mountAction('move_submit')
            ->assertMountedActionModalSee(['Guillaume', 'Mathilde'])
            ->callMountedAction()
            ->assertHasActionErrors(['recipients' => 'required_without']);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction('move_submit', ['recipients' => ['u:'.$mathilde->getKey()]])
            ->assertHasNoActionErrors();
        $this->assertEquals(['Léo' => 'primary', 'Mathilde' => 'primary'], $this->roles($ticket));
        Notification::assertSentTo($mathilde, NoticeNotification::class);
        Notification::assertNotSentTo($guillaume, NoticeNotification::class);

        // Prendre en charge : qui prend est principal par défaut ; il confirme ou ajoute.
        $this->actingAs($guillaume);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->mountAction('move_take_charge')
            ->assertMountedActionModalSee('Destinataires principaux de votre côté')
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertEquals(['Léo' => 'primary', 'Mathilde' => 'primary', 'Guillaume' => 'primary'], $this->roles($ticket));

        // Sans écran (API, commande) et sans personne désignée, toute l'équipe.
        $other = $this->draft($requester);
        $this->workflow()->run($other, 'submit', [], Context::ui($requester));
        $this->assertEqualsCanonicalizing(['Léo', 'Guillaume', 'Mathilde'], array_keys($this->roles($other)));
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

        // Grace (principale) l'apprend tout de suite ; Alan (secondaire) dans son bilan ; Ada, l'auteur du changement, rien.
        Notification::assertSentTo($grace, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Recipient
            && $mail->notice->data['line'] === 'Ada vous a ajouté(e) aux destinataires principaux.');
        $this->assertTrue(Notice::query()->pending()->for($alan)->where('event', NoticeEvent::Recipient->value)->exists());
        Notification::assertNotSentTo($requester, NoticeNotification::class);

        // « Tout le monde » remplit la liste.
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->mountAction($editor)
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
        $requester = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create', 'tma.ticket.workflow.transmit')->create(['name' => 'Léo']);
        $internal = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create', 'tma.ticket.workflow.submit')->create(['name' => 'Mathilde']);
        User::factory()->withPermissions('panels.admin.access')->create(['name' => 'Sans rôle']);

        // Une demande d'ici : les correspondants du Support (le droit de lui écrire) et les internes ; pas le Support.
        $options = Addressables::options(null);
        $this->assertSame(['Correspondants du Support', 'Internes'], array_keys($options));
        $this->assertSame(['u:'.$team->getKey() => 'Guillaume', 'u:'.$requester->getKey() => 'Léo'], $options['Correspondants du Support']);
        $this->assertSame(['u:'.$internal->getKey() => 'Mathilde'], $options['Internes']);
        $this->assertSame(['u:'.$team->getKey(), 'u:'.$requester->getKey(), 'u:'.$internal->getKey()], Addressables::everyone($options));

        // L'équipe qui traite (confier, prendre en charge) est à part : qui peut prendre en charge.
        $this->assertSame(['Équipe locale' => ['u:'.$team->getKey() => 'Guillaume']], Addressables::handlers());

        // Transmettre : qui transmet et le Support (le compte passerelle) deviennent principaux, et partent au maître.
        Http::fake(['master.test/*' => function (Request $request) {
            return Http::response(['kind' => 'ticket', 'ticket' => $request['ticket'], 'entries' => [], 'transitions' => [], 'recipients' => $request['recipients']], 201);
        }]);
        $ticket = $this->draft($requester);
        $this->workflow()->run($ticket, 'transmit', [], Context::ui($team));

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/tickets')
            && collect($request['recipients'])->contains(fn (array $item): bool => $item['support'] === true && $item['actor']['id'] === (string) $gateway->getKey()));
        $this->assertEquals(['Léo' => 'primary', 'Guillaume' => 'primary', 'Support (TMA)' => 'primary'], $this->roles($ticket->refresh()));

        // Une demande au Support : le Support et ses correspondants ; un interne n'y a pas sa place.
        $options = Addressables::options(null, $ticket);
        $this->assertSame(['support' => 'Support (toute l’équipe)', 'm:1' => 'Charles (Support)'], $options['Support']);
        $this->assertArrayNotHasKey('Internes', $options);
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
        $correspondent = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create', 'tma.ticket.workflow.transmit')->create();
        $internal = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.create', 'tma.ticket.workflow.submit')->create();
        $support = $this->agent();
        Agent::create(['user_id' => $support->getKey(), 'email' => $support->email, 'name' => $support->name, 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'x', 'linked_at' => now()]);
        Passport::actingAs($master, [], 'api');

        $users = collect($this->getJson('/api/tma/v1/users')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame([(string) $correspondent->getKey()], $users->keys()->map(fn ($id): string => (string) $id)->all());
        $this->assertTrue($users[(string) $correspondent->getKey()]['service_desk']);

        // Les internes, seulement à la demande.
        $all = collect($this->getJson('/api/tma/v1/users?include_local_only=1')->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($all[(string) $internal->getKey()]['service_desk']);
        $this->assertFalse($all->has((string) $support->getKey()));
        $this->assertFalse($all->has((string) $master->getKey()));
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

        // Chez le client : Ada (principale) tout de suite, Grace (secondaire) dans son bilan, les autres rien.
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

        Notification::assertSentTo($ada, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Task);
        Notification::assertNotSentTo($grace, NoticeNotification::class);
        $this->assertTrue(Notice::query()->pending()->for($grace)->where('event', NoticeEvent::Task->value)->exists());
        $this->assertFalse(Notice::query()->for($leo)->exists());
        $this->assertSame(RecipientRole::Primary, ProjectTask::query()->where('ulid', $snapshot['task']['ulid'])->sole()->recipients()->whereHas('actor', fn ($q) => $q->where('instance_uid', self::CLIENT_UID)->where('remote_id', '7'))->sole()->role);
    }

    public function test_an_internal_request_sent_to_the_support_leaves_its_author_out(): void
    {
        Notification::fake();
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton', 'filament-tma.api.master_user' => 'support@tma.local']);
        $gateway = User::factory()->create(['email' => 'support@tma.local', 'name' => 'Support (TMA)']);
        $mathilde = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.create', 'tma.ticket.comment', 'tma.ticket.workflow.submit', 'tma.ticket.workflow.answer', 'tma.ticket.workflow.validate')->create(['name' => 'Mathilde']);
        $guillaume = $this->agent();
        $guillaume->forceFill(['name' => 'Guillaume'])->save();

        $ticket = $this->draft($mathilde);
        $this->workflow()->run($ticket, 'submit', ['recipients' => ['u:'.$guillaume->getKey()]], Context::ui($mathilde));
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($guillaume));

        // L'équipe transmet : Mathilde, interne, cesse d'être destinataire ; Guillaume et le Support le sont.
        Http::fake(['master.test/*' => fn (Request $request) => Http::response(['kind' => 'ticket', 'ticket' => $request['ticket'], 'entries' => [], 'transitions' => [], 'recipients' => $request['recipients']], 201)]);
        $this->workflow()->run($ticket->refresh(), 'transmit', [], Context::ui($guillaume));
        $this->assertEquals(['Guillaume' => 'primary', 'Support (TMA)' => 'primary'], $this->roles($ticket->refresh()));
        Notification::fake();
        $known = Notice::query()->for($mathilde)->count(); // ce qu'elle a appris tant que la demande restait chez EDM

        // Le Support pose une question, puis relance : c'est Guillaume qui l'apprend, jamais Mathilde.
        Passport::actingAs($gateway, [], 'api');
        $charles = ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'];
        $snapshot = Payload::snapshot($ticket);
        $snapshot['ticket']['state'] = 'awaiting_requester';
        $snapshot['transitions'][] = ['ulid' => strtolower((string) Str::ulid()), 'move' => 'request-answer', 'from_state' => 'in_progress', 'to_state' => 'awaiting_requester',
            'actor' => $charles, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui'];
        $snapshot['entries'][] = ['ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>Relance : ce ticket attend votre retour.</p>', 'meta' => ['reminder' => true],
            'author' => $charles, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui', 'created_at' => now()->toIso8601String()];
        $snapshot['recipients'][] = ['actor' => $charles, 'role' => 'primary', 'support' => false];
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        Notification::assertSentTo($guillaume, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Turn
            && str_contains($mail->notice->data['line'], 'attend votre réponse'));
        Notification::assertSentTo($guillaume, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Reminder);
        Notification::assertNothingSentTo($mathilde);
        $this->assertSame($known, Notice::query()->for($mathilde)->count());

        // Elle n'y écrit plus et n'y fait aucun passage ; la demande attend Guillaume, pas elle.
        $ticket->refresh();
        $this->assertFalse(app(Timeline::class)->canAdd($ticket, EntryType::Comment, Context::ui($mathilde)));
        $this->assertFalse($this->workflow()->allows($ticket, 'answer', Context::ui($mathilde)));
        $this->assertTrue(Ticket::query()->awaiting($guillaume)->whereKey($ticket->getKey())->exists());
        $this->assertFalse(Ticket::query()->awaiting($mathilde)->whereKey($ticket->getKey())->exists());

        // Remise dans la liste par erreur, elle ne serait toujours pas prévenue.
        $this->assertSame([], app(Recipients::class)->local($ticket)->filter(fn (array $r): bool => $r['user']->is($mathilde))->all());
    }
}
