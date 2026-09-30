<?php

namespace Tests\Feature;

use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages\EditInvoice;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages\EditQuote;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use CharlesStOlive\FilamentPrism\Livewire\CorrectionReview;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

/**
 * Correction orthographique des devis et factures via filament-prism : le titre, la
 * description, et le titre/la description de chaque ligne du Builder `items`.
 */
class AiCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['prism.providers.openai.api_key' => 'test-key']);
        $this->actingAs(User::factory()->crmManager()->create());
    }

    public function test_the_quote_texts_and_its_lines_are_sent_and_applied(): void
    {
        $quote = Quote::create([
            ...$this->customer(),
            'title' => 'Refonte du sitte',
            'description' => '',
            'items' => [
                ['type' => 'product', 'data' => ['title' => 'Devellopement', 'description' => 'Un texte mal ecrit', 'qty' => 2]],
                ['type' => 'remise', 'data' => ['title' => 'Remise', 'total' => -10]],
            ],
        ]);

        // La description vide n'est pas envoyée ; la ligne « Remise » n'a pas de description.
        $this->assertSame([
            'title' => 'Refonte du sitte',
            'item_1_title' => 'Devellopement',
            'item_1_description' => 'Un texte mal ecrit',
            'item_2_title' => 'Remise',
        ], $quote->extractValues());

        Prism::fake([StructuredResponseFake::make()->withUsage(new Usage(10, 20))->withStructured([
            'title' => 'Refonte du site',
            'item_1_title' => 'Développement',
            'item_1_description' => 'Un texte mal écrit',
            'item_2_title' => 'Remise',
        ])]);

        $test = Livewire::test(EditQuote::class, ['record' => $quote->getKey()])
            ->mountAction($this->correctionAction());

        // La revue s'ouvre sur un brouillon ; « Envoyer » part à l'IA, « Tout appliquer » renvoie les valeurs à la page.
        $interaction = AiInteraction::sole();
        $this->assertTrue($interaction->isDraft());
        $this->assertTrue($interaction->correctable->is($quote));
        $this->assertSame('Ligne 1 — description', $interaction->meta['labels']['item_1_description']);

        Livewire::test(CorrectionReview::class, ['interactionId' => $interaction->getKey(), 'autoApply' => false])
            ->call('submit')
            ->call('applyAll');

        $interaction->refresh();
        $this->assertTrue($interaction->isStatus(AiInteraction::STATUS_ACCEPTED));

        // Seules les valeurs de l'interaction en base comptent, pas celles de l'événement.
        $test->dispatch('filament-prism:correction-applied', interactionId: $interaction->getKey(), values: [
            'title' => 'Titre forgé',
            'item_1_description' => 'Description forgée',
        ]);

        $quote->refresh();
        $this->assertSame('Refonte du site', $quote->title);
        $this->assertSame('Devellopement', $quote->items[0]['data']['title']);
        $this->assertSame('Un texte mal écrit', $quote->items[0]['data']['description']);
        $this->assertSame(2, $quote->items[0]['data']['qty']);
        $this->assertSame('Refonte du site', $test->get('data.title'));
    }

    public function test_an_interaction_of_another_document_is_ignored(): void
    {
        $invoice = Invoice::create([...$this->customer(), 'title' => 'Acompte', 'items' => [], 'state' => 'draft']);
        $other = Invoice::create([...$this->customer('other'), 'title' => 'Autre', 'items' => [], 'state' => 'draft']);

        $interaction = AiInteraction::create([
            'task' => 'orthography',
            'provider' => 'openai',
            'model' => 'fake',
            'correctable_type' => Invoice::class,
            'correctable_id' => $other->getKey(),
            'input' => ['title' => 'Autre'],
            'output' => ['title' => 'Piraté'],
            'status' => AiInteraction::STATUS_ACCEPTED,
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->assertActionVisible($this->correctionAction())
            ->dispatch('filament-prism:correction-applied', interactionId: $interaction->getKey(), values: ['title' => 'Piraté']);

        $this->assertSame('Acompte', $invoice->refresh()->title);
    }

    /** L'action vit dans les actions du formulaire (`getFormActions()`), pas dans l'en-tête. */
    protected function correctionAction(): TestAction
    {
        return TestAction::make('aiCorrection')->schemaComponent('split-view.form-actions', 'content');
    }

    /** @return array{company_id: int, contact_id: int} */
    protected function customer(string $slug = 'acme'): array
    {
        $company = Company::create(['title' => ucfirst($slug), 'slug' => $slug]);
        $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => "ada@{$slug}.test", 'company_id' => $company->getKey()]);

        return ['company_id' => $company->getKey(), 'contact_id' => $contact->getKey()];
    }
}
