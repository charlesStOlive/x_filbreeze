<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\NotificationPreference;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\Support\Digest;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/**
 * Rappels et notifications (CDC §12) : un destinataire principal apprend tout de suite ce qui arrive, par courriel ou
 * dans la cloche selon son choix ; un secondaire, dans son bilan ; les autres, rien. La relance, le rappel de ce qui
 * ne bouge plus, l'envoi à la demande.
 */
class NotificationsTest extends TmaTestCase
{
    /** Un ticket local : le demandeur l'a confié à l'intervenant, qui l'a pris et pose une question. */
    private function askedTicket(User $requester, User $handler, array $attributes = []): Ticket
    {
        $ticket = $this->draft($requester, $attributes);
        $this->workflow()->run($ticket, 'submit', ['recipients' => ['u:'.$handler->getKey()]], Context::ui($requester));
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($handler));
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));

        return $ticket->refresh();
    }

    public function test_the_digest_lists_what_waits_for_each_one_and_nobody_gets_an_empty_mail(): void
    {
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $idle = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany')->create();

        $this->askedTicket($requester, $handler, ['title' => 'Bouton d’export']);
        $unassigned = $this->draft($requester, ['title' => 'Faute de frappe']);
        $this->workflow()->run($unassigned, 'submit', [], Context::ui($requester));

        $this->assertSame(['DES-0001'], Digest::for($requester)['answer']->pluck('ref')->all());
        $this->assertSame(['DES-0002'], Digest::for($handler)['take']->pluck('ref')->all());
        $this->assertSame([], Digest::for($idle));

        $this->travel(8)->days();
        $this->assertTrue(Digest::for($requester)['answer']->first()['late']);

        Notification::fake();
        $this->artisan('tma:digest')->expectsOutputToContain('récapitulatif')->assertSuccessful();
        Notification::assertSentTo($requester, DailyDigest::class);
        Notification::assertNotSentTo($idle, DailyDigest::class);

        $mail = (string) (new DailyDigest(Digest::for($requester)))->toMail($requester)->render();
        $this->assertStringContainsString('DES-0001 — Bouton d’export', html_entity_decode($mail));
        $this->assertStringContainsString('en retard', $mail);
    }

    public function test_the_digest_and_the_reminders_are_scheduled_on_working_days(): void
    {
        $events = collect(app(Schedule::class)->events());
        $digest = $events->first(fn ($e) => str_contains((string) $e->command, 'tma:digest'));
        $stale = $events->first(fn ($e) => str_contains((string) $e->command, 'tma:notify') && str_contains((string) $e->command, '--stale'));

        $this->assertSame('0 8 * * 1,2,3,4,5', $digest?->expression);
        $this->assertSame('30 7 * * 1,2,3,4,5', $stale?->expression);
    }

    public function test_a_primary_hears_at_once_a_secondary_in_the_digest_the_others_nothing(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $colleague = $this->agent();
        $outsider = $this->agent();

        $ticket = $this->draft($requester, ['title' => 'Bouton d’export']);
        $this->workflow()->run($ticket, 'submit', ['recipients' => ['u:'.$handler->getKey()]], Context::ui($requester));
        app(Recipients::class)->add($ticket, Actor::forUser($colleague), RecipientRole::Secondary);
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($handler));
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));

        // Le demandeur, principal : tout de suite, par courriel et dans la cloche ; « Un ticket vous attend ».
        Notification::assertSentTo($requester, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Turn
            && $mail->via($requester) === ['mail', 'database']
            && $mail->notice->data['excerpt'] === 'Quel format ?');
        $database = (new NoticeNotification(Notice::query()->for($requester)->where('event', NoticeEvent::Turn->value)->sole(), ['database']))->toDatabase($requester);
        $this->assertSame('[DES-0001] Bouton d’export', $database['title']);
        $this->assertStringContainsString('attend votre réponse', $database['body']);
        $this->assertSame('Ouvrir le ticket', $database['actions'][0]['label']);

        // L'intervenant, principal depuis l'envoi : on lui a confié la demande.
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->data['line'] === $requester->name.' a envoyé la demande à Équipe locale.');

        // La collègue, secondaire : rien tout de suite, tout dans son bilan.
        Notification::assertNotSentTo($colleague, NoticeNotification::class);
        $this->assertSame([NoticeEvent::Progress, NoticeEvent::Progress], Notice::query()->pending()->for($colleague)->orderBy('id')->pluck('event')->all());

        // Les autres : rien.
        $this->assertFalse(Notice::query()->for($outsider)->exists());

        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($colleague, DailyDigest::class, function (DailyDigest $mail) use ($colleague): bool {
            $html = html_entity_decode((string) $mail->toMail($colleague)->render());

            return str_contains($html, 'Depuis le dernier envoi') && str_contains($html, 'a pris la demande en charge') && str_contains($html, '« Quel format ? »');
        });
        $this->assertSame(0, Notice::query()->pending()->count());
    }

    public function test_each_one_chooses_how_a_primary_notification_reaches_them(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $ticket = $this->askedTicket($requester, $handler);

        NoticeChannels::save($requester, ['database']);
        app(Timeline::class)->add($ticket, EntryType::Comment, '<p>Je regarde ça demain.</p>', Context::ui($handler));
        Notification::assertSentTo($requester, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Message
            && $mail->via($requester) === ['database']);

        // Aucun canal : ce qui le concerne attend son bilan.
        NoticeChannels::save($requester, []);
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Voir le journal.</p>', Context::ui($handler));
        $this->assertTrue(Notice::query()->pending()->for($requester)->where('event', NoticeEvent::Note->value)->exists());

        // Une note privée ne prévient personne.
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Pour moi seul.</p>', Context::ui($handler), private: true);
        $this->assertSame(1, Notice::query()->where('event', NoticeEvent::Note->value)->count());

        $mail = html_entity_decode((string) (new NoticeNotification(Notice::query()->for($requester)->where('event', NoticeEvent::Message->value)->sole()))->toMail($requester)->render());
        $this->assertStringContainsString('a écrit un message', $mail);
        $this->assertStringContainsString('« Je regarde ça demain. »', $mail);
        $this->assertStringContainsString('destinataire principal', $mail);
    }

    public function test_my_notifications_are_set_from_the_ticket_list_or_the_user_menu(): void
    {
        $this->asClient();
        $me = $this->agent();
        $this->actingAs($me);

        // Le lien du menu de l'utilisateur ouvre la fenêtre au chargement de la liste (`?action=`).
        Livewire::withQueryParams(['action' => 'tmaNotifications'])->test(ListTickets::class)
            ->call('mountAction', 'tmaNotifications')
            ->assertActionMounted('tmaNotifications');

        $this->assertSame(['mail', 'database'], NoticeChannels::for($me));

        Livewire::test(ListTickets::class)
            ->callAction('tmaNotifications', ['channels' => ['mail']])
            ->assertHasNoActionErrors();

        $this->assertSame(['mail'], NotificationPreference::of($me)->channels);
        $this->assertSame(['mail'], NoticeChannels::for($me));

        // L'application peut fixer le réglage pour tous.
        config(['filament-tma.notifications.user_choice' => false]);
        $this->assertSame(['mail', 'database'], NoticeChannels::for($me));
    }

    public function test_what_the_master_sends_is_told_to_the_requester_by_the_client_once(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $ticket = $this->draft($requester);
        $ticket->forceFill(['handling_mode' => 'service_desk', 'state' => 'in_progress'])->save();

        $master = User::factory()->create(['email' => 'filbreeze@api.test']);
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        Passport::actingAs($master, [], 'api');

        $charles = ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'];
        $snapshot = Payload::snapshot($ticket);
        $snapshot['ticket']['state'] = 'awaiting_requester';
        $snapshot['transitions'][] = [
            'ulid' => strtolower((string) Str::ulid()), 'move' => 'request-answer', 'from_state' => 'in_progress', 'to_state' => 'awaiting_requester',
            'actor' => $charles, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui',
        ];
        $snapshot['entries'][] = [
            'ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>Nous avons trouvé la cause.</p>',
            'author' => $charles, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui', 'created_at' => now()->toIso8601String(),
        ];
        $snapshot['recipients'][] = ['actor' => $charles, 'role' => 'primary', 'support' => false];

        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        // La question et le message, une fois chacun, au demandeur (principal) ; Charles est prévenu par le maître.
        Notification::assertSentToTimes($requester, NoticeNotification::class, 2);
        $mail = (string) (new NoticeNotification(Notice::query()->for($requester)->where('event', NoticeEvent::Turn->value)->sole()))->toMail($requester)->render();
        $this->assertStringContainsString('Charles attend votre réponse', $mail);
        $this->assertSame('Charles a écrit un message.', Notice::query()->for($requester)->where('event', NoticeEvent::Message->value)->sole()->data['line']);
        $this->assertSame(['Charles'], $ticket->recipients()->with('actor')->get()->filter(fn ($r) => $r->actor->instance_uid === self::MASTER_UID)->pluck('actor.name')->values()->all());
    }

    public function test_a_reminder_reaches_whoever_has_the_hand_at_once(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $ticket = $this->askedTicket($requester, $handler);

        // Même sans aucun canal choisi, une relance arrive, par courriel.
        NoticeChannels::save($requester, []);

        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertActionHidden('remind');

        $this->actingAs($handler);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible('remind')
            ->callAction('remind', ['message' => 'Avez-vous pu regarder ?'])
            ->assertHasNoActionErrors();

        $this->assertTrue($ticket->entries()->latest('id')->first()->meta['reminder']);
        Notification::assertSentTo($requester, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Reminder
            && $mail->notice->data['excerpt'] === 'Avez-vous pu regarder ?'
            && $mail->via($requester) === ['mail']);

        // Sans droit `remind`, pas de bouton.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.comment')->create());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertActionHidden('remind');
    }

    public function test_what_does_not_move_for_a_week_is_recalled_to_whoever_has_the_hand(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();

        $stuck = $this->draft($requester, ['title' => 'Bloqué']);
        $this->workflow()->run($stuck, 'submit', ['assignee_user_id' => $handler->getKey()], Context::ui($requester));
        $this->workflow()->run($stuck, 'take-charge', [], Context::ui($handler));

        $paused = $this->draft($requester, ['title' => 'En pause']);
        $this->workflow()->run($paused, 'submit', ['assignee_user_id' => $handler->getKey()], Context::ui($requester));
        $this->workflow()->run($paused, 'take-charge', [], Context::ui($handler));
        $this->workflow()->run($paused, 'pause', ['until' => now()->addMonth()->toDateTimeString()], Context::ui($handler));

        $this->draft($requester, ['title' => 'Brouillon oublié']);

        $this->travel(6)->days();
        $this->artisan('tma:notify --stale')->expectsOutputToContain('0 rappel(s)')->assertSuccessful();

        $this->travel(2)->days();
        $this->artisan('tma:notify --stale --dry-run')->expectsOutputToContain('1 rappel(s)')->assertSuccessful();
        $this->assertFalse(Notice::query()->where('event', NoticeEvent::Stale->value)->exists());

        $this->artisan('tma:notify --stale')->expectsOutputToContain('1 rappel(s)')->assertSuccessful();
        $notice = Notice::query()->where('event', NoticeEvent::Stale->value)->sole();
        $this->assertSame((string) $handler->getKey(), $notice->user_id);
        $this->assertSame($stuck->getKey(), (int) $notice->subject_id);
        $this->assertNull($notice->sent_at); // dans le bilan
        $this->assertStringContainsString('depuis 8 jours', $notice->data['line']);

        // Une fois par semaine tant que rien ne bouge.
        $this->travel(1)->days();
        $this->artisan('tma:notify --stale')->expectsOutputToContain('0 rappel(s)')->assertSuccessful();
        $this->travel(7)->days();
        $this->artisan('tma:notify --stale')->expectsOutputToContain('1 rappel(s)')->assertSuccessful();
        $this->assertSame(2, Notice::query()->where('event', NoticeEvent::Stale->value)->count());

        // Un passage, et le compteur repart.
        $this->workflow()->run($stuck->refresh(), 'request-answer', ['question' => '<p>Un détail ?</p>'], Context::ui($handler));
        $this->travel(8)->days();
        $this->artisan('tma:notify --stale')->assertSuccessful();
        $this->assertSame((string) $requester->getKey(), Notice::query()->where('event', NoticeEvent::Stale->value)->latest('id')->first()->user_id);
    }

    public function test_send_now_sends_the_digests_of_who_has_something_waiting(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $handler = $this->agent();
        $colleague = $this->agent();
        $idle = $this->agent();
        $ticket = $this->draft($requester);
        app(Recipients::class)->add($ticket, Actor::forUser($colleague), RecipientRole::Secondary);
        $this->workflow()->run($ticket, 'submit', ['assignee_user_id' => $handler->getKey()], Context::ui($requester));
        $this->workflow()->run($ticket, 'take-charge', [], Context::ui($handler));

        $this->actingAs($handler);
        Livewire::test(ListTickets::class)
            ->assertActionVisible('tmaSendNotifications')
            ->callAction('tmaSendNotifications')
            ->assertHasNoActionErrors();

        Notification::assertSentTo($colleague, DailyDigest::class);
        Notification::assertNotSentTo($idle, DailyDigest::class);
        $this->assertSame(0, Notice::query()->pending()->count());

        $this->artisan('tma:notify --grouped')->expectsOutputToContain('0 récapitulatif(s)')->assertSuccessful();

        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany')->create());
        Livewire::test(ListTickets::class)->assertActionHidden('tmaSendNotifications');
    }
}
