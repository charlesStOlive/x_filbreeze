<?php

namespace Tests\Feature;

use App\Services\Processors\Emails\CorrectEmailProcessor;
use App\Services\Processors\Emails\TradEmailProcessor;
use Carbon\CarbonImmutable;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use CharlesStOlive\MsGraphFilament\Contracts\EmailClient;
use CharlesStOlive\MsGraphFilament\Dto\EmailMessageDTO;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\PrismFake;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

/**
 * Traitement automatique des brouillons Outlook par l'IA (`AiDraftProcessor`) : traduction
 * (`d-trad`, ressource `email-translation`) et correction (`d-cor`, ressource `orthography`).
 * Sans interface : le processeur appelle l'IA et applique lui-même le résultat.
 */
class EmailDraftAiProcessorsTest extends TestCase
{
    use RefreshDatabase;

    protected object $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'prism.providers.openai.api_key' => 'test-key',
            'filament-prism.email_translation.model' => 'translation-model',
        ]);

        // Outlook : on n'enregistre que ce qui lui serait envoyé.
        $this->client = new class implements EmailClient
        {
            public array $updates = [];

            public array $created = [];

            public array $categories = [];

            public function fetchDraft(MsgUserDraft $user, string $messageId): array { return []; }

            public function fetchEmail($user, string $messageId): array { return []; }

            public function updateDraftBody(MsgUserDraft $user, MsgEmailDraft $email, string $contentType, string $content): void {}

            public function createDraft(MsgUserDraft $user, EmailMessageDTO $emailDto): array
            {
                $this->created[] = $emailDto;

                return ['id' => 'new-draft'];
            }

            public function addAttachments(MsgUserDraft $user, MsgEmailDraft $email, array $attachments): void {}

            public function updateEmail(MsgUserDraft $user, MsgEmailDraft $email, array $data): void
            {
                $this->updates[] = $data;
            }

            public function updateEmailContent(MsgUserDraft $user, MsgEmailDraft $email, string $newContent, bool $preserveHistory = true): void {}

            public function addCategory($user, $email, string $category): void {}

            public function setCategories($user, $email, array $categories): void
            {
                $this->categories[] = $categories;
            }
        };
    }

    public function test_the_draft_is_translated_in_place(): void
    {
        $fake = $this->fakeTranslation("```html\n<p>Hello</p>\n```");

        $email = $this->runProcessor('actif', ['en' => true]);

        $this->assertSame('<p>Hello</p>', $this->client->updates[0]['body']['content'], 'Le bloc Markdown est retiré.');
        $this->assertSame([['WORKING_START'], ['WORKING_END']], $this->client->categories);
        $this->assertSame('success', $email->getServiceResult('d-trad', 'status'));
        $this->assertSame('update', $email->getServiceResult('d-trad', 'processing_mode'));
        $this->assertSame(30, $email->getServiceResult('d-trad', 'tokens'));

        $fake->assertRequest(function (array $requests): void {
            $this->assertSame('translation-model', $requests[0]->model());
            $this->assertStringContainsString('Langue cible : en', $requests[0]->prompt());
            $this->assertStringNotContainsString('## traduit', $requests[0]->prompt(), 'Le code de déclenchement n’est pas traduit.');
        });

        $interaction = AiInteraction::sole();
        $this->assertSame('email-translation', $interaction->task);
        $this->assertSame('applied', $interaction->status);
        $this->assertTrue($interaction->correctable->is($email));
    }

    public function test_only_the_message_is_translated_and_the_history_is_kept(): void
    {
        $fake = $this->fakeTranslation('<p>Hello Paul</p>');
        $history = '<div id="appendonsend"></div><hr><div id="divRplyFwdMsg"><b>De :</b> Paul</div><p>Bonjour, pouvez-vous confirmer ?</p>';

        $this->runProcessor('actif', ['en' => true], body: "<html><head><style>p{margin:0}</style></head><body>\n<p>## traduit -en ##</p>\n<p>Bonjour Paul</p>{$history}</body></html>");

        $fake->assertRequest(function (array $requests): void {
            $this->assertStringContainsString('Bonjour Paul', $requests[0]->prompt());
            $this->assertStringNotContainsString('pouvez-vous confirmer', $requests[0]->prompt(), 'L’historique n’est pas envoyé à l’IA.');
            $this->assertStringNotContainsString('<style>', $requests[0]->prompt(), 'L’enveloppe du document non plus.');
        });

        $this->assertSame(
            "<html><head><style>p{margin:0}</style></head><body><p>Hello Paul</p>{$history}</body></html>",
            $this->client->updates[0]['body']['content'],
        );
    }

    public function test_a_reply_with_nothing_written_above_the_history_fails_without_calling_the_ai(): void
    {
        $fake = $this->fakeTranslation('<p>Hello</p>');

        $email = $this->runProcessor('actif', ['en' => true], body: "<html><body>\n<p>## traduit -en ##</p>\n<div id=\"appendonsend\"></div><div id=\"divRplyFwdMsg\">De : Paul</div></body></html>");

        $this->assertSame('error', $email->getServiceResult('d-trad', 'status'));
        $this->assertStringContainsString('Rien à traiter', $email->getServiceResult('d-trad', 'message'));
        $fake->assertCallCount(0);
        $this->assertSame([], $this->client->updates);
    }

    public function test_option_n_creates_a_new_draft_in_the_language_that_follows_it(): void
    {
        $this->fakeTranslation('<p>Hola</p>');

        $email = $this->runProcessor('actif', ['n' => true, 'es' => true]);

        $this->assertSame([], $this->client->updates);
        $this->assertSame('<p>Hola</p>', $this->client->created[0]->bodyHtml);
        $this->assertSame('new-draft', $email->getServiceResult('d-trad', 'new_draft_id'));
        $this->assertSame('es', $email->getServiceResult('d-trad', 'target_language'));
    }

    public function test_test_mode_translates_without_touching_outlook(): void
    {
        $this->fakeTranslation('<p>Hello</p>');

        $email = $this->runProcessor('test', ['en' => true]);

        $this->assertSame([], $this->client->updates);
        $this->assertSame([], $this->client->categories);
        $this->assertSame('<p>Hello</p>', $email->getServiceResult('d-trad', 'content'));
        $this->assertSame('discarded', AiInteraction::sole()->status);
    }

    public function test_a_code_without_language_is_blocked_before_any_call(): void
    {
        $fake = $this->fakeTranslation('<p>Hello</p>');

        $email = $this->runProcessor('actif', [], queue: false);

        $this->assertSame('blocked', $email->getServiceResult('d-trad', 'status'));
        $this->assertStringContainsString('Langue cible absente', $email->getServiceResult('d-trad', 'message'));
        $fake->assertCallCount(0);
    }

    public function test_correction_goes_through_the_orthography_resource_and_keeps_the_history(): void
    {
        $fake = Prism::fake([
            StructuredResponseFake::make()->withStructured(['message' => '<p>Bonjour Paul, voici le devis.</p>'])->withUsage(new Usage(10, 5)),
        ]);
        $history = '<div id="appendonsend"></div><div id="divRplyFwdMsg"><b>De :</b> Paul</div><p>Ou en est le devi ?</p>';

        $email = $this->runProcessor(
            'actif',
            [],
            body: "<html><body>\n<p>## corrige ##</p>\n<p>Bonjour Paul, voisi le devis.</p>{$history}</body></html>",
            processor: CorrectEmailProcessor::class,
        );

        $fake->assertRequest(function (array $requests): void {
            $this->assertSame('orthography', AiInteraction::sole()->task);
            $this->assertStringContainsString('voisi le devis', $requests[0]->prompt());
            $this->assertStringNotContainsString('Ou en est le devi', $requests[0]->prompt(), 'L’historique n’est pas corrigé.');
        });

        $this->assertSame(
            "<html><body><p>Bonjour Paul, voici le devis.</p>{$history}</body></html>",
            $this->client->updates[0]['body']['content'],
        );
        $this->assertSame('Email corrigé et mis à jour', $email->getServiceResult('d-cor', 'message'));
        $this->assertSame(15, $email->getServiceResult('d-cor', 'tokens'));
        $this->assertSame('applied', AiInteraction::sole()->status);
    }

    private function fakeTranslation(string $text): PrismFake
    {
        return Prism::fake([
            TextResponseFake::make()->withText($text)->withUsage(new Usage(10, 20)),
        ]);
    }

    /**
     * Le parcours de msgraph pour un brouillon : preflight (synchrone) puis, s'il passe, le job.
     *
     * @param  array<string, mixed>  $options  Les options du code (## traduit -en ## → ['en' => true]).
     */
    private function runProcessor(string $mode, array $options, bool $queue = true, string $body = "<p>## traduit ##</p>\n<p>Bonjour</p>", string $processor = TradEmailProcessor::class): MsgEmailDraft
    {
        $user = new MsgUserDraft([
            'email' => 'moi@example.com',
            'services_options' => [$processor::getKey() => ['mode' => $mode, 'regex_code' => $processor::getDefaultTriggerCode()]],
        ]);

        $email = MsgEmailDraft::create(['email_id' => 'draft-1', 'status' => 'processing', 'active_jobs' => 0]);

        $now = CarbonImmutable::now();
        $dto = new EmailMessageDTO(
            id: 'draft-1', createdAt: $now, receivedAt: $now, modifiedAt: $now, sentAt: $now,
            hasAttachments: false, internetMessageId: '', subject: 'Bonjour', importance: 'normal',
            contentType: 'HTML', bodyHtml: $body, bodyText: strip_tags($body),
            toEmails: [], ccEmails: [], bccEmails: [], fromName: '', fromEmail: 'moi@example.com',
            webLink: '', inferenceClassification: 'focused', attachments: [],
            regexCode: $processor::getDefaultTriggerCode(), regexOptions: $options,
        );

        $job = new $processor($user, $dto, $email, $this->client);

        if ($job->preflight()->proceed && $queue) {
            $email->increment('active_jobs');
            $job->handle();
        }

        return $email->refresh();
    }
}
