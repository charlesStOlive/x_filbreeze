<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Widgets\MySupportWidget;
use CharlesStOlive\FilamentTma\Filament\Widgets\TimeReportWidget;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Filament\Actions\Testing\TestAction;
use Filament\Pages\Dashboard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Le tableau de bord : mes tickets en cours et mes projets (deux onglets d'un même tableau), puis le suivi des temps
 * (TMA et projets, mois par mois, en jours et heures).
 */
class DashboardWidgetsTest extends TmaTestCase
{
    private ?Project $project = null;

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

    /** Une tâche publiée chez le client, dans `$project` (« Portail parrain » sinon), dont `$user` est destinataire (ou non). */
    private function task(string $title, string $state = 'in_progress', ?User $user = null, RecipientRole $role = RecipientRole::Primary, array $attributes = [], ?Project $project = null): ProjectTask
    {
        $this->project ??= Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID]);
        $task = ProjectTask::query()->create([
            'number' => 'T-'.str_pad((string) (ProjectTask::query()->withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'target_instance_uid' => self::CLIENT_UID, 'project_id' => ($project ?? $this->project)->getKey(), 'title' => $title, 'state' => $state,
            'published_at' => $state === 'draft' ? null : now()->subMonths(3), 'last_activity_at' => now(), ...$attributes,
        ]);

        if ($user !== null) {
            app(Recipients::class)->add($task, Actor::forUser($user), $role);
        }

        return $task;
    }

    /** Maître : du temps saisi sur un ticket ou une tâche. */
    private function time(Model $subject, int $minutes, Carbon $on): void
    {
        TimeEntry::query()->create([
            'ulid' => strtolower((string) Str::ulid()), 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'actor_id' => Actor::forUser($this->supportMember())->getKey(), 'worked_on' => $on->toDateString(), 'minutes' => $minutes,
        ]);
    }

    public function test_my_tickets_where_one_is_a_recipient_those_awaiting_first(): void
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

        Livewire::test(MySupportWidget::class)
            ->assertSet('tab', MySupportWidget::TICKETS)
            ->assertSee('Mes tickets en cours')
            ->assertDontSee('autres tickets')
            // Celui qui l'attend d'abord, puis les plus récemment actifs.
            ->assertCanSeeTableRecords([$mine, $recent, $copied], inOrder: true)
            ->assertCanNotSeeTableRecords([$closed, $paused, $others])
            ->assertTableColumnStateSet('waiting_on', 'Vous', $mine)
            ->assertTableColumnStateSet('waiting_on', 'Ada Lovelace', $recent)
            ->assertTableColumnStateSet('waiting_on', 'Support', $copied)
            ->assertTableColumnStateSet('recipient_role', RecipientRole::Primary, $mine)
            ->assertTableColumnStateSet('recipient_role', RecipientRole::Secondary, $copied)
            ->assertActionHasUrl(TestAction::make('open')->table($mine), TicketUrl::view($mine))
            ->assertActionVisible(TestAction::make('all')->table())
            // Sans le droit de voir les projets, pas d'onglets : le tableau des tickets seul.
            ->assertDontSee('Mes projets')
            ->set('tab', MySupportWidget::PROJECTS)
            ->assertSet('tab', MySupportWidget::TICKETS)
            ->assertCanSeeTableRecords([$mine]);
    }

    public function test_ten_at_most_and_the_count_says_so(): void
    {
        $this->asMaster();
        $charles = $this->supportMember();
        $this->actingAs($charles);

        foreach (range(10, 22) as $n) {
            $this->ticket("DES-00{$n}", 'in_progress', $charles);
        }

        // Au-delà de dix, la dernière ligne dit combien il en reste, en lien vers la liste.
        $widget = Livewire::test(MySupportWidget::class)
            ->assertSee('+ 3 autres tickets');
        $this->assertCount(MySupportWidget::LIMIT, $widget->instance()->getTableRecords());
    }

    public function test_the_client_sees_what_waits_for_him_and_only_who_sees_tickets_or_projects_has_the_widget(): void
    {
        $this->asClient();
        $leo = $this->correspondent();
        $this->actingAs($leo);
        $ticket = $this->draft($leo);
        $ticket->forceFill(['state' => 'awaiting_requester'])->save();

        Livewire::test(MySupportWidget::class)
            ->assertCanSeeTableRecords([$ticket])
            ->assertTableColumnStateSet('waiting_on', 'Vous', $ticket);

        // Sur le tableau de bord, pour qui voit les tickets.
        $this->get(Dashboard::getUrl())->assertOk()->assertSeeLivewire(MySupportWidget::class);

        $this->actingAs(User::factory()->withPermissions('panels.admin.access')->create());
        $this->assertFalse(MySupportWidget::canView());
        $this->get(Dashboard::getUrl())->assertOk()->assertDontSeeLivewire(MySupportWidget::class);

        // Qui ne voit que les projets n'a que leur onglet.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.project.viewany')->create());
        $this->assertSame([MySupportWidget::PROJECTS], MySupportWidget::tabs());
        Livewire::test(MySupportWidget::class)->assertSet('tab', MySupportWidget::PROJECTS)->assertSee('Mes projets');
    }

    public function test_my_projects_where_one_takes_part_with_their_estimate_and_time(): void
    {
        $this->asMaster();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'Europe/Paris'));
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton']);
        $charles = $this->supportMember('tma.project.viewany', 'tma.project.view');
        $this->actingAs($charles);

        // Nommé participant, avec une fourchette : 10 h passées, soit 1 j 3 h, au-delà d'une fourchette de 0,5 à 1 j.
        $portal = Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID, 'estimate_min_days' => 0.5, 'estimate_max_days' => 1]);
        $portal->members()->attach(Actor::forUser($charles));
        $mockups = $this->task('Maquettes', project: $portal);
        $this->task('Recette', project: $portal);
        $this->time($mockups, 600, now());
        // Destinataire d'une de ses tâches, sans être nommé.
        $export = Project::create(['title' => 'Export comptable', 'target_instance_uid' => self::CLIENT_UID, 'estimate_min_days' => 1, 'estimate_max_days' => 3]);
        $this->task('Fichier FEC', user: $charles, role: RecipientRole::Secondary, project: $export);
        // Pas participant ; ou participant d'un projet terminé.
        $elsewhere = Project::create(['title' => 'Intranet', 'target_instance_uid' => self::CLIENT_UID]);
        $this->task('Annuaire', project: $elsewhere);
        $finished = Project::create(['title' => 'Cadrage', 'target_instance_uid' => self::CLIENT_UID]);
        $finished->members()->attach(Actor::forUser($charles));
        $this->task('Atelier', 'done', project: $finished);

        $this->assertEqualsCanonicalizing([$portal->getKey(), $export->getKey()], MySupportWidget::projects($charles)->pluck('id')->all());
        // Le petit nombre de l'onglet : les tâches en cours de mes projets.
        $this->assertSame(3, MySupportWidget::ongoingTasks($charles)->count());

        Livewire::test(MySupportWidget::class)
            // Les onglets : un groupe de boutons dans l'en-tête du tableau, le petit nombre sur « Projets ».
            ->assertSeeHtml('fi-btn-group')
            ->assertSee(['Tickets', 'Projets', 'Le nombre : leurs tâches en cours'])
            ->assertDontSee('Le nombre : ceux qui vous attendent')
            ->set('tab', MySupportWidget::PROJECTS)
            ->assertSee('Mes projets')
            ->assertCanSeeTableRecords([$portal, $export])
            ->assertCanNotSeeTableRecords([$elsewhere, $finished])
            ->assertTableColumnStateSet('estimate', '0,5–1 j', $portal)
            ->assertTableColumnStateSet('time', '1 j 3 h', $portal)
            ->assertTableColumnStateSet('ongoing_tasks_count', 2, $portal)
            ->assertTableColumnStateSet('estimate', '1–3 j', $export)
            ->assertTableColumnStateSet('time', '0 h', $export)
            ->assertActionHasUrl(TestAction::make('open')->table($portal), ProjectResource::getUrl('view', ['record' => $portal]))
            ->assertTableColumnHidden('waiting_on')
            ->set('tab', MySupportWidget::TICKETS)
            ->assertSee('Mes tickets en cours')
            ->assertTableColumnHidden('estimate');
    }

    public function test_the_time_report_month_by_month_in_the_master(): void
    {
        $this->asMaster();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'Europe/Paris'));
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test', 'token' => 'jeton']);
        $charles = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.time-report', 'tma.project.view')->create();
        $this->actingAs($charles);

        $portal = Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID, 'estimate_min_days' => 1, 'estimate_max_days' => 2]);
        $mockups = $this->task('Maquettes', project: $portal);
        $setup = $this->task('Cadrage', 'done', project: $portal);
        $export = $this->task('Export', project: Project::create(['title' => 'Export comptable', 'target_instance_uid' => self::CLIENT_UID]));
        $ticket = $this->ticket('DES-0001', 'in_progress');
        $this->time($mockups, 120, now());
        $this->time($mockups, 30, now()->subDays(3));
        $this->time($setup, 60, now());
        $this->time($mockups, 600, now()->subMonth());
        $this->time($export, 480, now());
        $this->time($ticket, 45, now());

        // Tous les temps, quels que soient les destinataires : la TMA d'abord, puis les projets, du plus de temps au moins.
        $widget = Livewire::test(TimeReportWidget::class)
            ->assertSee(['Suivi des temps', 'Octobre 2026 : 1 j 5 h 15 au total.'])
            ->assertSee(['TMA', 'EDM', 'DES-0001 Ticket DES-0001', 'Export comptable', 'Portail parrain', 'T-0001 Maquettes', 'T-0002 Cadrage']);
        $this->assertSame(['tma-'.self::CLIENT_UID, 'project-'.$export->project_id, 'project-'.$portal->getKey()], array_keys($widget->instance()->rows()));
        $widget
            ->assertTableColumnStateSet('minutes', 45, 'tma-'.self::CLIENT_UID)
            ->assertTableColumnStateSet('minutes', 480, 'project-'.$export->project_id)
            ->assertTableColumnStateSet('minutes', 210, 'project-'.$portal->getKey())
            // Le cumul du projet, sur sa fourchette : 13 h 30, soit 1 j 6 h 30, dans la fourchette de 1 à 2 j.
            ->assertTableColumnStateSet('total', '1 j 6 h 30 / 1–2 j', 'project-'.$portal->getKey())
            ->assertSee('<strong>2 h 30</strong>', false)
            // Le mois suivant n'existe pas encore ; le précédent, si.
            ->assertActionDisabled(TestAction::make('nextMonth')->table())
            ->callAction(TestAction::make('previousMonth')->table())
            ->assertSet('month', '2026-09')
            ->assertSee('Septembre 2026 : 1 j 3 h au total.')
            ->assertDontSee('Export comptable')
            ->assertActionDisabled(TestAction::make('previousMonth')->table())
            ->callAction(TestAction::make('currentMonth')->table())
            ->assertSet('month', '2026-10')
            // Un mois à venir, forcé depuis le navigateur : le mois en cours.
            ->set('month', '2027-01')
            ->assertSee('Octobre 2026 : 1 j 5 h 15 au total.');

        // Sans le droit du suivi des temps, pas de widget.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create());
        $this->assertFalse(TimeReportWidget::canView());
    }

    public function test_the_time_report_in_the_client_reads_the_totals_received(): void
    {
        $this->asClient();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'Europe/Paris'));
        $leo = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.time-report')->create();
        $this->actingAs($leo);

        $mockups = $this->task('Maquettes', attributes: ['time_total_minutes' => 210, 'time_by_month' => ['2026-09' => 60, '2026-10' => 150]]);
        $this->task('Cadrage', 'done', attributes: ['time_total_minutes' => 180, 'time_by_month' => ['2026-09' => 180]]);
        $ticket = $this->draft($this->correspondent());
        $ticket->forceFill(['time_total_minutes' => 30, 'time_by_month' => ['2026-10' => 30]])->save();

        Livewire::test(TimeReportWidget::class)
            ->assertSee(['Octobre 2026 : 3 h au total.', 'TMA', 'Portail parrain', 'T-0001 Maquettes'])
            ->assertTableColumnStateSet('minutes', 150, 'project-'.$mockups->project_id)
            ->assertTableColumnStateSet('total', '6 h 30', 'project-'.$mockups->project_id)
            ->callAction(TestAction::make('previousMonth')->table())
            ->assertSee('Septembre 2026 : 4 h au total.')
            // Pas de TMA ce mois-là.
            ->assertCanNotSeeTableRecords(['tma-'.self::CLIENT_UID])
            ->assertActionDisabled(TestAction::make('previousMonth')->table());

        // Sur le tableau de bord, pour qui a le droit du suivi des temps.
        $this->get(Dashboard::getUrl())->assertOk()->assertSeeLivewire(TimeReportWidget::class);
    }
}
