<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Events\TaskReceived;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskDone;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/** Les tâches projet (CDC §8, lot 2) : créées dans le maître, invisibles en brouillon, publiées chez le client. */
class ProjectTaskTest extends TmaTestCase
{
    private Instance $desapp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();
        $this->desapp = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
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
        return $this->tasks()->create(['title' => 'Refonte de la page parrain', 'target_instance_uid' => self::CLIENT_UID, 'project' => 'Portail parrain', ...$attributes], Context::ui($agent));
    }

    public function test_a_draft_stays_in_the_master_and_publishing_sends_it(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent);

        $this->assertSame('T-0001', $task->number);
        $this->assertFalse($task->isShared());
        // Le client n'est pas encore une partie : un brouillon ne le regarde pas.
        $this->assertSame([], $task->sidesOf(self::CLIENT_UID, false));

        app(Timeline::class)->add($task, EntryType::Comment, '<p>À cadrer avec le client.</p>', Context::ui($agent));
        Http::assertNothingSent();

        $this->tasks()->run($task, 'publish', [], Context::ui($agent));

        $this->assertInstanceOf(TaskInProgress::class, $task->state);
        $this->assertNotNull($task->published_at);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://desapp.test/api/tma/v1/sync'
            && $request['kind'] === 'task'
            && $request['task']['number'] === 'T-0001'
            && $request['task']['state'] === 'in_progress'
            && count($request['entries']) === 1);
    }

    public function test_a_scheduled_task_publishes_itself(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $task = $this->draftTask($this->agentForTasks(), ['publish_at' => now()->addHour()]);

        $this->artisan('tma:publish-scheduled')->expectsOutputToContain('0 tâche');
        $this->travel(2)->hours();
        $this->artisan('tma:publish-scheduled')->expectsOutputToContain('1 tâche');

        $this->assertInstanceOf(TaskInProgress::class, $task->refresh()->state);
        $this->assertSame('auto-publish', $task->transitions()->sole()->move);
        $this->assertNull($task->transitions()->sole()->actor_id);
    }

    public function test_moves_need_their_permission_and_are_for_the_master_only(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
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
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $agent = $this->agentForTasks();
        $task = $this->draftTask($agent);

        app(TimeLog::class)->add($task, 420, '2026-09-30', 'Atelier', Context::ui($agent));
        app(TimeLog::class)->add($task, 210, '2026-10-01', 'Maquettes', Context::ui($agent));

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
        $member = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.comment')->create();

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
        $agent = $this->agentForTasks();
        $this->actingAs($agent);

        // Sans début, une échéance seule passe ; le début est le jour de la création.
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Export comptable', 'target_instance_uid' => self::CLIENT_UID, 'due_on' => now()->addWeek()->toDateString()])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = ProjectTask::query()->sole();
        $this->assertTrue($task->starts_on->isToday());
        $this->assertTrue($task->due_on->isSameDay(now()->addWeek()));

        // L'échéance ne précède pas le début (saisi, ou le jour de la création).
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Trop tôt', 'target_instance_uid' => self::CLIENT_UID, 'due_on' => now()->subDay()->toDateString()])
            ->call('create')
            ->assertHasFormErrors(['due_on' => 'after_or_equal']);

        // Rien de saisi : rien d'obligatoire.
        $bare = $this->draftTask($agent);
        $this->assertTrue($bare->starts_on->isToday());
        $this->assertNull($bare->due_on);

        // La vraie fin : le jour où elle est terminée.
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $this->tasks()->run($bare, 'publish', [], Context::ui($agent));
        $this->tasks()->run($bare, 'complete', [], Context::ui($agent));
        Livewire::test(ViewTask::class, ['record' => $bare->getKey()])->assertSee('Terminée le');
    }

    public function test_the_task_screens(): void
    {
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $agent = $this->agentForTasks();
        $this->actingAs($agent);

        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Export comptable', 'target_instance_uid' => self::CLIENT_UID, 'project' => 'Compta'])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = ProjectTask::query()->sole();
        $this->assertSame('draft', $task->state::getMorphClass());

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionVisible('task_publish')
            ->assertActionHidden('task_complete')
            ->callAction('task_publish')
            ->assertActionVisible('task_complete');

        $this->assertInstanceOf(TaskInProgress::class, $task->refresh()->state);

        // Dans une application cliente, on ne crée pas de tâche.
        $this->asClient();
        $this->assertFalse(TaskResource::canCreate());
    }
}
