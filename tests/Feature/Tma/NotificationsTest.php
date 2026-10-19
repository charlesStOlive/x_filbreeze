<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Filament\Pages\EditProfile;
use CharlesStOlive\FilamentPermissionManager\UserOptions\UserOptions;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\Support\Digest;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use CharlesStOlive\FilamentTma\Support\Notifier;
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
    /**
     * Un ticket du Support, dans le maître, en cours : `$handler` en est le contact (choisi à la transmission) et
     * destinataire principal.
     */
    private function takenTicket(User $handler, string $title = 'Bouton d’export', string $number = 'DES-0001'): Ticket
    {
        $ticket = $this->serviceDeskTicket('in_progress', $number);
        $ticket->forceFill(['title' => $title, 'assignee_actor_id' => Actor::forUser($handler)->getKey()])->save();
        app(Recipients::class)->add($ticket, Actor::forUser($handler));

        return $ticket->refresh();
    }

    public function test_the_digest_lists_what_waits_for_each_one_and_nobody_gets_an_empty_mail(): void
    {
        // Dans le maître : les demandes nouvelles que personne n'a prises, et celles qu'on a prises.
        $this->asMaster();
        $support = $this->supportMember();
        $idle = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany')->create();
        $this->serviceDeskTicket('submitted', 'DES-0001');
        $this->takenTicket($support, 'Relances en double', 'DES-0002');

        $this->assertSame(['DES-0001'], Digest::for($support)['take']->pluck('ref')->all());
        $this->assertSame(['DES-0002'], Digest::for($support)['mine']->pluck('ref')->all());
        $this->assertSame([], Digest::for($idle));

        // Dans l'application cliente : les demandes qui attendent la réponse de leur demandeur.
        $this->asClient();
        $requester = $this->correspondent();
        $asked = $this->serviceDeskTicket('awaiting_requester', 'DES-0003');
        $asked->forceFill(['title' => 'Bouton d’export', 'requester_actor_id' => Actor::forUser($requester)->getKey(), 'waiting_since' => now()])->save();
        $this->assertSame(['DES-0003'], Digest::for($requester)['answer']->pluck('ref')->all());

        $this->travel(8)->days();
        $this->assertTrue(Digest::for($requester)['answer']->first()['late']);

        Notification::fake();
        $this->artisan('tma:digest')->expectsOutputToContain('récapitulatif')->assertSuccessful();
        Notification::assertSentTo($requester, DailyDigest::class);
        Notification::assertNotSentTo($idle, DailyDigest::class);

        $mail = (string) (new DailyDigest(Digest::for($requester)))->toMail($requester)->render();
        $this->assertStringContainsString('DES-0003 — Bouton d’export', html_entity_decode($mail));
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
        $this->asMaster();
        $handler = $this->supportMember();
        $colleague = $this->supportMember();
        $outsider = $this->supportMember();

        $ticket = $this->serviceDeskTicket('submitted');
        $ticket->forceFill(['title' => 'Bouton d’export'])->save();
        app(Recipients::class)->add($ticket, Actor::forUser($handler));
        app(Recipients::class)->add($ticket, Actor::forUser($colleague), RecipientRole::Secondary);
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));
        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Le CSV.</p>'], $this->fromClient());

        // Le Support qui a la main, principal : tout de suite, par courriel et dans la cloche ; « Un ticket vous attend ».
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Turn
            && $mail->via($handler) === ['mail', 'database']
            && $mail->notice->data['excerpt'] === 'Le CSV.');
        $database = (new NoticeNotification(Notice::query()->for($handler)->where('event', NoticeEvent::Turn->value)->sole(), ['database']))->toDatabase($handler);
        $this->assertSame('[DES-0001] Bouton d’export', $database['title']);
        $this->assertStringContainsString('Ada Lovelace a répondu', $database['body']);
        $this->assertSame('Ouvrir le ticket', $database['actions'][0]['label']);

        // La collègue, secondaire : rien tout de suite, tout dans son bilan.
        Notification::assertNotSentTo($colleague, NoticeNotification::class);
        $this->assertSame([NoticeEvent::Progress, NoticeEvent::Progress], Notice::query()->pending()->for($colleague)->orderBy('id')->pluck('event')->all());

        // Les autres : rien.
        $this->assertFalse(Notice::query()->for($outsider)->exists());

        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($colleague, DailyDigest::class, function (DailyDigest $mail) use ($colleague): bool {
            $html = html_entity_decode((string) $mail->toMail($colleague)->render());

            return str_contains($html, 'Depuis le dernier envoi') && str_contains($html, 'a demandé des précisions') && str_contains($html, '« Le CSV. »');
        });
        $this->assertSame(0, Notice::query()->pending()->count());
    }

    public function test_each_one_chooses_how_a_primary_notification_reaches_them(): void
    {
        Notification::fake();
        $this->asMaster();
        $handler = $this->supportMember();
        $colleague = $this->supportMember();
        $ticket = $this->takenTicket($handler);
        app(Recipients::class)->add($ticket, Actor::forUser($colleague));

        UserOptions::save($handler, 'tma', ['notifications' => ['channels' => ['database']]]);
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Je regarde ça demain : quelle version ?</p>'], Context::ui($colleague));
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Progress
            && $mail->via($handler) === ['database']);

        // Aucun canal : ce qui le concerne attend son bilan.
        UserOptions::save($handler, 'tma', ['notifications' => ['channels' => []]]);
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Voir le journal.</p>', Context::ui($colleague));
        $this->assertTrue(Notice::query()->pending()->for($handler)->where('event', NoticeEvent::Note->value)->exists());

        // Une note privée ne prévient personne.
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Pour moi seul.</p>', Context::ui($colleague), private: true);
        $this->assertSame(1, Notice::query()->where('event', NoticeEvent::Note->value)->count());

        $mail = html_entity_decode((string) (new NoticeNotification(Notice::query()->for($handler)->where('event', NoticeEvent::Progress->value)->sole()))->toMail($handler)->render());
        $this->assertStringContainsString('a demandé des précisions', $mail);
        $this->assertStringContainsString('« Je regarde ça demain : quelle version ? »', $mail);
        $this->assertStringContainsString('destinataire principal', $mail);
    }

    public function test_my_notifications_are_set_in_the_profile_linked_from_the_ticket_list(): void
    {
        $this->asClient();
        $me = $this->agent();
        $this->actingAs($me);

        Livewire::test(ListTickets::class)
            ->assertActionVisible('tmaNotifications')
            ->assertActionHasUrl('tmaNotifications', filament()->getProfileUrl().'#user-options-tma');

        $this->assertSame(['mail', 'database'], NoticeChannels::for($me));
        $this->assertTrue(NoticeChannels::digest($me));

        Livewire::test(EditProfile::class)
            ->fillForm([
                'userOptions.tma.notifications.channels' => ['mail'],
                'userOptions.tma.notifications.digest' => false,
                'timezone' => 'Europe/Paris',
                'locale' => 'fr_FR',
            ])
            ->call('save')->assertHasNoFormErrors();

        $me->refresh();
        $this->assertSame(['mail'], $me->userOption('tma.notifications.channels'));
        $this->assertFalse($me->userOption('tma.notifications.digest'));
        $this->assertSame(['mail'], NoticeChannels::for($me));
        $this->assertFalse(NoticeChannels::digest($me));

        // L'application peut fixer le réglage pour tous.
        config(['filament-tma.notifications.user_choice' => false]);
        $this->assertSame(['mail', 'database'], NoticeChannels::for($me));
        $this->assertTrue(NoticeChannels::digest($me));
    }

    public function test_the_application_says_what_is_on_until_one_chooses_and_without_the_digest_nothing_waits(): void
    {
        Notification::fake();
        config(['filament-tma.notifications.defaults' => ['database']]);
        $this->asMaster();
        $handler = $this->supportMember();
        $colleague = $this->supportMember();
        $ticket = $this->takenTicket($handler);
        app(Recipients::class)->add($ticket, Actor::forUser($colleague), RecipientRole::Secondary);

        // Rien choisi : la cloche seule, sans bilan.
        $this->assertSame(['database'], NoticeChannels::for($handler));
        $this->assertFalse(NoticeChannels::digest($handler));

        // Sans bilan, ce qui devait y aller est seulement noté.
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));
        $skipped = Notice::query()->for($colleague)->sole();
        $this->assertSame(NoticeMode::Skipped, $skipped->mode);
        $this->assertNotNull($skipped->sent_at);

        // La collègue veut le bilan : ce qui suit l'attend ; le principal, lui, l'apprend dans la cloche.
        UserOptions::save($colleague, 'tma', ['notifications' => ['channels' => [], 'digest' => true]]);
        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Le CSV.</p>'], $this->fromClient());
        $this->assertSame(1, Notice::query()->pending()->for($colleague)->count());
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->via($handler) === ['database']);

        // Sans bilan, pas de rappel de ce qui ne bouge plus.
        $this->assertSame(0, Notifier::stale($ticket->refresh(), 8));

        // Elle le refuse ensuite : rien ne part, ni à elle ni au principal (qui n'a rien choisi), et rien n'attend plus.
        UserOptions::save($colleague, 'tma', ['notifications' => ['channels' => [], 'digest' => false]]);
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertNotSentTo($colleague, DailyDigest::class);
        Notification::assertNotSentTo($handler, DailyDigest::class);
        $this->assertSame(0, Notice::query()->pending()->count());
        $this->assertSame(2, Notice::query()->for($colleague)->where('mode', NoticeMode::Skipped->value)->count());

        // Le principal choisit le bilan : le voilà rappelé, et dans son bilan.
        UserOptions::save($handler, 'tma', ['notifications' => ['channels' => ['database'], 'digest' => true]]);
        $this->assertSame(1, Notifier::stale($ticket->refresh(), 8));
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($handler, DailyDigest::class);
    }

    public function test_what_the_master_sends_is_told_to_the_requester_by_the_client_once(): void
    {
        Notification::fake();
        $this->asClient();
        $requester = $this->agent();
        $ticket = $this->draft($requester);
        $ticket->forceFill(['state' => 'in_progress'])->save();

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
        $this->asMaster();
        $handler = $this->supportMember();
        $ticket = $this->takenTicket($handler);

        // Même sans aucun canal choisi, une relance arrive, par courriel : ici, celle du demandeur, reçue de son application.
        UserOptions::save($handler, 'tma', ['notifications' => ['channels' => []]]);
        app(Timeline::class)->write($ticket, EntryType::Comment, '<p>Avez-vous pu regarder ?</p>', $this->fromClient(), meta: ['reminder' => true]);
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Reminder
            && $mail->notice->data['excerpt'] === 'Avez-vous pu regarder ?'
            && $mail->via($handler) === ['mail']);

        // Le Support ne se relance pas lui-même ; au tour du demandeur, il le relance.
        $this->actingAs($handler);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertActionHidden('remind');
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible('remind')
            ->callAction('remind', ['message' => 'Un retour avant vendredi ?'])
            ->assertHasNoActionErrors();
        $this->assertTrue($ticket->entries()->latest('id')->first()->meta['reminder']);

        // Sans droit `remind`, pas de bouton.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.handle')->create());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertActionHidden('remind');
    }

    public function test_what_does_not_move_for_a_week_is_recalled_to_whoever_has_the_hand(): void
    {
        Notification::fake();
        $this->asMaster();
        $handler = $this->supportMember();

        $stuck = $this->takenTicket($handler, 'Bloqué', 'DES-0001');
        $paused = $this->takenTicket($handler, 'En pause', 'DES-0002');
        $this->workflow()->run($paused, 'pause', ['until' => now()->addMonth()->toDateTimeString()], Context::ui($handler));

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

        // Au tour du demandeur, c'est son application qui le lui rappelle : rien de plus ici.
        $this->workflow()->run($stuck->refresh(), 'request-answer', ['question' => '<p>Un détail ?</p>'], Context::ui($handler));
        $this->travel(8)->days();
        $this->artisan('tma:notify --stale')->assertSuccessful();
        $this->assertSame(2, Notice::query()->where('event', NoticeEvent::Stale->value)->count());
    }

    public function test_send_now_sends_the_digests_of_who_has_something_waiting(): void
    {
        Notification::fake();
        $this->asMaster();
        $handler = $this->supportMember('tma.ticket.notify');
        $colleague = $this->supportMember();
        $idle = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        app(Recipients::class)->add($ticket, Actor::forUser($colleague), RecipientRole::Secondary);
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));

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
