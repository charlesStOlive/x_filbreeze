<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Events\TaskReceived;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\CreateProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\ListProjects;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\ViewProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\EditTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ListTasks;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\States\Task\TaskDone;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** Les tâches projet (CDC §8, lot 2) : créées dans le maître, invisibles en brouillon, publiées chez le client. */
class ProjectTaskTest extends TmaTestCase
{
    private Instance $desapp;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();
        $this->desapp = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        $this->project = Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID]);
    }

    /** Desapp répond : ses utilisateurs (Ada, destinataire possible), et la réception des tâches. */
    private function fakeDesapp(): void
    {
        Http::fake([
            'desapp.test/api/tma/v1/users*' => Http::response(['data' => [['id' => '7', 'name' => 'Ada Lovelace', 'email' => 'ada@client.test', 'service_desk' => true]]]),
            'desapp.test/*' => Http::response(['missing_media' => []]),
        ]);
    }

    private function tasks(): TaskWorkflow
    {
        return app(TaskWorkflow::class);
    }

    private function agentForTasks(string ...$more): User
    {
        return User::factory()->withPermissions('panels.admin.access', 'tma.task.*', ...$more)->create();
    }

    private function draftTask(User $agent, array $attributes = []): ProjectTask
    {
        return $this->tasks()->create(['title' => 'Refonte de la page parrain', 'project_id' => $this->project->getKey(), ...$attributes], Context::ui($agent));
    }

    /** @param  array<string, mixed>  $attributes */
    private function publishedTask(User $agent, array $attributes = []): ProjectTask
    {
        $task = $this->draftTask($agent, $attributes);
        $this->tasks()->run($task, 'publish', [], Context::ui($agent));

        return $task->refresh();
    }

    public function test_a_draft_stays_in_the_master_and_publishing_sends_it(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent);

        $this->assertSame('T-0001', $task->number);
        $this->assertFalse($task->isShared());
        // Le client n'est pas encore une partie : un brouillon ne le regarde pas.
        $this->assertSame([], $task->sidesOf(self::CLIENT_UID, false));

        // Rien ne s'écrit avant la publication : la description est le premier message.
        $this->assertFalse(app(Timeline::class)->canAdd($task, EntryType::Comment, Context::ui($agent)));
        $this->actingAs($agent);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])->assertDontSee('Nouveau message');
        Http::assertNothingSent();

        $this->tasks()->run($task, 'publish', [], Context::ui($agent));

        $this->assertInstanceOf(TaskInProgress::class, $task->state);
        $this->assertNotNull($task->published_at);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://desapp.test/api/tma/v1/sync'
            && $request['kind'] === 'task'
            && $request['task']['number'] === 'T-0001'
            && $request['task']['state'] === 'in_progress'
            && count($request['entries']) === 0);
    }

    public function test_a_scheduled_task_publishes_itself(): void
    {
        $this->fakeDesapp();
        $at = now()->addHour()->startOfMinute();
        $task = $this->draftTask($this->agentForTasks(), ['publish_at' => $at]);

        $this->artisan('tma:publish-scheduled')->expectsOutputToContain('0 tâche');
        $this->travel(2)->hours();
        $this->artisan('tma:publish-scheduled')->expectsOutputToContain('1 tâche');

        $this->assertInstanceOf(TaskInProgress::class, $task->refresh()->state);
        $this->assertSame('auto-publish', $task->transitions()->sole()->move);
        $this->assertNull($task->transitions()->sole()->actor_id);

        // Publiée à l'heure dite, pas au passage du planificateur : sa date, son passage, sa dernière activité.
        $this->assertTrue($task->published_at->equalTo($at));
        $this->assertTrue($task->transitions()->sole()->created_at->equalTo($at));
        $this->assertTrue($task->last_activity_at->equalTo($at));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync') && Carbon::parse($request['task']['published_at'])->equalTo($at));
    }

    public function test_moves_need_their_permission_and_are_for_the_master_only(): void
    {
        $this->fakeDesapp();
        $task = $this->draftTask($this->agentForTasks());
        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create();

        $this->assertFalse($this->tasks()->allows($task, 'publish', Context::ui($viewer)));
        $this->assertFalse($this->tasks()->allows($task, 'auto-publish', Context::ui($this->agentForTasks())));

        $publisher = User::factory()->withPermissions('panels.admin.access', 'tma.task.view', 'tma.task.workflow.publish')->create();
        $this->tasks()->run($task, 'publish', [], Context::ui($publisher));
        $this->assertFalse($this->tasks()->allows($task, 'complete', Context::ui($publisher)));

        $this->tasks()->run($task, 'complete', [], Context::ui($this->agentForTasks()));
        $this->assertInstanceOf(TaskDone::class, $task->state);

        $this->asClient();
        $this->assertFalse($this->tasks()->allows($task->forceFill(['state' => 'in_progress']), 'complete', Context::ui($this->agentForTasks())));
    }

    public function test_time_on_a_task_is_split_by_month(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent);

        app(TimeLog::class)->add($task, 420, '2026-09-30', Context::ui($agent));
        app(TimeLog::class)->add($task, 210, '2026-10-01', Context::ui($agent));

        $this->assertSame(['total_minutes' => 630, 'by_month' => ['2026-09' => 420, '2026-10' => 210]], Payload::task($task)['time']);
    }

    public function test_the_client_receives_a_published_task_and_never_a_draft(): void
    {
        Event::fake([TaskReceived::class]);
        $task = $this->draftTask($this->agentForTasks());
        $task->forceFill(['state' => 'in_progress', 'published_at' => now()])->save();
        $snapshot = Payload::snapshot($task);
        $task->forceDelete();

        $this->asClient();
        $master = User::factory()->create(['email' => 'filbreeze@api.test']);
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        Passport::actingAs($master, [], 'api');

        $this->postJson('/api/tma/v1/sync', [...$snapshot, 'task' => [...$snapshot['task'], 'state' => 'draft']])->assertUnprocessable();
        $this->postJson('/api/tma/v1/sync', [...$snapshot, 'task' => [...$snapshot['task'], 'target_instance_uid' => '01JTESTOTHER00000000000000']])->assertUnprocessable();

        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        $received = ProjectTask::query()->sole();
        $this->assertSame('T-0001', $received->number);
        $this->assertSame([Side::Requester], $received->sidesHere());
        $this->assertFalse($received->fieldsEditableHere());
        Event::assertDispatchedTimes(TaskReceived::class, 1);
    }

    public function test_the_client_comments_a_task_through_the_master(): void
    {
        $task = $this->draftTask($this->agentForTasks());
        $task->forceFill(['state' => 'in_progress'])->save();

        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton']);
        // Un correspondant du Support (le droit de lui écrire) ; un interne n'écrit pas sur une tâche.
        $member = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.comment', 'tma.ticket.workflow.transmit')->create();

        Http::fake(['master.test/*' => Http::response(['message' => 'indisponible'], 503)]);
        try {
            app(Timeline::class)->add($task, EntryType::Comment, '<p>On peut décaler d’une semaine ?</p>', Context::ui($member));
            $this->fail('Le commentaire aurait dû échouer.');
        } catch (SyncException) {
            $this->assertSame(0, Entry::query()->count());
        }
    }

    public function test_the_master_takes_entries_only_from_the_target_application(): void
    {
        $task = $this->draftTask($this->agentForTasks());
        $service = User::factory()->create();
        $this->desapp->update(['api_user_id' => $service->getKey()]);
        Passport::actingAs($service, [], 'api');

        $entry = [
            'ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>Bien reçu.</p>',
            'author' => ['instance_uid' => self::CLIENT_UID, 'id' => '3', 'name' => 'Bob'], 'origin_instance_uid' => self::CLIENT_UID,
        ];

        // Brouillon : la tâche n'existe pas pour le client.
        $this->postJson("/api/tma/v1/tasks/{$task->ulid}/entries", $entry)->assertNotFound();

        $task->forceFill(['state' => 'in_progress'])->save();
        $this->postJson("/api/tma/v1/tasks/{$task->ulid}/entries", $entry)->assertCreated();
        $this->assertSame('Bob', $task->entries()->sole()->author->name);

        $other = User::factory()->create();
        Instance::create(['uid' => '01JTESTOTHER00000000000000', 'name' => 'EDM', 'url' => 'https://edm.test', 'api_user_id' => $other->getKey()]);
        Passport::actingAs($other, [], 'api');
        $this->getJson("/api/tma/v1/tasks/{$task->ulid}")->assertNotFound();
    }

    public function test_the_start_defaults_to_the_creation_day_and_the_deadline_is_optional(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $this->actingAs($agent);

        // Sans début, une échéance seule passe ; le début est le jour de la création. L'échéance est à la demande.
        Livewire::test(CreateTask::class)
            ->assertFormFieldHidden('due_on')
            ->callAction('toggleOnDemand')
            ->fillForm(['title' => 'Export comptable', 'project_id' => $this->project->getKey(), 'due_on' => now()->addWeek()->toDateString(), 'primary_recipients' => ['c:7']])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = ProjectTask::query()->sole();
        $this->assertTrue($task->starts_on->isToday());
        $this->assertTrue($task->due_on->isSameDay(now()->addWeek()));

        // L'échéance ne précède pas le début (saisi, ou le jour de la création).
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Trop tôt', 'project_id' => $this->project->getKey(), 'due_on' => now()->subDay()->toDateString()])
            ->call('create')
            ->assertHasFormErrors(['due_on' => 'after_or_equal']);

        // Rien de saisi : rien d'obligatoire.
        $bare = $this->draftTask($agent);
        $this->assertTrue($bare->starts_on->isToday());
        $this->assertNull($bare->due_on);

        // La vraie fin : le jour où elle est terminée.
        $this->fakeDesapp();
        $this->tasks()->run($bare, 'publish', [], Context::ui($agent));
        $this->tasks()->run($bare, 'complete', [], Context::ui($agent));
        Livewire::test(ViewTask::class, ['record' => $bare->getKey()])->assertSee('Terminée le');
    }

    public function test_the_task_screens(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $this->actingAs($agent);

        // Sans date de publication, la tâche part chez le client dès l'enregistrement.
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Export comptable', 'project_id' => $this->project->getKey(), 'primary_recipients' => ['c:7']])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Publiée chez Desapp');

        $task = ProjectTask::query()->sole();
        $this->assertInstanceOf(TaskInProgress::class, $task->state);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync') && $request['task']['title'] === 'Export comptable');

        // Avec une date, elle attend en brouillon ; « Publier chez… » la publie sans attendre.
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Atelier de lancement', 'project_id' => $this->project->getKey(), 'publish_at' => now()->addDay()->format('Y-m-d H:i'), 'primary_recipients' => ['c:7']])
            ->call('create')
            ->assertHasNoFormErrors();
        $draft = ProjectTask::query()->where('title', 'Atelier de lancement')->sole();
        $this->assertSame('draft', $draft->state::getMorphClass());

        Livewire::test(ViewTask::class, ['record' => $draft->getKey()])
            ->assertSee('publication prévue le')
            ->assertActionVisible('task_publish')
            ->assertActionHidden('task_complete')
            ->callAction('task_publish')
            ->assertActionVisible('task_complete');

        $this->assertInstanceOf(TaskInProgress::class, $draft->refresh()->state);

        // Des notes, comme sur un ticket : sur la tâche entière ou sur un message, lues de tous ou gardées pour soi.
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Note sur la tâche')
            ->callAction(TestAction::make('createNote'), ['body' => '<p>Le client valide le périmètre lundi.</p>'])
            ->assertNotified('Note n° 1 ajoutée')
            ->callAction(TestAction::make('createNote'), ['body' => '<p>Prévoir une marge.</p>', 'private' => true])
            ->assertNotified('Note n° 2 ajoutée')
            ->assertSee(['Le client valide le périmètre lundi.', 'Visible de vous seul']);
        $this->assertSame([false, true], $task->entries()->notes()->orderBy('number')->pluck('private')->all());

        // Dans une application cliente, on ne crée pas de tâche.
        $this->asClient();
        $this->assertFalse(TaskResource::canCreate());
    }

    public function test_tasks_are_grouped_in_projects_whose_time_is_the_sum_of_their_tasks(): void
    {
        $agent = $this->agentForTasks('tma.project.*');
        $first = $this->draftTask($agent);
        $second = $this->draftTask($agent, ['title' => 'Espace parrain']);
        $compta = Project::create(['title' => 'Compta', 'target_instance_uid' => self::CLIENT_UID]);
        $elsewhere = $this->tasks()->create(['title' => 'Export', 'project_id' => $compta->getKey()], Context::ui($agent));
        app(TimeLog::class)->add($first, 420, '2026-09-30', Context::ui($agent));
        app(TimeLog::class)->add($second, 60, '2026-10-01', Context::ui($agent));
        app(TimeLog::class)->add($elsewhere, 30, '2026-10-01', Context::ui($agent));

        // L'application d'une tâche est celle de son projet ; le temps du projet, la somme de celui de ses tâches.
        $this->assertSame(self::CLIENT_UID, $first->target_instance_uid);
        $this->assertSame(['total_minutes' => 480, 'by_month' => ['2026-09' => 420, '2026-10' => 60]], $this->project->timeTotals());

        $this->actingAs($agent);
        Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$this->project, $compta])->assertSee(['8 h', '0 / 2 terminées']);
        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])->assertSee(['Portail parrain', '8 h']);
        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $this->project, 'pageClass' => ViewProject::class])
            ->assertCanSeeTableRecords([$first, $second])
            ->assertCanNotSeeTableRecords([$elsewhere]);
        Livewire::test(ListTasks::class)->assertSee(['Portail parrain', 'Compta']);

        Livewire::test(CreateProject::class)
            ->fillForm(['title' => 'Refonte', 'target_instance_uid' => self::CLIENT_UID])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(self::CLIENT_UID, Project::query()->where('title', 'Refonte')->sole()->target_instance_uid);

        // Un projet qui a des tâches ne se supprime pas ; le client ne crée pas de projet.
        $this->assertFalse(ProjectResource::canDelete($this->project));
        $this->asClient();
        $this->assertFalse(ProjectResource::canCreate());
    }

    public function test_the_project_travels_with_its_tasks(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent);
        $this->tasks()->run($task, 'publish', [], Context::ui($agent));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync') && $request['task']['project']['title'] === 'Portail parrain');

        // Un nouveau titre repart avec les tâches publiées du projet.
        $this->project->update(['title' => 'Portail des parrains']);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync') && $request['task']['project']['title'] === 'Portail des parrains');

        // Chez le client : le projet issu de l'ancien libellé prend l'identité de celui du maître, sans doublon.
        $snapshot = Payload::snapshot($task->refresh());
        $task->forceDelete();
        $this->project->forceDelete();
        $this->asClient();
        $migrated = Project::create(['title' => 'Portail des parrains', 'target_instance_uid' => self::CLIENT_UID]);

        $received = Mirror::task($snapshot['task']);

        $this->assertTrue($received->project->is($migrated));
        $this->assertSame($snapshot['task']['project']['ulid'], $migrated->refresh()->ulid);
        $this->assertSame(1, Project::query()->count());
    }

    public function test_the_support_edits_a_task_anytime_and_the_client_still_writes_on_a_done_task(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent);
        $this->tasks()->run($task, 'publish', [], Context::ui($agent));
        $this->tasks()->run($task, 'complete', [], Context::ui($agent));

        // Terminée, la tâche se modifie encore chez le Support, et part chez le client.
        $this->actingAs($agent);
        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->fillForm(['title' => 'Refonte de l’espace parrain', 'description' => '<p>Avec la FAQ.</p>'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Refonte de l’espace parrain', $task->refresh()->title);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync') && $request['task']['title'] === 'Refonte de l’espace parrain');

        // Le client écrit encore sur une tâche terminée (pas sur une tâche annulée), sans pouvoir la modifier.
        $this->asClient();
        $member = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.comment', 'tma.task.note', 'tma.ticket.workflow.transmit')->create();
        $internal = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.comment', 'tma.task.note')->create();
        $this->actingAs($member);
        $this->assertFalse(TaskResource::canEdit($task));
        // Chez le client, le temps ferme le volet, en lecture ; le calendrier ne s'affiche qu'à la demande.
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSee(['Nouveau message', 'Projet', 'Tout le projet'])
            ->assertSeeInOrder(['Suivi', 'Dernière activité', 'Temps passé', 'Tout le projet'])
            ->assertDontSee(['En attente de', 'Calendrier', 'Saisir du temps'])
            ->callAction('toggleOnDemand')
            ->assertSee(['Calendrier', 'Échéance prévue']);
        $this->assertTrue(app(Timeline::class)->canAdd($task, EntryType::Comment, Context::ui($member)));
        $this->assertFalse(app(Timeline::class)->canAdd($task, EntryType::Comment, Context::ui($internal)));
        $task->forceFill(['state' => 'cancelled']);
        $this->assertFalse(app(Timeline::class)->canAdd($task, EntryType::Comment, Context::ui($member)));
        $this->assertTrue(app(Timeline::class)->canAdd($task, EntryType::Note, Context::ui($member)));
    }

    public function test_a_ticket_starts_from_a_task_and_stays_linked_to_it(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent, ['description' => '<p>La page parrain.</p>']);
        $this->tasks()->run($task, 'publish', [], Context::ui($agent));

        // Chez le client, la demande part de la tâche, préremplie.
        $this->asClient();
        $member = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.ticket.*')->create();
        $this->actingAs($member);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])->assertActionVisible('createTicket');
        Livewire::withQueryParams(['task' => $task->getKey()])->test(CreateTicket::class)
            ->assertSee('À partir de la tâche T-0001')
            ->assertSchemaStateSet(['title' => 'Refonte de la page parrain'])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->sole();
        $this->assertTrue($ticket->task->is($task));
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])->assertSee([$ticket->number, 'Tickets']);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertSee(['À partir de la tâche', 'T-0001']);

        // Le lien voyage avec le ticket.
        $payload = Payload::ticket($ticket);
        $this->assertSame($task->ulid, $payload['task_ulid']);
        $ticket->forceDelete();
        $this->assertTrue(Mirror::ticket($payload)->task->is($task));
    }

    public function test_time_can_bear_on_a_message_or_a_note(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->publishedTask($agent);
        $message = app(Timeline::class)->add($task, EntryType::Comment, '<p>Le client veut une FAQ.</p>', Context::ui($agent));

        $time = app(TimeLog::class)->add($task, 90, now(), Context::ui($agent), entry: $message);
        $this->assertTrue($time->entry->is($message));

        // Pas sur le message d'une autre fiche.
        $other = $this->publishedTask($agent, ['title' => 'Autre']);
        $foreign = app(Timeline::class)->add($other, EntryType::Comment, '<p>Ailleurs.</p>', Context::ui($agent));
        try {
            app(TimeLog::class)->add($task, 30, now(), Context::ui($agent), entry: $foreign);
            $this->fail('Le temps porte sur un message de la même fiche.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        // Avec son message, ou pas du tout : une durée refusée n'écrit rien.
        $before = $task->entries()->count();
        try {
            app(TimeLog::class)->addWithMessage($task, 0, now(), '<p>Rien.</p>', Context::ui($agent));
            $this->fail('Une saisie va d’une minute à 24 heures.');
        } catch (ValidationException) {
            $this->assertSame($before, $task->entries()->count());
        }

        // Depuis le volet, sur une note ; l'historique montre le temps sur le message.
        $this->actingAs($agent);
        $note = app(Timeline::class)->add($task, EntryType::Note, '<p>Prévoir la FAQ.</p>', Context::ui($agent));
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class])
            ->callTableAction('logTime', data: ['worked_on' => now()->toDateString(), 'duration' => 1, 'unit' => 'hours', 'billable' => true, 'entry_id' => $note->getKey()])
            ->assertHasNoTableActionErrors()
            ->assertSee('Sur : Note n° 1');
        $this->assertSame(60, (int) $note->timeEntries()->sum('minutes'));
        $this->assertSame($before + 1, $task->entries()->count());
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])->assertSee('1 h 30');
    }

    public function test_optional_details_show_on_demand(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent, ['due_on' => now()->addMonth()->toDateString()]);
        $this->actingAs($agent);

        // Dans le maître, le temps vient en tête ; l'application et le calendrier, à la demande.
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSeeLivewire(TimeEntriesRelationManager::class)
            ->assertDontSee(['Calendrier', 'Desapp'])
            ->assertActionExists('toggleOnDemand')
            ->callAction('toggleOnDemand')
            ->assertSee(['Calendrier', 'Desapp'])
            ->assertSet('showOnDemand', true);

        // Le choix reste pour l'utilisateur : la modification montre aussi l'échéance ; caché, il la laisse telle quelle.
        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->assertFormFieldVisible('due_on')
            ->callAction('toggleOnDemand')
            ->assertFormFieldHidden('due_on')
            ->fillForm(['title' => 'Refonte de la page parrain (v2)', 'primary_recipients' => ['c:7']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Refonte de la page parrain (v2)', $task->refresh()->title);
        $this->assertTrue($task->due_on->isSameDay(now()->addMonth()));

        // À la création aussi, l'échéance est à la demande ; le bouton n'est qu'une icône, qui s'explique au survol.
        Livewire::test(CreateTask::class)
            ->assertFormFieldHidden('due_on')
            ->assertActionHasIcon('toggleOnDemand', 'heroicon-m-eye')
            ->tap(function (Testable $page): void {
                $button = $page->instance()->getAction('toggleOnDemand');
                $this->assertTrue($button->isIconButton());
                $this->assertSame('Tout afficher : montrer aussi les informations facultatives, masquées pour alléger la page.', $button->getTooltip());
            })
            ->callAction('toggleOnDemand')
            ->assertFormFieldVisible('due_on');
    }

    public function test_time_is_one_compact_block(): void
    {
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent);
        $other = $this->draftTask($agent, ['title' => 'Espace parrain']);
        app(TimeLog::class)->add($task, 121, '2026-10-09', Context::ui($agent));
        app(TimeLog::class)->add($other, 240, '2026-10-14', Context::ui($agent), billable: false);

        // Les totaux en tête, puis une ligne par saisie : qui, le jour et le mois, combien ; les boutons en icônes.
        $this->actingAs($agent);
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class])
            ->assertSee(['Cette tâche : 2 h 01 · Tout le projet : 6 h 01', '09/10', 'Saisir du temps'])
            ->assertDontSee(['09/10/2026', 'Trier par'])
            ->assertTableActionVisible('edit', TimeEntry::query()->where('minutes', 121)->sole());
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $other, 'pageClass' => ViewTask::class])
            ->assertSee(['Cette tâche : 4 h · Tout le projet : 6 h 01', 'Non facturable']);

        // Sans le droit de saisir : les totaux seuls, en lecture.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create());
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertDontSeeLivewire(TimeEntriesRelationManager::class)
            ->assertSee('Cette tâche : 2 h 01 · Tout le projet : 6 h 01')
            ->assertDontSee('Saisir du temps');
    }

    public function test_dates_show_in_the_readers_timezone(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $agent->forceFill(['timezone' => 'Europe/Paris'])->save();
        $this->travelTo(Carbon::parse('2026-10-14 22:06:00', 'UTC'));
        $task = $this->draftTask($agent, ['description' => '<p>Le périmètre du lot 2.</p>']);
        $this->tasks()->run($task, 'publish', [], Context::ui($agent));
        app(Timeline::class)->add($task, EntryType::Comment, '<p>Bonjour, voici le lot 2.</p>', Context::ui($agent));

        // Enregistrées en UTC (22:06), affichées à l'heure de Paris : la tâche, son passage, le message.
        $this->actingAs($agent->refresh());
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSeeInOrder([$agent->name, '15/10/2026 00:06', 'Le périmètre du lot 2.', 'Bonjour, voici le lot 2.'])
            ->assertDontSee('14/10/2026 22:06');
    }

    public function test_the_demo_set_of_the_support_starts_from_scratch(): void
    {
        Http::fake([
            'desapp.test/api/tma/v1/users*' => Http::response(['data' => [['id' => '5', 'name' => 'Léo Labarbe', 'email' => 'leo@client.test', 'service_desk' => true]]]),
            'desapp.test/*' => Http::response(['missing_media' => []]),
        ]);
        $agent = User::factory()->withPermissions('panels.admin.access', 'tma.*')->create();
        $old = $this->draftTask($agent);
        app(TimeLog::class)->add($old, 60, now(), Context::ui($agent));

        $this->artisan('tma:demo', ['--fresh' => true, '--force' => true])->assertSuccessful();

        // L'ancien est parti ; deux projets et leurs tâches dans tous les états.
        $this->assertNull(ProjectTask::query()->find($old->getKey()));
        $this->assertSame(['Export comptable', 'Portail parrain'], Project::query()->orderBy('title')->pluck('title')->all());
        $states = ProjectTask::query()->orderBy('number')->get()->mapWithKeys(fn (ProjectTask $t): array => [$t->title => $t->state::getMorphClass()])->all();
        $this->assertSame('done', $states['Cadrage du nouveau portail']);
        $this->assertSame('in_progress', $states['Maquettes de l’espace parrain']);
        $this->assertSame('draft', $states['Recette avec les parrains testeurs']);
        $this->assertSame('cancelled', $states['Reprise de l’ancien export CSV']);
        $late = ProjectTask::query()->where('title', 'Export des dons vers la comptabilité')->sole();
        $this->assertTrue($late->due_on->isPast());
        $this->assertTrue($late->published_at->lt(now()->subDays(5)));
        $this->assertSame(11 * 60, Project::query()->where('title', 'Portail parrain')->sole()->timeTotals()['total_minutes']);
        $this->assertSame(3, TimeEntry::query()->whereNotNull('entry_id')->count());

        // Deux tickets préparés pour le client : l'un publié et pris en charge, l'autre programmé.
        $this->assertSame(['in_progress', 'draft'], Ticket::query()->orderBy('id')->get()->map(fn (Ticket $t): string => $t->state::getMorphClass())->all());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync') && ($request['task']['title'] ?? null) === 'Maquettes de l’espace parrain');

        // Vider seulement.
        $this->artisan('tma:demo', ['--wipe' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(0, ProjectTask::query()->count() + Project::query()->count() + Ticket::query()->count() + TimeEntry::query()->count());
    }

    public function test_a_task_opens_with_its_publication_and_closes_with_its_last_word(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->publishedTask($agent, ['description' => '<p>Le périmètre du lot 1.</p>']);
        app(Timeline::class)->add($task, EntryType::Comment, '<p>Les maquettes arrivent.</p>', Context::ui($agent));
        $done = $this->tasks()->run($task, 'complete', ['message' => '<p>Livré : le lot 1 est en ligne.</p>'], Context::ui($agent));

        $this->assertSame($done->ulid, $task->entries()->where('body', '<p>Livré : le lot 1 est en ligne.</p>')->sole()->meta['transition']);
        $this->actingAs($agent);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSeeInOrder(['Publier chez le client', 'Le périmètre du lot 1.', 'Les maquettes arrivent.', 'Terminer', 'Livré : le lot 1 est en ligne.'])
            ->tap(fn (Testable $page) => $this->assertSame(0, substr_count($page->html(), 'class="fi-tma-timeline-event"')));
    }

    public function test_the_support_reopens_a_task_on_the_master_or_from_the_client(): void
    {
        $this->fakeDesapp();
        $agent = $this->agentForTasks();
        $task = $this->publishedTask($agent);
        $this->tasks()->run($task, 'complete', [], Context::ui($agent));

        // Le droit `tma.task.workflow.reopen` : sans lui, pas de réouverture.
        $reader = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create();
        $this->assertFalse($this->tasks()->allows($task->refresh(), 'reopen', Context::ui($reader)));

        $this->actingAs($agent);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionVisible('task_reopen')
            ->callAction('task_reopen', ['reason' => 'Un oubli dans la livraison.'])
            ->assertHasNoActionErrors();

        $task->refresh();
        $this->assertInstanceOf(TaskInProgress::class, $task->state);
        $this->assertNull($task->done_at);
        $this->assertSame('Un oubli dans la livraison.', $task->transitions()->latest('id')->first()->reason);

        // Annulée, elle se rouvre aussi.
        $this->tasks()->run($task, 'cancel', ['reason' => 'Reportée.'], Context::ui($agent));
        $this->assertTrue($this->tasks()->allows($task->refresh(), 'reopen', Context::ui($agent)));

        // Depuis l'application cliente, un membre du Support : le maître rouvre, l'état revient.
        $snapshot = Payload::snapshot($task);
        $task->recipients()->delete();
        $task->transitions()->delete();
        $task->forceDelete();

        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton-instance']);
        Mirror::snapshot($snapshot);
        $copy = ProjectTask::query()->where('ulid', $snapshot['task']['ulid'])->sole();
        $charles = User::factory()->withPermissions('panels.admin.access', 'tma.*')->create(['email' => 'charles@notilac.fr']);
        Agent::create(['user_id' => $charles->getKey(), 'email' => $charles->email, 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'jeton-agent', 'linked_at' => now()]);
        $client = User::factory()->withPermissions('panels.admin.access', 'tma.*')->create();

        $this->assertFalse($this->tasks()->allows($copy, 'reopen', Context::ui($client)));
        $this->assertTrue($this->tasks()->allows($copy, 'reopen', Context::ui($charles)));

        Http::fake(['master.test/api/tma/v1/agent/tickets/*/moves' => function (Request $request) use ($snapshot) {
            $snapshot['task']['state'] = 'in_progress';
            $snapshot['task']['version']++;
            $snapshot['transitions'][] = ['ulid' => strtolower((string) Str::ulid()), 'move' => $request['move'], 'from_state' => 'cancelled', 'to_state' => 'in_progress',
                'actor' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'api'];

            return Http::response(['data' => ['snapshot' => $snapshot]]);
        }]);

        $this->tasks()->run($copy, 'reopen', [], Context::ui($charles));
        $this->assertSame('in_progress', $copy->refresh()->state::getMorphClass());
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-agent') && $request['move'] === 'reopen');
    }
}
