<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\UserOptions\UserOptions;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Widgets\MySupportWidget;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\Notifications\WeeklyDigest;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Questions;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/**
 * Les questions du Support sur une tâche projet : une tâche informe, une question engage. Le Support la pose dans la
 * tâche à des personnes du client ; elles l'apprennent comme un ticket qui les attend, jusqu'à ce que quelqu'un du
 * client y réponde ou que le Support la retire. La tâche ne change pas d'état.
 */
class TaskQuestionsTest extends TmaTestCase
{
    private const QUESTION = '<p>Préférez-vous le menu à gauche ou en haut ?</p>';

    /** Une tâche publiée dans le maître, pour Ada (principale) et Grace (secondaire), par Charles. */
    private function publishedTask(): ProjectTask
    {
        $this->asMaster();
        Instance::query()->firstOrCreate(['uid' => self::CLIENT_UID], ['name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $project = Project::create(['title' => 'Nouvel environnement graphique', 'target_instance_uid' => self::CLIENT_UID]);
        $charles = User::factory()->withPermissions('panels.admin.access', 'tma.task.*')->create(['name' => 'Charles']);
        $task = app(TaskWorkflow::class)->create(['title' => 'Phase 1 : la nouvelle maquette', 'project_id' => $project->getKey()], Context::ui($charles));
        app(Recipients::class)->add($task, Actor::fromInstance(self::CLIENT_UID, '7', 'Ada Lovelace', 'ada@client.test'));
        app(Recipients::class)->add($task, Actor::fromInstance(self::CLIENT_UID, '8', 'Grace Hopper', 'grace@client.test'), RecipientRole::Secondary);
        app(TaskWorkflow::class)->run($task, 'publish', [], Context::ui($charles));

        return $task->refresh();
    }

    private function charles(): User
    {
        return User::query()->firstWhere('name', 'Charles');
    }

    /**
     * Le client reçoit l'état que le maître lui envoie (la copie du maître est retirée d'abord : les deux applications
     * partagent ici la même base). Ada et Grace y ont un compte.
     *
     * @return array{0: User, 1: User}
     */
    private function asClientReceiving(array $snapshot): array
    {
        ProjectTask::query()->where('ulid', $snapshot['task']['ulid'])->get()->each(function (ProjectTask $task): void {
            $task->entries()->withTrashed()->forceDelete();
            $task->recipients()->delete();
            $task->transitions()->delete();
            $task->forceDelete();
        });
        Notice::query()->delete();

        $this->asClient();
        config(['filament-tma.api.master_user' => 'filbreeze@api.test', 'filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton']);
        $permissions = ['panels.admin.access', 'tma.task.viewany', 'tma.task.view', 'tma.task.comment'];
        $ada = User::factory()->withPermissions(...$permissions)->create(['id' => 7, 'name' => 'Ada Lovelace']);
        $grace = User::factory()->withPermissions(...$permissions)->create(['id' => 8, 'name' => 'Grace Hopper']);
        // L'e-mail direct coupé : la question attend dans la cloche et le bilan du matin.
        UserOptions::save($ada, 'tma', ['notifications' => ['channels' => [], 'digest' => true, 'weekly' => true]]);

        $this->receive($snapshot);

        return [$ada, $grace];
    }

    private function receive(array $snapshot): void
    {
        Passport::actingAs(User::query()->firstWhere('email', 'filbreeze@api.test') ?? User::factory()->create(['email' => 'filbreeze@api.test']), [], 'api');
        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();
        // Passport a pris la garde par défaut : les pages reviennent à celle du panel.
        auth()->shouldUse('web');
    }

    private static function composer(string $name): TestAction
    {
        return TestAction::make($name)->schemaComponent('composerActions', schema: 'composer');
    }

    public function test_the_support_asks_a_question_from_the_task_to_the_client_primaries_by_default(): void
    {
        $task = $this->publishedTask();
        $this->actingAs($this->charles());

        // À qui la poser : les destinataires du client, les principaux d'office.
        $this->assertSame(['7' => 'Ada Lovelace', '8' => 'Grace Hopper (en copie)'], Questions::addressables($task));
        $this->assertSame(['7'], Questions::defaultAddressees($task));

        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Vous attendez une réponse du client ? « Poser une question ».')
            ->assertActionVisible(self::composer('composerQuestion'))
            ->fillForm(['body' => self::QUESTION], 'composer')
            ->mountAction(self::composer('composerQuestion'))
            ->assertActionDataSet(['to' => ['7']])
            ->setActionData(['to' => ['7', '8']])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSee('Question : en attente de réponse')
            ->assertSee('Question à Ada Lovelace, Grace Hopper');

        $question = $task->entries()->sole();
        $this->assertTrue($question->isQuestion());
        $this->assertSame(['7', '8'], Questions::to($question));
        $this->assertSame(Questions::OPEN, Questions::status($question));
        $this->assertSame('in_progress', $task->refresh()->state::getMorphClass());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync')
            && collect($request->data()['entries'] ?? [])->contains(fn (array $sent): bool => ($sent['meta']['question']['to'] ?? null) === ['7', '8']));

        // Sans texte, pas de question ; personne de l'autre côté : refusé.
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->callAction(self::composer('composerQuestion'), ['to' => ['7']]);
        $this->assertSame(1, $task->entries()->count());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(Questions::class)->ask($task, self::QUESTION, ['99'], Context::ui($this->charles()));
    }

    public function test_only_the_support_asks_on_a_published_task(): void
    {
        $task = $this->publishedTask();
        $this->actingAs($this->charles());
        $draft = app(TaskWorkflow::class)->create(['title' => 'Phase 2', 'project_id' => $task->project_id], Context::ui($this->charles()));

        Livewire::test(ViewTask::class, ['record' => $draft->getKey()])->assertDontSee('Poser une question');
        $this->assertTrue(app(Questions::class)->canAsk($task, Context::ui($this->charles())));

        $this->asClient();
        $this->assertFalse(app(Questions::class)->canAsk($task, Context::ui($this->charles())));
    }

    public function test_the_client_learns_a_question_as_something_waiting_for_whom_it_is_asked(): void
    {
        $task = $this->publishedTask();
        app(Questions::class)->ask($task, self::QUESTION, ['7'], Context::ui($this->charles()));
        $snapshot = Payload::snapshot($task->refresh());

        Notification::fake();
        [$ada, $grace] = $this->asClientReceiving($snapshot);

        // Ada : la cloche, et son bilan du matin (pas d'e-mail direct) ; Grace, en copie de la tâche : pour information.
        Notification::assertSentTo($ada, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->via($ada) === ['database']
            && $mail->notice->event === NoticeEvent::Question
            && $mail->notice->data['line'] === 'Charles (Support) vous pose une question.');
        $this->assertSame(NoticeMode::Grouped, Notice::query()->for($ada)->where('event', NoticeEvent::Question->value)->sole()->mode);
        $this->assertSame('digest', Notice::query()->for($ada)->where('event', NoticeEvent::Question->value)->sole()->data['digest']);
        Notification::assertNotSentTo($grace, NoticeNotification::class);
        $this->assertSame('Charles (Support) a posé une question à Ada Lovelace.', Notice::query()->for($grace)->latest('id')->first()->data['line']);

        // La chronologie le dit, et Ada a le bouton pour répondre.
        $question = ProjectTask::query()->sole()->entries()->sole();
        $this->assertSame([$question->getKey()], Questions::openFor($ada)->modelKeys());
        $this->assertSame([], Questions::openFor($grace)->modelKeys());
        $this->actingAs($ada);
        Livewire::test(ViewTask::class, ['record' => $question->subject_id])
            ->assertSee('Question : en attente de réponse')
            ->assertActionVisible(TestAction::make('answerQuestion')->arguments(['entry' => $question->getKey()]))
            ->assertActionHidden(TestAction::make('withdrawQuestion')->arguments(['entry' => $question->getKey()]))
            ->assertDontSee('Poser une question');
        // Le tableau de bord la compte, à qui voit les projets (l'onglet le dit, à côté de celui des tickets).
        $ada->givePermissionTo(Permission::findOrCreate('tma.project.viewany'), Permission::findOrCreate('tma.ticket.viewany'));
        Livewire::test(MySupportWidget::class)
            ->set('tab', MySupportWidget::PROJECTS)
            ->assertSee('Questions pour vous')
            ->assertSee('Le nombre : les questions du Support qui vous attendent');

        // Le bilan du matin la met dans « À faire », avec son texte, et pas dans les nouveautés.
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertSentTo($ada, DailyDigest::class, function (DailyDigest $mail) use ($ada): bool {
            $message = $mail->toMail($ada);
            $html = html_entity_decode((string) $message->render());

            return $message->subject === 'Bilan du matin : 1 à faire'
                && str_contains($html, '1 question du Support attend votre réponse.')
                && str_contains($html, 'Les questions du Support sur vos projets')
                && str_contains($html, 'T-0001 — Phase 1 : la nouvelle maquette')
                && str_contains($html, 'Charles (Support) vous demande :') && str_contains($html, 'Préférez-vous le menu à gauche ou en haut ?')
                && ! str_contains($html, 'Nouveautés');
        });
        Notification::assertNotSentTo($grace, DailyDigest::class);

        // Le bilan de la semaine la signale en tête et sur sa tâche.
        $this->artisan('tma:digest --weekly')->assertSuccessful();
        Notification::assertSentTo($ada, WeeklyDigest::class, function (WeeklyDigest $mail) use ($ada): bool {
            $html = html_entity_decode((string) $mail->toMail($ada)->render());

            return str_contains($html, '1 question du Support attend votre réponse (elle est dans votre bilan du matin)')
                && str_contains($html, 'À vous de répondre : 1 question')
                && str_contains($html, 'Le Support a ajouté cette tâche au projet.');
        });
    }

    public function test_with_direct_mail_a_question_is_mailed_as_something_to_do(): void
    {
        $task = $this->publishedTask();
        app(Questions::class)->ask($task, self::QUESTION, ['7'], Context::ui($this->charles()));
        $snapshot = Payload::snapshot($task->refresh());

        Notification::fake();
        [$ada] = $this->asClientReceiving($snapshot);
        Notice::query()->delete();
        UserOptions::save($ada, 'tma', ['notifications' => ['channels' => ['mail'], 'digest' => true, 'weekly' => true]]);
        ProjectTask::query()->sole()->entries()->forceDelete();
        $this->receive($snapshot);

        Notification::assertSentTo($ada, NoticeNotification::class, function (NoticeNotification $mail) use ($ada): bool {
            $message = $mail->toMail($ada);
            $html = html_entity_decode((string) $message->render());

            return $mail->via($ada) === ['database', 'mail']
                && $message->subject === '[T-0001] Le Support vous pose une question – Phase 1 : la nouvelle maquette'
                && str_contains($html, 'À faire') && str_contains($html, 'Répondre à la question, dans la tâche.')
                && str_contains($html, 'Préférez-vous le menu à gauche ou en haut ?')
                && str_contains($html, 'le Support vous pose une question et que vous avez choisi l’e-mail direct');
        });
    }

    public function test_a_reply_from_the_client_closes_the_question(): void
    {
        $task = $this->publishedTask();
        app(Questions::class)->ask($task, self::QUESTION, ['7'], Context::ui($this->charles()));
        $snapshot = Payload::snapshot($task->refresh());

        Notification::fake();
        [$ada, $grace] = $this->asClientReceiving($snapshot);
        Http::fake(['master.test/api/tma/v1/tasks/*/entries' => Http::response(['entry' => ['number' => null]], 201)]);
        $question = ProjectTask::query()->sole()->entries()->sole();

        $this->actingAs($ada);
        Livewire::test(ViewTask::class, ['record' => $question->subject_id])
            ->callAction(TestAction::make('answerQuestion')->arguments(['entry' => $question->getKey()]), ['body' => '<p>En haut, merci.</p>'])
            ->assertHasNoActionErrors()
            ->assertSee('Question : répondue');

        $answer = $question->replies()->sole();
        $this->assertSame('<p>En haut, merci.</p>', $answer->body);
        $this->assertSame(Questions::ANSWERED, Questions::status($question->refresh()));
        $this->assertSame([], Questions::openFor($ada)->all());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), "/tasks/{$snapshot['task']['ulid']}/entries")
            && $request['parent_ulid'] === $question->ulid);
        // Grace l'apprend pour information.
        $this->assertSame('Ada Lovelace a répondu à la question du Support.', Notice::query()->for($grace)->latest('id')->first()->data['line']);

        // Plus rien n'attend Ada le matin : pas de bilan vide pour la question qui l'attendait, seulement marquée envoyée.
        $this->assertSame(1, Notice::query()->for($ada)->pending()->where('event', NoticeEvent::Question->value)->count());
        $this->artisan('tma:digest')->assertSuccessful();
        Notification::assertNotSentTo($ada, DailyDigest::class);
        $this->assertSame(0, Notice::query()->for($ada)->pending()->daily()->count());
    }

    public function test_the_support_hears_that_the_client_answered_its_question(): void
    {
        $task = $this->publishedTask();
        $question = app(Questions::class)->ask($task, self::QUESTION, ['7'], Context::ui($this->charles()));
        Notification::fake();

        $service = User::factory()->create();
        Instance::query()->where('uid', self::CLIENT_UID)->update(['api_user_id' => $service->getKey()]);
        Passport::actingAs($service, [], 'api');
        $this->postJson("/api/tma/v1/tasks/{$task->ulid}/entries", [
            'ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => '<p>En haut, merci.</p>', 'parent_ulid' => $question->ulid,
            'author' => ['instance_uid' => self::CLIENT_UID, 'id' => '7', 'name' => 'Ada Lovelace'], 'origin_instance_uid' => self::CLIENT_UID,
        ])->assertCreated();

        $this->assertSame(Questions::ANSWERED, Questions::status($question->refresh()));
        $this->assertSame('Ada Lovelace', Questions::answerOf($question)->author->name);
        Notification::assertSentTo($this->charles(), NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->data['headline'] === 'Ada Lovelace (EDM) a répondu à votre question'
            && $mail->notice->data['line'] === 'Ada Lovelace (EDM) a répondu à la question du Support.');

        // Une suite du Support dans le fil ne la rouvre pas ; une réponse ne se retire plus.
        app(Questions::class)->answer($question->refresh(), '<p>Noté.</p>', Context::ui($this->charles()));
        $this->assertSame(Questions::ANSWERED, Questions::status($question->refresh()));
        $this->assertFalse(app(Questions::class)->canWithdraw($question, Context::ui($this->charles())));
    }

    public function test_the_support_withdraws_an_open_question_and_the_client_hears_it(): void
    {
        $task = $this->publishedTask();
        $question = app(Questions::class)->ask($task, self::QUESTION, ['7'], Context::ui($this->charles()));
        $before = Payload::snapshot($task->refresh());

        $this->actingAs($this->charles());
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->callAction(TestAction::make('withdrawQuestion')->arguments(['entry' => $question->getKey()]))
            ->assertHasNoActionErrors()
            ->assertSee('Question retirée')
            ->assertActionHidden(TestAction::make('answerQuestion')->arguments(['entry' => $question->getKey()]));

        $this->assertSame(Questions::WITHDRAWN, Questions::status($question->refresh()));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync')
            && collect($request->data()['entries'] ?? [])->contains(fn (array $sent): bool => filled($sent['meta']['question']['withdrawn_at'] ?? null)));
        $after = Payload::snapshot($task->refresh());

        // Le client la connaissait ouverte : Ada apprend qu'elle n'a plus à y répondre, et une seule fois.
        Notification::fake();
        [$ada] = $this->asClientReceiving($before);
        $this->receive([...$after, 'task' => [...$after['task'], 'version' => $after['task']['version'] + 1]]);
        $this->receive([...$after, 'task' => [...$after['task'], 'version' => $after['task']['version'] + 2]]);

        $this->assertSame(1, Notice::query()->for($ada)->where('key', 'like', 'question-withdrawn:%')->count());
        Notification::assertSentTo($ada, NoticeNotification::class, fn (NoticeNotification $mail): bool => $mail->notice->data['line'] === 'Le Support a retiré sa question : plus besoin d’y répondre.');
        $this->assertSame([], Questions::openFor($ada)->all());
        $this->assertSame(Questions::WITHDRAWN, Questions::status(Entry::query()->where('ulid', $question->ulid)->sole()));
    }

    public function test_a_plain_message_on_a_task_stays_for_information(): void
    {
        $task = $this->publishedTask();
        app(Timeline::class)->add($task, EntryType::Comment, '<p>La maquette est en ligne.</p>', Context::ui($this->charles()));
        $snapshot = Payload::snapshot($task->refresh());

        Notification::fake();
        [$ada] = $this->asClientReceiving($snapshot);

        $this->assertNull(Questions::status(ProjectTask::query()->sole()->entries()->sole()));
        $this->assertSame([], Questions::openFor($ada)->all());
        $this->assertSame('weekly', Notice::query()->for($ada)->latest('id')->first()->data['digest']);
    }
}
