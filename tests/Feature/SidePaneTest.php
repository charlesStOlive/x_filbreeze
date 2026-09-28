<?php

namespace Tests\Feature;

use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages\EditInvoice;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages\EditQuote;
use App\Filament\Resources\DeclarationResource\Pages\EditDeclaration;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Declaration;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Devis, factures et déclarations : le contenu à gauche, l'infolist dans le volet
 * latéral de filament-ui, ouvert en permanence — ni bouton pour l'ouvrir, ni croix
 * pour le fermer. Devis et factures ont leur action de modification dans le bandeau.
 */
class SidePaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_quote_shows_its_infolist_in_the_side_pane(): void
    {
        [$company, $contact] = $this->customer();
        $quote = Quote::create(['title' => 'Refonte', 'company_id' => $company->getKey(), 'contact_id' => $contact->getKey(), 'items' => []]);

        $test = $this->assertSidePane(EditQuote::class, $quote->getKey(), 25, 'Total HT')
            ->assertSeeHtml('data-split-pane-header');

        // L'action de modification, dans le bandeau du volet, agit sur le devis édité.
        $test->callAction(TestAction::make('edit')->schemaComponent('split-view', 'content'), ['end_at' => '2026-12-31'])
            ->assertHasNoFormErrors();
        $this->assertStringStartsWith('2026-12-31', (string) $quote->refresh()->end_at);
    }

    public function test_the_invoice_shows_its_infolist_in_the_side_pane(): void
    {
        [$company, $contact] = $this->customer();
        $invoice = Invoice::create(['title' => 'Acompte', 'company_id' => $company->getKey(), 'contact_id' => $contact->getKey(), 'items' => [], 'state' => 'draft']);

        $test = $this->assertSidePane(EditInvoice::class, $invoice->getKey(), 25, 'Total TTC');

        $test->callAction(TestAction::make('edit')->schemaComponent('split-view', 'content'), ['title' => 'Solde', 'modalite' => '30 jours'])
            ->assertHasNoFormErrors();
        $this->assertSame('Solde', $invoice->refresh()->title);
    }

    public function test_the_declaration_shows_its_invoice_lists_in_the_side_pane(): void
    {
        $declaration = Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'status' => 'draft',
            'calculation_details' => ['client_invoice_ids' => [], 'qonto_supplier_invoice_ids' => []],
        ]);

        $this->assertSidePane(EditDeclaration::class, $declaration->getKey(), 40, 'Fournisseurs')
            // Seulement les sections « Déclaration » et « TVA », sur le fond du volet : pas de bandeau.
            ->assertDontSeeHtml('data-split-pane-header')
            ->assertDontSeeHtml('data-ui-background="none"');
    }

    /** @param  class-string  $page */
    protected function assertSidePane(string $page, int $record, int $width, string $infolistText): mixed
    {
        $test = Livewire::test($page, ['record' => $record]);

        // Ouvert au lancement, à la largeur voulue, avec l'infolist de la page.
        $this->assertSame('info', $test->get('sidePane'));
        $test->assertSeeHtml('data-split-pane="info"')
            ->assertSeeHtml("--pane-width: {$width}%")
            ->assertSee($infolistText);

        // Toujours ouvert : ni croix dans le bandeau, ni bouton d'en-tête pour le basculer.
        $test->assertDontSeeHtml('Fermer le volet')
            ->assertActionDoesNotExist('paneInfo');

        return $test;
    }

    /** @return array{Company, Contact} */
    protected function customer(): array
    {
        $company = Company::create(['title' => 'Acme', 'slug' => 'acme']);
        $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'company_id' => $company->getKey()]);

        return [$company, $contact];
    }
}
