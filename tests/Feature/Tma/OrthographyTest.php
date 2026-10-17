<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentPrism\Livewire\CorrectionReview;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;

/**
 * La correction orthographique par l'IA (filament-prism), facultative : le bouton « Orthographe » de la zone de
 * réponse et des formulaires ne se montre qu'avec la clé API, le réglage et le droit `ai.orthography` ; le texte
 * corrigé revient dans le champ, encore à envoyer ou à enregistrer.
 */
class OrthographyTest extends TmaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['filament-prism.provider' => 'openai', 'prism.providers.openai.api_key' => 'test-key']);
    }

    public function test_the_permission_is_declared_on_tickets_and_tasks(): void
    {
        $this->assertArrayHasKey('ai.orthography', TicketResource::permissionActions());
        $this->assertArrayHasKey('ai.orthography', TaskResource::permissionActions());
        // Les passages des tâches restent des droits à côté.
        $this->assertArrayHasKey('workflow.publish', TaskResource::permissionActions());
    }

    public function test_the_button_needs_the_key_the_setting_and_the_permission(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();

        // Sans le droit, pas de bouton.
        $this->actingAs($this->supportMember());
        $this->assertNoComposerAction($this->viewTicket($ticket));

        $this->actingAs($this->supportMember('tma.ticket.ai.orthography'));
        $this->viewTicket($ticket)->assertActionVisible(self::composer('orthography_composer'));

        // Sans clé API (chez un client, par exemple), rien ne s'affiche, même avec le droit.
        config(['prism.providers.openai.api_key' => '']);
        $this->assertNoComposerAction($this->viewTicket($ticket));

        // Le réglage éteint la correction, clé ou pas.
        config(['prism.providers.openai.api_key' => 'test-key', 'filament-tma.ai.orthography' => false]);
        $this->assertNoComposerAction($this->viewTicket($ticket));
    }

    public function test_the_message_is_corrected_before_it_is_sent(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($this->supportMember('tma.ticket.ai.orthography'));

        $page = $this->viewTicket($ticket)
            ->fillForm(['body' => '<p>Quel parain exactement ?</p>'], 'composer')
            ->mountAction(self::composer('orthography_composer'));

        // La revue s'ouvre sur un brouillon : le message en HTML, rattaché au ticket.
        $interaction = AiInteraction::sole();
        $this->assertTrue($interaction->isDraft());
        $this->assertTrue($interaction->correctable->is($ticket));
        $this->assertSame('tma:tickets:composer', $interaction->subject_key);
        $this->assertStringContainsString('Quel parain exactement', $interaction->input['body']);
        $this->assertTrue($interaction->meta['fields'][0]['html']);

        $this->correct($interaction, ['body' => '<p>Quel parrain exactement ?</p>']);

        // Seul le texte de la demande en base compte, pas celui de l'événement.
        $page->dispatch('filament-prism:correction-applied', interactionId: $interaction->getKey(), values: ['body' => '<p>Texte forgé</p>'])
            ->assertNotified('Correction appliquée')
            ->callAction(self::composer('composerReply_request_answer'));

        $this->assertSame('awaiting_requester', $ticket->refresh()->state::getMorphClass());
        $this->assertStringContainsString('Quel parrain exactement', Entry::query()->sole()->body);
    }

    public function test_the_correction_of_someone_else_is_ignored(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $colleague = $this->supportMember('tma.ticket.ai.orthography');

        // La demande d'un collègue, sur la même zone du même ticket : son brouillon ne vient pas chez moi.
        $interaction = AiInteraction::create([
            'user_id' => $colleague->getKey(),
            'task' => 'orthography',
            'provider' => 'openai',
            'model' => 'fake',
            'correctable_type' => $ticket->getMorphClass(),
            'correctable_id' => $ticket->getKey(),
            'subject_key' => 'tma:tickets:composer',
            'input' => ['body' => '<p>Le brouillon du collègue</p>'],
            'output' => ['body' => '<p>Le brouillon du collègue</p>'],
            'status' => AiInteraction::STATUS_ACCEPTED,
        ]);

        $this->actingAs($this->supportMember('tma.ticket.ai.orthography'));
        $this->viewTicket($ticket)
            ->fillForm(['body' => '<p>Mon message</p>'], 'composer')
            ->dispatch('filament-prism:correction-applied', interactionId: $interaction->getKey(), values: ['body' => true])
            ->assertNotNotified('Correction appliquée')
            ->callAction(self::composer('composerReply_request_answer'));

        $this->assertStringContainsString('Mon message', Entry::query()->sole()->body);
    }

    public function test_a_new_request_is_corrected_before_it_is_created(): void
    {
        $this->asClient();
        $correspondent = $this->correspondent('tma.ticket.ai.orthography');
        $this->actingAs($correspondent);

        $page = Livewire::test(CreateTicket::class)
            ->fillForm(['title' => 'Ajoutter un champ', 'priority' => 'normal', 'description' => '<p>Sur la fiche parain.</p>'])
            ->mountAction('orthography_form');

        // Avant la création, la demande se rattache à l'utilisateur.
        $interaction = AiInteraction::sole();
        $this->assertTrue($interaction->correctable->is($correspondent));
        $this->assertSame('tma:tickets:form', $interaction->subject_key);
        $this->assertSame(['title', 'description'], array_keys($interaction->input));

        $this->correct($interaction, ['title' => 'Ajouter un champ', 'description' => '<p>Sur la fiche parrain.</p>']);

        $page->dispatch('filament-prism:correction-applied', interactionId: $interaction->getKey(), values: ['title' => true, 'description' => true])
            ->assertSchemaStateSet(['title' => 'Ajouter un champ'])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->sole();
        $this->assertSame('Ajouter un champ', $ticket->title);
        $this->assertStringContainsString('parrain', $ticket->description);
    }

    public function test_the_task_form_follows_the_task_permission(): void
    {
        $this->asMaster();

        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.task.*')->create());
        Livewire::test(CreateTask::class)->assertActionVisible('orthography_form');

        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.task.viewany', 'tma.task.create')->create());
        Livewire::test(CreateTask::class)->assertActionHidden('orthography_form');
    }

    /**
     * La revue : « Envoyer » part à l'IA (simulée), « Tout appliquer » renvoie les valeurs à la page.
     *
     * @param  array<string, string>  $corrected
     */
    private function correct(AiInteraction $interaction, array $corrected): void
    {
        Prism::fake([StructuredResponseFake::make()->withUsage(new Usage(10, 20))->withStructured($corrected)]);

        Livewire::test(CorrectionReview::class, ['interactionId' => $interaction->getKey(), 'autoApply' => false])
            ->call('submit')
            ->call('applyAll');

        $this->assertTrue($interaction->refresh()->isStatus(AiInteraction::STATUS_ACCEPTED));
    }

    private function viewTicket(Ticket $ticket): Testable
    {
        return Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()]);
    }

    /** Le bouton n'est pas proposé : une action masquée d'un schéma n'est même pas trouvée. */
    private function assertNoComposerAction(Testable $page): void
    {
        try {
            $page->assertActionHidden(self::composer('orthography_composer'));
        } catch (ActionNotResolvableException) {
            $this->addToAssertionCount(1);
        }
    }

    /** Une action de la zone de réponse, sous la chronologie. */
    private static function composer(string $name): TestAction
    {
        return TestAction::make($name)->schemaComponent('composerActions', schema: 'composer');
    }
}
