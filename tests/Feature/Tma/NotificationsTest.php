<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\TicketMoveNotification;
use CharlesStOlive\FilamentTma\Support\Digest;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/** Rappels et notifications (CDC §12, lot 2) : le récapitulatif quotidien, les notifications immédiates. */
class NotificationsTest extends TmaTestCase
{
    public function test_the_digest_lists_what_waits_for_each_one_and_nobody_gets_an_empty_mail(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $idle = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany')->create();

        $waiting = $this->draft($requester, ['title' => 'Bouton d’export']);
        $this->workflow()->run($waiting, 'submit', ['assignee_user_id' => $handler->getKey()], Context::ui($requester));
        $this->workflow()->run($waiting, 'take-charge', [], Context::ui($handler));
        $this->workflow()->run($waiting, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));

        $unassigned = $this->draft($requester, ['title' => 'Faute de frappe']);
        $this->workflow()->run($unassigned, 'submit', [], Context::ui($requester));

        $this->assertSame(['DES-0001'], Digest::for($requester)['answer']->pluck('ref')->all());
        $this->assertSame(['DES-0002'], Digest::for($handler)['take']->pluck('ref')->all());
        $this->assertSame([], Digest::for($idle));

        $this->travel(8)->days();
        $this->assertTrue(Digest::for($requester)['answer']->first()['late']);

        $this->artisan('tma:digest')->expectsOutputToContain('récapitulatif')->assertSuccessful();
        Notification::assertSentTo($requester, DailyDigest::class);
        Notification::assertNotSentTo($idle, DailyDigest::class);

        $mail = (string) (new DailyDigest(Digest::for($requester)))->toMail($requester)->render();
        $this->assertStringContainsString('DES-0001 — Bouton d’export', html_entity_decode($mail));
        $this->assertStringContainsString('en retard', $mail);
    }

    public function test_the_digest_is_scheduled_on_working_days(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'tma:digest'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * 1,2,3,4,5', $event->expression);
    }

    public function test_immediate_notifications_are_off_by_default_and_never_go_to_the_author(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $ticket = $this->draft($requester);
        $this->workflow()->run($ticket, 'submit', ['assignee_user_id' => $handler->getKey()], Context::ui($requester));
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($handler));
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>?</p>'], Context::ui($handler));
        Notification::assertNothingSent();

        config(['filament-tma.notifications.immediate' => ['request-answer', 'answer']]);
        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>!</p>'], Context::ui($requester));
        Notification::assertSentTo($handler, TicketMoveNotification::class);

        // Le demandeur qui répond lui-même… à son propre ticket dont il est aussi l'intervenant : pas de courriel.
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Encore ?</p>'], Context::ui($requester));
        Notification::assertNotSentTo($requester, TicketMoveNotification::class);
    }

    public function test_a_question_from_the_master_is_told_to_the_requester_by_the_client(): void
    {
        Notification::fake();
        config(['filament-tma.notifications.immediate' => ['request-answer']]);
        $this->asClient();
        $requester = $this->agent();
        $ticket = $this->draft($requester);
        $ticket->forceFill(['handling_mode' => 'service_desk', 'state' => 'in_progress'])->save();

        $master = User::factory()->create(['email' => 'filbreeze@api.test']);
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        Passport::actingAs($master, [], 'api');

        $snapshot = Payload::snapshot($ticket);
        $snapshot['ticket']['state'] = 'awaiting_requester';
        $snapshot['transitions'][] = [
            'ulid' => strtolower((string) Str::ulid()), 'move' => 'request-answer', 'from_state' => 'in_progress', 'to_state' => 'awaiting_requester',
            'actor' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui',
        ];

        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        Notification::assertSentToTimes($requester, TicketMoveNotification::class, 1);
        $mail = (string) (new TicketMoveNotification($ticket->refresh(), $ticket->transitions()->sole()))->toMail($requester)->render();
        $this->assertStringContainsString('Charles attend votre réponse', $mail);
    }
}
