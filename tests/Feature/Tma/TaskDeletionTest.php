<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ListTasks;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/** Supprimer une tâche, dans le maître seulement, quel que soit son état ; publiée, elle disparaît aussi chez le client. */
class TaskDeletionTest extends TmaTestCase
{
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        $this->project = Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID]);
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
    }

    private function taskAgent(string ...$more): User
    {
        return User::factory()->withPermissions('panels.admin.access', 'tma.task.*', ...$more)->create();
    }

    private function task(User $agent, bool $published = true, string $title = 'Refonte de la page parrain'): ProjectTask
    {
        $task = app(TaskWorkflow::class)->create(['title' => $title, 'description' => '<p>Le <strong>détail</strong> de la tâche.</p>', 'project_id' => $this->project->getKey()], Context::ui($agent));

        if ($published) {
            app(TaskWorkflow::class)->run($task, 'publish', [], Context::ui($agent));
        }

        return $task->refresh();
    }

    public function test_a_published_task_goes_with_its_messages_notes_and_time_and_leaves_the_client_too(): void
    {
        $agent = $this->taskAgent();
        $task = $this->task($agent);
        $message = app(Timeline::class)->add($task, EntryType::Comment, '<p>Les maquettes arrivent.</p>', Context::ui($agent));
        app(Timeline::class)->add($task, EntryType::Note, '<p>Penser au contraste.</p>', Context::ui($agent));
        app(TimeLog::class)->add($task, 90, now(), Context::ui($agent), entry: $message);
        app(TaskWorkflow::class)->run($task, 'complete', [], Context::ui($agent));

        // Quel que soit son état (ici terminée), avec un avertissement qui dit tout ce qui part.
        $this->actingAs($agent);
        Livewire::test(ListTasks::class)
            ->mountAction(TestAction::make('delete')->table($task))
            ->assertMountedActionModalSee([
                'Supprimer la tâche T-0001 ?',
                'Partent avec elle : 1 message, 1 note et 1 h 30 de temps saisi.',
                'Elle est publiée : elle disparaît aussi chez EDM.',
                'La suppression ne se défait pas.',
            ])
            ->callMountedAction()
            ->assertNotified('Tâche T-0001 supprimée');

        $this->assertSoftDeleted($task);
        $this->assertSame(0, Entry::query()->count());
        $this->assertSame(2, Entry::withTrashed()->count());
        $this->assertSame(0, TimeEntry::query()->count());
        $this->assertSame(0, $this->project->timeTotals()['total_minutes']);

        // Le client reçoit la tâche supprimée, avec ses entrées.
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync')
            && filled($request['task']['deleted_at'] ?? null)
            && collect($request['entries'])->every(fn (array $entry): bool => filled($entry['deleted_at'])));

        // Le numéro n'est jamais redonné.
        $this->assertSame('T-0002', $this->task($agent, title: 'Une autre')->number);
    }

    public function test_the_client_drops_a_task_deleted_in_the_master(): void
    {
        $agent = $this->taskAgent();
        $task = $this->task($agent);
        app(Timeline::class)->add($task, EntryType::Comment, '<p>Bonjour.</p>', Context::ui($agent));
        $published = Payload::snapshot($task->refresh());
        app(TaskWorkflow::class)->delete($task, Context::ui($agent));
        $deleted = Payload::snapshot($task->refresh());
        $task->entries()->withTrashed()->forceDelete();
        $task->forceDelete();

        $this->asClient();
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        Passport::actingAs(User::factory()->create(['email' => 'filbreeze@api.test']), [], 'api');

        $this->postJson('/api/tma/v1/sync', $published)->assertOk();
        $this->assertSame(1, ProjectTask::query()->count());

        $this->postJson('/api/tma/v1/sync', $deleted)->assertOk();
        $this->assertSame(0, ProjectTask::query()->count());
        $this->assertSame(0, Entry::query()->count());

        // Une tâche supprimée avant d'être reçue n'est pas créée.
        ProjectTask::withTrashed()->forceDelete();
        $this->postJson('/api/tma/v1/sync', $deleted)->assertOk();
        $this->assertSame(0, ProjectTask::withTrashed()->count());
    }

    public function test_several_tasks_at_once_from_the_list(): void
    {
        $agent = $this->taskAgent();
        $draft = $this->task($agent, published: false, title: 'Brouillon');
        $published = $this->task($agent, title: 'Publiée');
        $invoiced = $this->task($agent, title: 'Facturée');
        $time = app(TimeLog::class)->add($invoiced, 60, now(), Context::ui($agent));
        $time->forceFill(['invoice_type' => 'invoice', 'invoice_id' => 1])->saveQuietly();

        $this->actingAs($agent);
        Livewire::test(ListTasks::class)
            ->assertSee(['T-0001', 'Brouillon', 'Publiée', 'Facturée', 'Le détail de la tâche.'])
            ->selectTableRecords([$draft, $published, $invoiced])
            ->mountAction(TestAction::make('deleteTasks')->table()->bulk())
            ->assertMountedActionModalSee(['Supprimer les 3 tâches sélectionnées ?', '2 sont publiées : elles disparaissent aussi chez EDM.'])
            ->callMountedAction()
            ->assertNotified('2 tâches supprimées');

        $this->assertSoftDeleted($draft);
        $this->assertSoftDeleted($published);
        // Du temps facturé : la tâche reste.
        $this->assertNotSoftDeleted($invoiced);
    }

    public function test_the_task_page_deletes_too_and_goes_back_to_the_list(): void
    {
        $agent = $this->taskAgent();
        $task = $this->task($agent);

        $this->actingAs($agent);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->callAction('delete')
            ->assertRedirect(ListTasks::getUrl());
        $this->assertSoftDeleted($task);
    }

    public function test_only_in_the_master_and_with_the_right(): void
    {
        $agent = $this->taskAgent();
        $task = $this->task($agent);
        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create();

        $this->assertFalse(app(TaskWorkflow::class)->canDelete($task, Context::ui($viewer)));
        $this->actingAs($viewer);
        Livewire::test(ListTasks::class)->assertTableActionHidden('delete', $task);

        try {
            app(TaskWorkflow::class)->delete($task, Context::ui($viewer));
            $this->fail('Il faut le droit de supprimer.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted($task);
        }

        // Chez le client, jamais.
        $this->asClient();
        $this->assertFalse(app(TaskWorkflow::class)->canDelete($task, Context::ui($agent)));
        $this->actingAs($agent);
        Livewire::test(ListTasks::class)
            ->assertTableActionHidden('delete', $task)
            ->assertActionHidden('create');
    }

    public function test_invoiced_time_keeps_the_task(): void
    {
        $agent = $this->taskAgent();
        $task = $this->task($agent);
        $time = app(TimeLog::class)->add($task, 60, now(), Context::ui($agent));
        $time->forceFill(['invoice_type' => 'invoice', 'invoice_id' => 1])->saveQuietly();

        $this->expectException(ValidationException::class);
        app(TaskWorkflow::class)->delete($task, Context::ui($agent));
    }
}
