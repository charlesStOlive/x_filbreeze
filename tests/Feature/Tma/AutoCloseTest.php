<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\AutoCloseWarning;
use CharlesStOlive\FilamentTma\Notifications\TicketAutoClosed;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * Clôture automatique (addendum §4) : avertissement à J-7, clôture à 28 jours, par le maître seul ; l'application
 * cliente prévient son demandeur quand l'état lui arrive.
 */
class AutoCloseTest extends TmaTestCase
{
    /** Un ticket du Support, dans le maître, dont le demandeur ne répond plus. */
    private function waitingTicket(): Ticket
    {
        $this->asMaster();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('in_progress');
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Une capture ?</p>'], Context::ui($support));

        return $ticket->refresh();
    }

    public function test_the_ticket_is_warned_then_closed(): void
    {
        $ticket = $this->waitingTicket();
        Event::fake([AutoCloseWarned::class]);

        $this->travel(20)->days();
        $this->artisan('tma:close-stale')->assertSuccessful();
        Event::assertNotDispatched(AutoCloseWarned::class);

        $this->travel(1)->days();
        $this->artisan('tma:close-stale')->assertSuccessful();
        Event::assertDispatchedTimes(AutoCloseWarned::class, 1);
        $this->assertNotNull($ticket->refresh()->auto_close_warned_at);

        // Une seule fois.
        $this->artisan('tma:close-stale');
        Event::assertDispatchedTimes(AutoCloseWarned::class, 1);

        $this->travel(7)->days();
        $this->artisan('tma:close-stale')->assertSuccessful();

        $this->assertInstanceOf(Closed::class, $ticket->refresh()->state);
        $this->assertSame('auto-close', $ticket->transitions()->latest('id')->first()->move);
    }

    public function test_any_activity_restarts_the_count(): void
    {
        $ticket = $this->waitingTicket();
        Notification::fake();

        $this->travel(22)->days();
        $this->artisan('tma:close-stale');
        $this->assertNotNull($ticket->refresh()->auto_close_warned_at);

        // Une note du demandeur, reçue de son application : le compte repart de zéro, l'avertissement aussi.
        app(Timeline::class)->write($ticket, EntryType::Note, '<p>Je cherche.</p>', $this->fromClient());
        $this->assertNull($ticket->refresh()->auto_close_warned_at);

        $this->travel(10)->days();
        $this->artisan('tma:close-stale');
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->refresh()->state);
    }

    public function test_a_late_warning_still_leaves_the_full_delay(): void
    {
        $ticket = $this->waitingTicket();
        Notification::fake();

        // Le planificateur était arrêté : premier passage à 40 jours.
        $this->travel(40)->days();
        $this->artisan('tma:close-stale');
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->refresh()->state);

        $this->travel(6)->days();
        $this->artisan('tma:close-stale');
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->refresh()->state);

        $this->travel(1)->days();
        $this->artisan('tma:close-stale');
        $this->assertInstanceOf(Closed::class, $ticket->refresh()->state);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $ticket = $this->waitingTicket();
        Notification::fake();

        $this->travel(60)->days();
        $this->artisan('tma:close-stale', ['--dry-run' => true])->expectsOutputToContain('[simulation]')->assertSuccessful();

        $this->assertNull($ticket->refresh()->auto_close_warned_at);
        Notification::assertNothingSent();
    }

    public function test_only_the_master_closes_and_a_remote_requester_is_not_mailed_from_here(): void
    {
        Notification::fake();
        Event::fake([AutoCloseWarned::class]);
        $this->asMaster();
        $ticket = $this->serviceDeskTicket('awaiting_requester');
        $ticket->forceFill(['last_activity_at' => now()])->save();

        $this->travel(21)->days();
        $this->artisan('tma:close-stale');
        // Le demandeur est un utilisateur de l'application cliente : c'est elle qui l'avertira (lot 1b).
        Notification::assertNothingSent();
        Event::assertDispatched(AutoCloseWarned::class);

        $this->travel(7)->days();
        $this->artisan('tma:close-stale');
        $this->assertInstanceOf(Closed::class, $ticket->refresh()->state);

        // Vu du client, ce ticket du Support n'est pas à lui de le clôturer.
        $other = $this->serviceDeskTicket('awaiting_requester', 'DES-0002');
        $other->forceFill(['last_activity_at' => now()->subDays(90), 'auto_close_warned_at' => now()->subDays(30)])->save();
        $this->asClient();
        $this->artisan('tma:close-stale');
        $this->assertInstanceOf(AwaitingRequester::class, $other->refresh()->state);
    }

    public function test_paused_tickets_resume_on_their_date(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket('in_progress');
        $this->workflow()->run($ticket, 'pause', ['until' => now()->addHours(3)->toDateTimeString()], Context::ui($this->agent()));

        $this->artisan('tma:resume-paused')->expectsOutputToContain('0 ticket');
        $this->travel(4)->hours();
        $this->artisan('tma:resume-paused')->expectsOutputToContain('1 ticket');

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);
    }

    public function test_the_mails_render_with_a_link_to_the_ticket(): void
    {
        // Dans l'application cliente, où le demandeur a son compte.
        $this->asClient();
        $requester = $this->correspondent();
        $ticket = $this->serviceDeskTicket('awaiting_requester');
        $ticket->forceFill(['requester_actor_id' => Actor::forUser($requester)->getKey()])->save();

        $warning = (string) (new AutoCloseWarning($ticket, now()->addDays(7)))->toMail($requester)->render();
        $this->assertStringContainsString($ticket->number, $warning);
        $this->assertStringContainsString(TicketUrl::view($ticket), $warning);
        $this->assertStringContainsString('une simple note, suffit', $warning);

        $closed = (string) (new TicketAutoClosed($ticket))->toMail($requester)->render();
        $this->assertStringContainsString('clos automatiquement', $closed);
    }

    public function test_the_commands_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command);

        $this->assertTrue($events->contains(fn (?string $command): bool => str_contains((string) $command, 'tma:close-stale')));
        $this->assertTrue($events->contains(fn (?string $command): bool => str_contains((string) $command, 'tma:resume-paused')));
    }
}
