<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Widgets\OngoingTicketsWidget;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Filament\Actions\Testing\TestAction;
use Filament\Pages\Dashboard;
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
}
