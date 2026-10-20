<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Filament\Pages\EditProfile;
use CharlesStOlive\FilamentPermissionManager\UserOptions\UserOptions;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\Notifications\WeeklyDigest;
use CharlesStOlive\FilamentTma\Support\Digest;
use CharlesStOlive\FilamentTma\Support\MailBrand;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use CharlesStOlive\FilamentTma\Support\Notifier;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
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
        $this->desapp();
        $ticket = $this->serviceDeskTicket('in_progress', $number);
        $ticket->forceFill(['title' => $title, 'assignee_actor_id' => Actor::forUser($handler)->getKey()])->save();
        app(Recipients::class)->add($ticket, Actor::forUser($handler));

        return $ticket->refresh();
    }

    /** L'application cliente, vue du maître : son nom court nomme ses personnes (« Ada Lovelace (EDM) »). */
    private function desapp(): Instance
    {
        return Instance::query()->firstOrCreate(['uid' => self::CLIENT_UID], ['name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
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

    public function test_the_digests_and_the_reminders_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events());
        $digest = $events->first(fn ($e) => str_contains((string) $e->command, 'tma:digest') && ! str_contains((string) $e->command, '--weekly'));
        $weekly = $events->first(fn ($e) => str_contains((string) $e->command, 'tma:digest --weekly'));
        $stale = $events->first(fn ($e) => str_contains((string) $e->command, 'tma:notify') && str_contains((string) $e->command, '--stale'));

        $this->assertSame('0 8 * * 1,2,3,4,5', $digest?->expression);
        $this->assertSame('0 8 * * 1', $weekly?->expression);
        $this->assertSame('30 7 * * 1,2,3,4,5', $stale?->expression);
    }

    public function test_a_ticket_that_waits_for_a_primary_is_mailed_the_other_primaries_see_the_bell_and_a_secondary_the_digest(): void
    {
        Notification::fake();
        $this->asMaster();
        $handler = $this->supportMember();
        $colleague = $this->supportMember();
        $copied = $this->supportMember();
        $outsider = $this->supportMember();

        $this->desapp();
        $ticket = $this->serviceDeskTicket('submitted');
        $ticket->forceFill(['title' => 'Bouton d’export', 'assignee_actor_id' => Actor::forUser($handler)->getKey()])->save();
        app(Recipients::class)->add($ticket, Actor::forUser($handler));
        app(Recipients::class)->add($ticket, Actor::forUser($colleague));
        app(Recipients::class)->add($ticket, Actor::forUser($copied), RecipientRole::Secondary);
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));
        $this->workflow()->run($ticket, 'answer', ['answer' => '<p>Le CSV.</p>'], $this->fromClient());

        // Le contact, à qui la main revient : une action, par courriel (l'e-mail direct est actif d'origine ici) et dans la cloche.
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Turn
            && $mail->via($handler) === ['database', 'mail']
            && $mail->notice->data['tone'] === 'action'
            && $mail->notice->data['action'] === 'Reprendre le traitement avec cette réponse.'
            && $mail->notice->data['excerpt'] === 'Le CSV.');
        $database = (new NoticeNotification(Notice::query()->for($handler)->where('event', NoticeEvent::Turn->value)->sole(), ['database']))->toDatabase($handler);
        $this->assertSame('[DES-0001] Bouton d’export', $database['title']);
        $this->assertStringContainsString('Ada Lovelace (EDM) a répondu', $database['body']);
        $this->assertStringContainsString('À faire : reprendre le traitement', $database['body']);
        $this->assertSame('Ouvrir le ticket', $database['actions'][0]['label']);

        // L'autre principal : pour information, dans la cloche seulement, deux fois.
        Notification::assertSentToTimes($colleague, NoticeNotification::class, 2);
        Notification::assertSentTo($colleague, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->via($colleague) === ['database']);
        $this->assertSame(0, Notice::query()->pending()->for($colleague)->count());

        // Le secondaire : rien tout de suite, tout dans son bilan, sous « En copie ».
        Notification::assertNotSentTo($copied, NoticeNotification::class);
        $this->assertSame([NoticeEvent::Progress, NoticeEvent::Progress], Notice::query()->pending()->for($copied)->orderBy('id')->pluck('event')->all());

        // Les autres : rien.
        $this->assertFalse(Notice::query()->for($outsider)->exists());

        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($copied, DailyDigest::class, function (DailyDigest $mail) use ($copied): bool {
            $html = html_entity_decode((string) $mail->toMail($copied)->render());

            return str_contains($html, 'En copie') && str_contains($html, 'a demandé des précisions') && str_contains($html, '« Le CSV. »')
                && $mail->toMail($copied)->subject === 'Bilan du matin : 1 en copie';
        });
        $this->assertSame(0, Notice::query()->pending()->count());
    }

    public function test_a_new_demand_without_contact_waits_for_the_whole_support(): void
    {
        Notification::fake();
        $this->asMaster();
        $charles = $this->supportMember();
        $grace = $this->supportMember();
        config(['filament-tma.recipients.support_defaults' => [$charles->email, $grace->email]]);
        $service = User::factory()->create();
        $this->desapp()->update(['api_user_id' => $service->getKey()]);
        Passport::actingAs($service, [], 'api');

        // Le client transmet sans choisir de contact, au Support (son compte passerelle).
        $requester = ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'];
        $this->postJson('/api/tma/v1/tickets', [
            'ticket' => ['ulid' => strtolower((string) Str::ulid()), 'number' => 'DES-0042', 'source_instance_uid' => self::CLIENT_UID, 'created_on_instance_uid' => self::CLIENT_UID,
                'title' => 'Sans contact', 'state' => 'draft', 'requester' => $requester, 'created_by' => $requester, 'version' => 0],
            'entries' => [],
            'transition' => ['ulid' => strtolower((string) Str::ulid()), 'move' => 'transmit', 'from_state' => 'draft', 'to_state' => 'submitted', 'actor' => $requester, 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui'],
            'recipients' => [['support' => true, 'role' => 'primary']],
        ])->assertCreated();
        $this->assertNull(Ticket::query()->sole()->assignee_actor_id);

        foreach ([$charles, $grace] as $member) {
            Notification::assertSentTo($member, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Turn
                && $mail->via($member) === ['database', 'mail']
                && $mail->notice->data['headline'] === 'Nouvelle demande de Ada Lovelace (EDM)');
        }
    }

    public function test_without_direct_mail_a_ticket_that_waits_for_you_reaches_you_in_the_bell_and_the_morning_digest(): void
    {
        Notification::fake();
        $this->asMaster();
        $handler = $this->supportMember();
        $colleague = $this->supportMember();
        $ticket = $this->takenTicket($handler);
        app(Recipients::class)->add($ticket, Actor::forUser($colleague));

        UserOptions::save($handler, 'tma', ['notifications' => ['channels' => [], 'digest' => true]]);
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quelle version ?</p>'], Context::ui($colleague));
        $this->workflow()->run($ticket->refresh(), 'answer', ['answer' => '<p>La 2.</p>'], $this->fromClient());

        $turn = Notice::query()->for($handler)->where('event', NoticeEvent::Turn->value)->sole();
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->is($turn) && $mail->via($handler) === ['database']);
        $this->assertNull($turn->sent_at); // dans le bilan

        // Un fait sans action (la question d'un collègue) : la cloche seulement, pas le bilan.
        $this->assertSame(NoticeMode::Immediate, Notice::query()->for($handler)->where('event', NoticeEvent::Progress->value)->sole()->mode);

        // Une note privée ne prévient personne ; une note, pour information.
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Pour moi seul.</p>', Context::ui($colleague), private: true);
        app(Timeline::class)->add($ticket, EntryType::Note, '<p>Voir le journal.</p>', Context::ui($colleague));
        $this->assertSame(1, Notice::query()->where('event', NoticeEvent::Note->value)->count());

        // Le bilan : le ticket sous « À faire », avec ce qui s'y est passé.
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($handler, DailyDigest::class, function (DailyDigest $mail) use ($handler): bool {
            $message = $mail->toMail($handler);
            $html = html_entity_decode((string) $message->render());

            return $message->subject === 'Bilan du matin : 1 à faire'
                && str_contains($html, 'À faire') && str_contains($html, 'DES-0001 — Bouton d’export')
                && str_contains($html, 'Ada Lovelace (EDM) a répondu.') && str_contains($html, '« La 2. »');
        });
    }

    public function test_my_notifications_are_set_in_the_profile_linked_from_the_ticket_list(): void
    {
        $this->asClient();
        $me = $this->agent();
        $this->actingAs($me);

        Livewire::test(ListTickets::class)
            ->assertActionVisible('tmaNotifications')
            ->assertActionHasUrl('tmaNotifications', filament()->getProfileUrl().'#user-options-tma');

        // Ce que l'application met d'origine ici : l'e-mail direct, et les deux bilans.
        $this->assertTrue(NoticeChannels::mail($me));
        $this->assertTrue(NoticeChannels::digest($me));
        $this->assertTrue(NoticeChannels::weekly($me));

        // Ni e-mail direct ni bilan du matin : un ticket qui attend ne parviendrait pas.
        Livewire::test(EditProfile::class)
            ->assertSee(['E-mail direct', 'Bilan du matin', 'Bilan de la semaine'])
            ->fillForm([
                'userOptions.tma.notifications.channels' => false,
                'userOptions.tma.notifications.digest' => false,
                'timezone' => 'Europe/Paris',
                'locale' => 'fr_FR',
            ])
            ->call('save')->assertHasFormErrors(['userOptions.tma.notifications.digest']);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'userOptions.tma.notifications.channels' => true,
                'userOptions.tma.notifications.digest' => false,
                'userOptions.tma.notifications.weekly' => false,
                'timezone' => 'Europe/Paris',
                'locale' => 'fr_FR',
            ])
            ->call('save')->assertHasNoFormErrors();

        $me->refresh();
        $this->assertSame(['mail'], $me->userOption('tma.notifications.channels'));
        $this->assertTrue(NoticeChannels::mail($me));
        $this->assertFalse(NoticeChannels::digest($me));
        $this->assertFalse(NoticeChannels::weekly($me));

        // Un ancien choix (la cloche et le courriel, d'avant la cloche obligatoire) reste valable.
        UserOptions::save($me, 'tma', ['notifications' => ['channels' => ['database'], 'digest' => true]]);
        $this->assertFalse(NoticeChannels::mail($me->refresh()));
        Livewire::test(EditProfile::class)->assertFormSet(['userOptions.tma.notifications.channels' => false, 'userOptions.tma.notifications.digest' => true]);

        // L'application peut fixer le réglage pour tous.
        config(['filament-tma.notifications.user_choice' => false]);
        $this->assertTrue(NoticeChannels::mail($me));
        $this->assertTrue(NoticeChannels::digest($me));
    }

    public function test_the_application_says_what_is_on_until_one_chooses_and_without_the_digest_nothing_waits(): void
    {
        Notification::fake();
        // L'origine du module : la cloche et le bilan du matin, sans e-mail direct.
        config(['filament-tma.notifications.defaults' => ['digest', 'weekly']]);
        $this->asMaster();
        $handler = $this->supportMember();
        $colleague = $this->supportMember();
        $ticket = $this->takenTicket($handler);
        app(Recipients::class)->add($ticket, Actor::forUser($colleague), RecipientRole::Secondary);

        $this->assertFalse(NoticeChannels::mail($handler));
        $this->assertTrue(NoticeChannels::digest($handler));

        // La main revient au contact : la cloche, et son bilan.
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Quel format ?</p>'], Context::ui($handler));
        $this->workflow()->run($ticket->refresh(), 'answer', ['answer' => '<p>Le CSV.</p>'], $this->fromClient());
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->via($handler) === ['database']);
        $this->assertSame(1, Notice::query()->pending()->for($handler)->count());

        // La collègue refuse le bilan : ce qui devait y aller est seulement noté.
        UserOptions::save($colleague, 'tma', ['notifications' => ['channels' => ['mail'], 'digest' => false]]);
        $this->workflow()->run($ticket->refresh(), 'pause', ['until' => now()->addDay()->toDateTimeString()], Context::ui($handler));
        $this->assertSame(NoticeMode::Skipped, Notice::query()->for($colleague)->latest('id')->first()->mode);

        // Sans bilan, pas de rappel de ce qui ne bouge plus.
        UserOptions::save($handler, 'tma', ['notifications' => ['channels' => ['mail'], 'digest' => false]]);
        $this->workflow()->run($ticket->refresh(), 'resume', [], Context::ui($handler));
        $this->assertSame(0, Notifier::stale($ticket->refresh(), 8));

        // Au bilan, rien ne part à qui n'en veut pas, et rien n'attend plus.
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertNotSentTo($colleague, DailyDigest::class);
        Notification::assertNotSentTo($handler, DailyDigest::class);
        $this->assertSame(0, Notice::query()->pending()->count());
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
            'ulid' => $move = strtolower((string) Str::ulid()), 'move' => 'request-answer', 'from_state' => 'in_progress', 'to_state' => 'awaiting_requester',
            'actor' => $charles, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui',
        ];
        $snapshot['entries'][] = [
            'ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>Sur quelle page ?</p>', 'meta' => ['transition' => $move, 'question' => true],
            'author' => $charles, 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui', 'created_at' => now()->toIso8601String(),
        ];
        $snapshot['recipients'][] = ['actor' => $charles, 'role' => 'primary', 'support' => false];

        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        // La question, une fois, au demandeur : ce qu'on attend de lui, et quand le ticket se fermerait sans réponse.
        Notification::assertSentToTimes($requester, NoticeNotification::class, 1);
        $notice = Notice::query()->for($requester)->where('event', NoticeEvent::Turn->value)->sole();
        $mail = (new NoticeNotification($notice))->toMail($requester);
        $html = html_entity_decode((string) $mail->render());
        $this->assertSame('[DES-0001] Le Support attend votre réponse – Le formulaire de contact ne part plus', $mail->subject);
        $this->assertStringContainsString('Charles (Support) attend votre réponse.', $html);
        $this->assertStringContainsString('Répondre à la question, dans le ticket.', $html);
        $this->assertStringContainsString('« Sur quelle page ? »', html_entity_decode((string) view($mail->view[1], $mail->viewData)->render()));
        $this->assertStringContainsString('Sans réponse, le ticket sera clos automatiquement le '.now()->addDays(28)->timezone('Europe/Paris')->format('d/m/Y'), $html);
        $this->assertSame(['Charles'], $ticket->recipients()->with('actor')->get()->filter(fn ($r) => $r->actor->instance_uid === self::MASTER_UID)->pluck('actor.name')->values()->all());
    }

    public function test_a_reminder_reaches_whoever_has_the_hand_at_once(): void
    {
        Notification::fake();
        $this->asMaster();
        $handler = $this->supportMember();
        $ticket = $this->takenTicket($handler);

        // Même sans e-mail direct, une relance arrive par courriel : ici, celle du demandeur, reçue de son application.
        UserOptions::save($handler, 'tma', ['notifications' => ['channels' => [], 'digest' => true]]);
        app(Timeline::class)->write($ticket, EntryType::Comment, '<p>Avez-vous pu regarder ?</p>', $this->fromClient(), meta: ['reminder' => true]);
        Notification::assertSentTo($handler, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->event === NoticeEvent::Reminder
            && $mail->notice->data['excerpt'] === 'Avez-vous pu regarder ?'
            && $mail->via($handler) === ['database', 'mail']
            && str_contains(html_entity_decode((string) $mail->toMail($handler)->render()), 'Une relance arrive toujours par courriel.'));

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

    /**
     * Une tâche publiée dans le maître, pour Ada (principale) et Grace (secondaire), et ce que le client en reçoit :
     * l'état complet que le maître lui envoie.
     *
     * @return array{0: ProjectTask, 1: array<string, mixed>}
     */
    private function publishedTaskSnapshot(): array
    {
        $this->asMaster();
        $this->desapp();
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $project = Project::create(['title' => 'Nouvel environnement graphique', 'target_instance_uid' => self::CLIENT_UID]);
        $author = User::factory()->withPermissions('panels.admin.access', 'tma.task.*')->create(['name' => 'Charles']);
        $task = app(TaskWorkflow::class)->create(['title' => 'Phase 1 : la nouvelle maquette', 'project_id' => $project->getKey(), 'due_on' => '2026-11-20'], Context::ui($author));
        app(Recipients::class)->add($task, Actor::fromInstance(self::CLIENT_UID, '7', 'Ada Lovelace', 'ada@client.test'));
        app(Recipients::class)->add($task, Actor::fromInstance(self::CLIENT_UID, '8', 'Grace Hopper', 'grace@client.test'), RecipientRole::Secondary);
        app(TaskWorkflow::class)->run($task, 'publish', [], Context::ui($author));

        return [$task->refresh(), Payload::snapshot($task->refresh())];
    }

    /** Joue le client qui reçoit ce que le maître lui envoie. */
    private function receivedAsClient(array $snapshot): void
    {
        $this->asClient();
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        Passport::actingAs(User::query()->firstWhere('email', 'filbreeze@api.test') ?? User::factory()->create(['email' => 'filbreeze@api.test']), [], 'api');
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
    }

    public function test_a_task_informs_the_client_in_the_bell_and_the_weekly_digest_never_by_mail(): void
    {
        [$task, $snapshot] = $this->publishedTaskSnapshot();
        $task->recipients()->delete();
        $task->transitions()->delete();
        $task->forceDelete();
        Notice::query()->delete();

        Notification::fake();
        $ada = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create(['id' => 7, 'name' => 'Ada Lovelace']);
        $grace = User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.view')->create(['id' => 8, 'name' => 'Grace Hopper']);

        // La publication : la cloche pour Ada (l'e-mail direct ne vaut que pour les tickets), le bilan de la semaine pour les deux.
        $this->receivedAsClient($snapshot);
        Notification::assertSentTo($ada, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->via($ada) === ['database']
            && $mail->notice->data['line'] === 'Le Support a publié une tâche pour vous.');
        Notification::assertNotSentTo($grace, NoticeNotification::class);

        // Du temps saisi, puis la tâche modifiée : dits sans passage ni message.
        $snapshot['task'] = [...$snapshot['task'], 'version' => $snapshot['task']['version'] + 1, 'time' => ['total_minutes' => 180, 'by_month' => [now()->format('Y-m') => 180]]];
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $snapshot['task'] = [...$snapshot['task'], 'version' => $snapshot['task']['version'] + 1, 'due_on' => '2026-11-27', 'title' => 'Phase 1 : la maquette'];
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        $lines = Notice::query()->for($ada)->where('event', NoticeEvent::Task->value)->orderBy('id')->get()->pluck('data.line')->all();
        $this->assertCount(3, $lines);
        $this->assertStringStartsWith('Temps passé sur la tâche : ', $lines[1]);
        $this->assertSame('Le Support a modifié la tâche (titre, échéance).', $lines[2]);

        // « Informer par courriel » : un courriel à Ada, même sans e-mail direct ; Grace l'a dans son bilan.
        UserOptions::save($ada, 'tma', ['notifications' => ['channels' => [], 'digest' => true, 'weekly' => true]]);
        $snapshot['entries'][] = [
            'ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>La maquette est en ligne sur la préproduction.</p>', 'meta' => ['notify' => true],
            'author' => ['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles'], 'origin_instance_uid' => self::MASTER_UID, 'origin' => 'ui',
            'created_at' => now()->toIso8601String(),
        ];
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        Notification::assertSentTo($ada, NoticeNotification::class, function (NoticeNotification $mail) use ($ada): bool {
            $message = $mail->toMail($ada);
            $html = html_entity_decode((string) $message->render());

            return $mail->via($ada) === ['database', 'mail']
                && $message->subject === '[T-0001] Le Support vous informe – Phase 1 : la maquette'
                && str_contains($html, 'Pour information') && str_contains($html, 'Aucune action n’est attendue de votre part.')
                && str_contains($html, 'La maquette est en ligne sur la préproduction.')
                && str_contains($html, 'Nouvel environnement graphique');
        });
        Notification::assertNotSentTo($grace, NoticeNotification::class);

        // Le bilan du matin ne parle pas des tâches ; celui de la semaine, si.
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertNotSentTo($ada, DailyDigest::class);
        $this->artisan('tma:digest --weekly')->expectsOutputToContain('2 récapitulatif(s)')->assertSuccessful();
        Notification::assertSentTo($ada, WeeklyDigest::class, function (WeeklyDigest $mail) use ($ada): bool {
            $message = $mail->toMail($ada);
            $html = html_entity_decode((string) $message->render());

            return $message->subject === 'Bilan de la semaine : 1 tâche de vos projets'
                && str_contains($html, 'Nouvel environnement graphique') && str_contains($html, 'T-0001 — Phase 1 : la maquette')
                && str_contains($html, 'Échéance : 27/11/2026') && str_contains($html, 'Le Support a publié une tâche pour vous.')
                && ! str_contains($html, 'En copie');
        });
        Notification::assertSentTo($grace, WeeklyDigest::class, fn (WeeklyDigest $mail): bool => str_contains(html_entity_decode((string) $mail->toMail($grace)->render()), 'En copie'));
        $this->assertSame(0, Notice::query()->pending()->count());
    }

    public function test_the_support_hears_of_a_task_only_when_the_client_writes_on_it(): void
    {
        Notification::fake();
        [$task] = $this->publishedTaskSnapshot();
        $author = User::query()->firstWhere('name', 'Charles');
        $colleague = User::factory()->withPermissions('panels.admin.access', 'tma.task.*')->create();
        app(Recipients::class)->add($task, Actor::forUser($colleague));

        // Ce que le Support fait sur une tâche ne prévient personne chez lui.
        app(TaskWorkflow::class)->run($task, 'complete', [], Context::ui($author));
        app(Timeline::class)->add($task->refresh(), EntryType::Comment, '<p>Livrée.</p>', Context::ui($author));
        Notification::assertNothingSent();

        // Le client commente : c'est à voir, par courriel pour qui a l'e-mail direct.
        $service = User::factory()->create();
        $this->desapp()->update(['api_user_id' => $service->getKey()]);
        Passport::actingAs($service, [], 'api');
        $this->postJson("/api/tma/v1/tasks/{$task->ulid}/entries", [
            'ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>Peut-on voir la version mobile ?</p>',
            'author' => ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'], 'origin_instance_uid' => self::CLIENT_UID,
        ])->assertCreated();

        foreach ([$author, $colleague] as $support) {
            Notification::assertSentTo($support, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->via($support) === ['database', 'mail']
                && $mail->notice->data['headline'] === 'Ada Lovelace (EDM) a commenté la tâche'
                && $mail->notice->data['action'] === 'Lui répondre dans la tâche, si besoin.');
        }
    }

    public function test_the_support_informs_the_client_by_mail_from_a_task(): void
    {
        [$task] = $this->publishedTaskSnapshot();
        $this->actingAs(User::query()->firstWhere('name', 'Charles'));

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertActionVisible('informByMail')
            ->callAction('informByMail', ['message' => 'La maquette est en ligne.'])
            ->assertHasNoActionErrors();

        $entry = $task->entries()->sole();
        $this->assertTrue($entry->meta['notify']);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/sync') && collect($request['entries'])->contains(fn (array $sent): bool => ! empty($sent['meta']['notify'])));

        // Pas sur un brouillon, ni chez le client.
        $draft = app(TaskWorkflow::class)->create(['title' => 'Phase 2', 'project_id' => $task->project_id, 'publish_at' => now()->addDay()], Context::ui(auth()->user()));
        Livewire::test(ViewTask::class, ['record' => $draft->getKey()])->assertActionHidden('informByMail');
    }

    public function test_tickets_and_tasks_have_their_own_mail_layout_with_the_logo(): void
    {
        $this->asClient();
        $user = $this->correspondent();
        $ticket = $this->draft($user);
        $notice = fn (array $data, string $kind) => Notice::query()->create([
            'user_id' => (string) $user->getKey(), 'key' => Str::random(), 'event' => NoticeEvent::Turn,
            'subject_type' => $ticket->getMorphClass(), 'subject_id' => $ticket->getKey(), 'mode' => NoticeMode::Immediate,
            'data' => ['kind' => $kind, 'ref' => 'DES-0001', 'title' => 'Export des dons', 'state' => 'En attente du demandeur', 'line' => 'Charles (Support) attend votre réponse.', ...$data],
            'sent_at' => now(), 'created_at' => now(),
        ]);

        $ticketMail = (new NoticeNotification($notice(['tone' => 'action', 'headline' => 'Le Support attend votre réponse', 'action' => 'Répondre à la question, dans le ticket.', 'button' => 'Répondre'], 'ticket')))->toMail($user);
        $html = html_entity_decode((string) $ticketMail->render());
        $this->assertStringContainsString(rtrim(config('app.url'), '/').'/images/logo.png', $html);
        $this->assertStringContainsString(MailBrand::color('ticket'), $html);
        $this->assertStringContainsString('À faire', $html);
        $this->assertStringContainsString('>Répondre</a>', $html);
        $this->assertStringContainsString('Ticket DES-0001 · Support', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $text = html_entity_decode((string) view($ticketMail->view[1], $ticketMail->viewData)->render());
        $this->assertStringContainsString('À FAIRE : Répondre à la question, dans le ticket.', $text);

        $taskMail = (new NoticeNotification($notice(['tone' => 'info', 'project' => 'Portail parrain'], 'task')))->toMail($user);
        $html = html_entity_decode((string) $taskMail->render());
        $this->assertStringContainsString(MailBrand::color('task'), $html);
        $this->assertStringContainsString('Pour information', $html);
        $this->assertStringContainsString('Tâche DES-0001 · Portail parrain', $html);
        $this->assertStringNotContainsString('À faire', $html);

        // Un texte toujours lisible sur la couleur ; les couleurs de filament-ui, sinon les siennes.
        $this->assertSame('#111827', MailBrand::onColor('#86B918'));
        $this->assertSame('#FFFFFF', MailBrand::onColor('#1E3A8A'));
        config(['filament-tma.mail.colors.ticket' => '#123456', 'filament-ui.colors.secondary' => 'pas une couleur']);
        $this->assertSame('#123456', MailBrand::color('ticket'));
        $this->assertSame('#475569', MailBrand::color('task'));
        config(['filament-tma.mail.logo' => 'images/absent.png']);
        $this->assertNull(MailBrand::logo());
    }
}
