<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Notifications\AutoCloseWarning;
use CharlesStOlive\FilamentTma\Notifications\TicketAutoClosed;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/** Clôture automatique (addendum §4) : avertissement à J-7, clôture à 28 jours, par l'application qui traite. */
class AutoCloseTest extends TmaTestCase
{
    /** Un ticket local dont le demandeur ne répond plus. */
    private function waitingLocalTicket(): array
    {
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $ticket = $this->draft($requester);
        $this->workflow()->run($ticket, 'submit', [], Context::ui($requester));
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($handler));
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Une capture ?</p>'], Context::ui($handler));

        return [$ticket, $requester];
    }

    public function test_the_requester_is_warned_then_the_ticket_is_closed(): void
    {
        Notification::fake();
        [$ticket, $requester] = $this->waitingLocalTicket();

        $this->travel(20)->days();
        $this->artisan('tma:close-stale')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(1)->days();
        $this->artisan('tma:close-stale')->assertSuccessful();
        Notification::assertSentTo($requester, AutoCloseWarning::class);
        $this->assertNotNull($ticket->refresh()->auto_close_warned_at);

        // Une seule fois.
        $this->artisan('tma:close-stale');
        Notification::assertSentToTimes($requester, AutoCloseWarning::class, 1);

        $this->travel(7)->days();
        $this->artisan('tma:close-stale')->assertSuccessful();

        $this->assertInstanceOf(Closed::class, $ticket->refresh()->state);
        $this->assertSame('auto-close', $ticket->transitions()->latest('id')->first()->move);
        Notification::assertSentTo($requester, TicketAutoClosed::class);
    }

    public function test_any_activity_restarts_the_count(): void
    {
        Notification::fake();
        [$ticket, $requester] = $this->waitingLocalTicket();

        $this->travel(22)->days();
        $this->artisan('tma:close-stale');
        $this->assertNotNull($ticket->refresh()->auto_close_warned_at);

        // Un commentaire : le compte repart de zéro, l'avertissement aussi.
        app(\CharlesStOlive\FilamentTma\Workflow\Timeline::class)->add($ticket, \CharlesStOlive\FilamentTma\Enums\EntryType::Comment, '<p>Je cherche.</p>', Context::ui($requester));
        $this->assertNull($ticket->refresh()->auto_close_warned_at);

        $this->travel(10)->days();
        $this->artisan('tma:close-stale');
        $this->assertInstanceOf(AwaitingRequester::class, $ticket->refresh()->state);
    }

    public function test_a_late_warning_still_leaves_the_full_delay(): void
    {
        Notification::fake();
        [$ticket] = $this->waitingLocalTicket();

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
        Notification::fake();
        [$ticket] = $this->waitingLocalTicket();

        $this->travel(60)->days();
        $this->artisan('tma:close-stale', ['--dry-run' => true])->expectsOutputToContain('[simulation]')->assertSuccessful();

        $this->assertNull($ticket->refresh()->auto_close_warned_at);
        Notification::assertNothingSent();
    }

    public function test_only_the_handling_application_closes_and_a_remote_requester_is_not_mailed_from_here(): void
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

        // Vu du client, ce ticket du centre de service n'est pas à lui de le clôturer.
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
        [$ticket, $requester] = $this->waitingLocalTicket();

        $warning = (string) (new AutoCloseWarning($ticket, now()->addDays(7)))->toMail($requester)->render();
        $this->assertStringContainsString($ticket->number, $warning);
        $this->assertStringContainsString(\CharlesStOlive\FilamentTma\Support\TicketUrl::view($ticket), $warning);
        $this->assertStringContainsString('Un commentaire suffit', $warning);

        $closed = (string) (new TicketAutoClosed($ticket))->toMail($requester)->render();
        $this->assertStringContainsString('clôturé automatiquement', $closed);
    }

    public function test_the_commands_are_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($event) => $event->command);

        $this->assertTrue($events->contains(fn (?string $command): bool => str_contains((string) $command, 'tma:close-stale')));
        $this->assertTrue($events->contains(fn (?string $command): bool => str_contains((string) $command, 'tma:resume-paused')));
    }
}
