<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Widgets\OngoingTicketsWidget;
use CharlesStOlive\FilamentTma\Filament\Widgets\ProjectTasksWidget;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Filament\Actions\Testing\TestAction;
use Filament\Pages\Dashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** Le tableau de bord : les tickets en cours dont on est destinataire. */
class DashboardWidgetsTest extends TmaTestCase
{
    /** Un ticket du Support, dans le maître, dont `$user` est destinataire (ou non, sans rôle). */
    private function ticket(string $number, string $state, ?User $user = null, RecipientRole $role = RecipientRole::Primary, bool $contact = false): Ticket
    {
        $ticket = $this->serviceDeskTicket($state, $number);
        $ticket->forceFill(['title' => "Ticket {$number}", 'last_activity_at' => now()->subMinutes((int) substr($number, -2))])->save();

        if ($user !== null) {
            app(Recipients::class)->add($ticket, Actor::forUser($user), $role);
        }

        if ($contact) {
            $ticket->forceFill(['assignee_actor_id' => Actor::forUser($user)->getKey()])->save();
        }

        return $ticket->refresh();
    }

    public function test_ongoing_tickets_where_one_is_a_recipient_those_awaiting_first(): void
    {
        $this->asMaster();
        $charles = $this->supportMember();
        $this->actingAs($charles);

        $recent = $this->ticket('DES-0001', 'awaiting_requester', $charles);
        // En copie d'un ticket qu'un collègue traite.
        $copied = $this->ticket('DES-0002', 'in_progress', $charles, RecipientRole::Secondary);
        $copied->forceFill(['assignee_actor_id' => Actor::forUser($this->supportMember())->getKey()])->save();
        $mine = $this->ticket('DES-0003', 'in_progress', $charles, contact: true);
        $closed = $this->ticket('DES-0004', 'closed', $charles);
        $paused = $this->ticket('DES-0005', 'paused', $charles);
        $others = $this->ticket('DES-0006', 'in_progress');

        Livewire::test(OngoingTicketsWidget::class)
            ->assertSee('Mes tickets en cours')
            // Celui qui l'attend d'abord, puis les plus récemment actifs.
            ->assertCanSeeTableRecords([$mine, $recent, $copied], inOrder: true)
            ->assertCanNotSeeTableRecords([$closed, $paused, $others])
            ->assertTableColumnStateSet('waiting_on', 'Vous', $mine)
            ->assertTableColumnStateSet('waiting_on', 'Ada Lovelace', $recent)
            ->assertTableColumnStateSet('waiting_on', 'Support', $copied)
            ->assertTableColumnStateSet('recipient_role', RecipientRole::Primary, $mine)
            ->assertTableColumnStateSet('recipient_role', RecipientRole::Secondary, $copied)
            ->assertActionHasUrl(TestAction::make('open')->table($mine), TicketUrl::view($mine))
            ->assertActionVisible(TestAction::make('all')->table());
    }

    public function test_ten_at_most_and_the_count_says_so(): void
    {
        $this->asMaster();
        $charles = $this->supportMember();
        $this->actingAs($charles);

        foreach (range(10, 22) as $n) {
            $this->ticket("DES-00{$n}", 'in_progress', $charles);
        }

        $widget = Livewire::test(OngoingTicketsWidget::class)
            ->assertSee('Les 10 premiers sur 13');
        $this->assertCount(OngoingTicketsWidget::LIMIT, $widget->instance()->getTableRecords());
    }

    public function test_the_client_sees_what_waits_for_him_and_only_who_sees_tickets_has_the_widget(): void
    {
        $this->asClient();
        $leo = $this->correspondent();
        $this->actingAs($leo);
        $ticket = $this->draft($leo);
        $ticket->forceFill(['state' => 'awaiting_requester'])->save();

        Livewire::test(OngoingTicketsWidget::class)
            ->assertCanSeeTableRecords([$ticket])
            ->assertTableColumnStateSet('waiting_on', 'Vous', $ticket);

        // Sur le tableau de bord, pour qui voit les tickets.
        $this->get(Dashboard::getUrl())->assertOk()->assertSeeLivewire(OngoingTicketsWidget::class);

        $this->actingAs(User::factory()->withPermissions('panels.admin.access')->create());
        $this->assertFalse(OngoingTicketsWidget::canView());
        $this->get(Dashboard::getUrl())->assertOk()->assertDontSeeLivewire(OngoingTicketsWidget::class);
    }

    /** Une tâche publiée chez le client, dont `$user` est destinataire (ou non). */
    private function task(string $title, string $state = 'in_progress', ?User $user = null, RecipientRole $role = RecipientRole::Primary, array $attributes = []): ProjectTask
    {
        $this->project ??= Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID]);
        $task = ProjectTask::query()->create([
            'number' => 'T-'.str_pad((string) (ProjectTask::query()->withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'target_instance_uid' => self::CLIENT_UID, 'project_id' => $this->project->getKey(), 'title' => $title, 'state' => $state,
            'published_at' => $state === 'draft' ? null : now()->subMonths(3), 'last_activity_at' => now(), ...$attributes,
        ]);

        if ($user !== null) {
            app(Recipients::class)->add($task, Actor::forUser($user), $role);
        }

        return $task;
    }

    /** Maître : du temps saisi sur une tâche. */
    private function time(ProjectTask $task, int $minutes, Carbon $on): void
    {
        TimeEntry::query()->create([
            'ulid' => strtolower((string) Str::ulid()), 'subject_type' => $task->getMorphClass(), 'subject_id' => $task->getKey(),
            'actor_id' => Actor::forUser($this->supportMember())->getKey(), 'worked_on' => $on->toDateString(), 'minutes' => $minutes,
        ]);
    }

    private ?Project $project = null;

    public function test_the_tasks_widget_lists_all_the_ongoing_tasks_without_time(): void
    {
        $this->asMaster();
        $charles = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create();
        $this->actingAs($charles);

        $mine = $this->task('Maquettes', user: $charles, attributes: ['due_on' => now()->subDay()->toDateString()]);
        $copied = $this->task('Recette', user: $charles, role: RecipientRole::Secondary);
        $done = $this->task('Cadrage', 'done', $charles);
        $draft = $this->task('Brouillon', 'draft', $charles);
        $others = $this->task('Export', 'in_progress');
        $this->time($mine, 120, now());

        // Sans le droit du suivi des temps : un seul onglet, sans temps.
        $this->assertFalse(ProjectTasksWidget::canSeeTimes());
        Livewire::test(ProjectTasksWidget::class)
            ->assertSee(['Tâches en cours', 'Les tâches projet en cours.', 'Portail parrain'])
            ->assertDontSee(['Suivi des temps', 'Temps par tâche', 'Temps du mois'])
            // Toutes les tâches en cours, qu'on en soit destinataire ou non.
            ->assertCanSeeTableRecords([$mine, $copied, $others])
            ->assertCanNotSeeTableRecords([$done, $draft])
            ->assertTableColumnVisible('due_on')
            ->assertTableColumnHidden('month_time')
            ->assertTableColumnHidden('total_time')
            ->assertActionHasUrl(TestAction::make('open')->table($mine), TaskResource::getUrl('view', ['record' => $mine]))
            // Un onglet forcé depuis le navigateur ramène au premier.
            ->set('activeTab', 'month')
            ->assertSet('activeTab', 'ongoing')
            ->assertTableColumnHidden('month_time');
    }

    public function test_time_tracking_month_by_month_in_the_master(): void
    {
        $this->asMaster();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'Europe/Paris'));
        $charles = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.time-report')->create();
        $this->actingAs($charles);

        $mockups = $this->task('Maquettes', user: $charles);
        $setup = $this->task('Cadrage', 'done', $charles, RecipientRole::Secondary);
        $others = $this->task('Export', 'in_progress');
        $this->time($mockups, 120, now());
        $this->time($mockups, 30, now()->subDays(3));
        $this->time($mockups, 60, now()->subMonth());
        $this->time($setup, 180, now()->subMonth());
        $this->time($others, 600, now());

        $widget = Livewire::test(ProjectTasksWidget::class)
            ->assertSee(['Tâches en cours', 'Suivi des temps', 'Temps par tâche'])
            ->set('activeTab', 'month')
            ->assertSee(['Temps du mois', 'Octobre 2026 : 12 h 30 au total.'])
            ->assertCanSeeTableRecords([$mockups, $others])
            ->assertCanNotSeeTableRecords([$setup])
            ->assertTableColumnStateSet('month_time', '2 h 30', $mockups)
            ->assertTableColumnStateSet('month_time', '10 h', $others)
            ->assertTableColumnHidden('due_on')
            // Le mois suivant n'existe pas encore ; le précédent, si.
            ->assertActionDisabled(TestAction::make('nextMonth')->table())
            ->assertActionEnabled(TestAction::make('previousMonth')->table())
            ->callAction(TestAction::make('previousMonth')->table())
            ->assertSet('month', '2026-09')
            ->assertSee('Septembre 2026 : 4 h au total.')
            ->assertCanSeeTableRecords([$mockups, $setup])
            ->assertCanNotSeeTableRecords([$others])
            ->assertTableColumnStateSet('month_time', '1 h', $mockups)
            ->assertTableColumnStateSet('month_time', '3 h', $setup)
            ->assertActionDisabled(TestAction::make('previousMonth')->table())
            ->assertActionEnabled(TestAction::make('nextMonth')->table())
            // Le mois du milieu ramène au mois en cours.
            ->callAction(TestAction::make('currentMonth')->table())
            ->assertSet('month', '2026-10')
            // Un mois à venir, forcé depuis le navigateur : le mois en cours.
            ->set('month', '2027-01')
            ->assertSee('Octobre 2026 : 12 h 30 au total.');

        // Temps par tâche : le cumul, tous mois confondus.
        $widget->set('activeTab', 'tasks')
            ->assertSee('Temps par tâche')
            ->assertCanSeeTableRecords([$mockups, $setup, $others])
            ->assertTableColumnStateSet('total_time', '3 h 30', $mockups)
            ->assertTableColumnStateSet('total_time', '3 h', $setup)
            ->assertTableColumnStateSet('total_time', '10 h', $others);

        foreach (range(1, 5) as $n) {
            $this->time($this->task("Lot {$n}", user: $charles), 15, now());
        }

        $this->assertCount(ProjectTasksWidget::LAST_TASKS, $widget->call('$refresh')->instance()->getTableRecords());
    }

    public function test_time_tracking_in_the_client_reads_the_totals_received(): void
    {
        $this->asClient();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'Europe/Paris'));
        $leo = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.time-report')->create();
        $this->actingAs($leo);

        $mockups = $this->task('Maquettes', user: $leo, attributes: ['time_total_minutes' => 210, 'time_by_month' => ['2026-09' => 60, '2026-10' => 150]]);
        $setup = $this->task('Cadrage', 'done', $leo, attributes: ['time_total_minutes' => 180, 'time_by_month' => ['2026-09' => 180]]);

        Livewire::test(ProjectTasksWidget::class)
            ->set('activeTab', 'month')
            ->assertSee('Octobre 2026 : 2 h 30 au total.')
            ->assertCanSeeTableRecords([$mockups])
            ->assertCanNotSeeTableRecords([$setup])
            ->callAction(TestAction::make('previousMonth')->table())
            ->assertSee('Septembre 2026 : 4 h au total.')
            ->assertCanSeeTableRecords([$mockups, $setup])
            ->assertTableColumnStateSet('month_time', '3 h', $setup)
            ->assertActionDisabled(TestAction::make('previousMonth')->table())
            ->set('activeTab', 'tasks')
            ->assertTableColumnStateSet('total_time', '3 h 30', $mockups);

        // Sur le tableau de bord, pour qui voit les tâches.
        $this->get(Dashboard::getUrl())->assertOk()->assertSeeLivewire(ProjectTasksWidget::class);
        $this->actingAs(User::factory()->withPermissions('panels.admin.access')->create());
        $this->assertFalse(ProjectTasksWidget::canView());
    }
}
