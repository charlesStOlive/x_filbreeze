<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentPermissionManager\UserOptions\UserOptions;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Support\Notifier;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

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

    public function test_the_warning_asks_for_an_answer_and_the_closing_goes_to_the_bell_and_the_digest(): void
    {
        // Dans l'application cliente, où le demandeur a son compte.
        Notification::fake();
        $this->asClient();
        $requester = $this->correspondent();
        $ticket = $this->serviceDeskTicket('awaiting_requester');
        $ticket->forceFill(['requester_actor_id' => Actor::forUser($requester)->getKey(), 'auto_close_warned_at' => now()])->save();
        app(Recipients::class)->add($ticket, Actor::forUser($requester));

        // L'avertissement attend une réponse : par courriel avec l'e-mail direct, la date dans l'objet.
        Notifier::autoCloseWarning($ticket, now()->addDays(7));
        $date = now()->addDays(7)->timezone('Europe/Paris')->format('d/m/Y');
        Notification::assertSentTo($requester, NoticeNotification::class, function (NoticeNotification $notice) use ($requester, $ticket, $date): bool {
            $mail = $notice->toMail($requester);
            $html = html_entity_decode((string) $mail->render());

            return $notice->via($requester) === ['database', 'mail']
                && $mail->subject === "[DES-0001] Sans réponse, ce ticket sera clos le {$date} – Export des parrains"
                && str_contains($html, 'À faire')
                && str_contains($html, 'une simple note, suffit à le garder ouvert')
                && str_contains($html, TicketUrl::view($ticket));
        });

        // Sans e-mail direct : la cloche, et le bilan du matin.
        UserOptions::save($requester, 'tma', ['notifications' => ['channels' => [], 'digest' => true]]);
        $ticket->forceFill(['auto_close_warned_at' => now()->addMinute()])->save();
        Notifier::autoCloseWarning($ticket->refresh(), now()->addDays(7));
        $this->assertSame(1, Notice::query()->for($requester)->pending()->where('event', NoticeEvent::AutoCloseWarning->value)->count());

        // La clôture ne demande plus rien : la cloche et le bilan, jamais de courriel.
        $ticket->forceFill(['state' => 'closed'])->save();
        Notifier::transition($ticket->refresh(), Mirror::transition($ticket, [
            'ulid' => strtolower((string) Str::ulid()), 'move' => 'auto-close', 'from_state' => 'awaiting_requester', 'to_state' => 'closed',
            'actor' => null, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'scheduler',
        ]));
        $closed = Notice::query()->for($requester)->where('event', NoticeEvent::AutoClosed->value)->sole();
        $this->assertSame(NoticeMode::Grouped, $closed->mode);
        $this->assertSame('Restée sans réponse, la demande a été close automatiquement.', $closed->data['line']);
        Notification::assertSentTo($requester, NoticeNotification::class, fn (NoticeNotification $notice): bool => $notice->notice->is($closed) && $notice->via($requester) === ['database']);

        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($requester, DailyDigest::class, function (DailyDigest $digest) use ($requester): bool {
            $html = html_entity_decode((string) $digest->toMail($requester)->render());

            return str_contains($html, 'Nouveautés') && str_contains($html, 'close automatiquement');
        });
    }

    public function test_the_commands_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command);

        $this->assertTrue($events->contains(fn (?string $command): bool => str_contains((string) $command, 'tma:close-stale')));
        $this->assertTrue($events->contains(fn (?string $command): bool => str_contains((string) $command, 'tma:resume-paused')));
    }
}
