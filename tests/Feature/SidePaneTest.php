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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Devis, factures et déclarations : le contenu à gauche, l'infolist dans le volet
 * latéral de filament-ui, ouvert d'emblée et refermable par le bouton d'en-tête.
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

        $this->assertSidePane(EditQuote::class, $quote->getKey(), 25, 'Total HT');
    }

    public function test_the_invoice_shows_its_infolist_in_the_side_pane(): void
    {
        [$company, $contact] = $this->customer();
        $invoice = Invoice::create(['title' => 'Acompte', 'company_id' => $company->getKey(), 'contact_id' => $contact->getKey(), 'items' => [], 'state' => 'draft']);

        $this->assertSidePane(EditInvoice::class, $invoice->getKey(), 25, 'Total TTC');
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

        $this->assertSidePane(EditDeclaration::class, $declaration->getKey(), 40, 'Fournisseurs');
    }

    /** @param  class-string  $page */
    protected function assertSidePane(string $page, int $record, int $width, string $infolistText): void
    {
        $test = Livewire::test($page, ['record' => $record]);

        // Ouvert au lancement, à la largeur voulue, avec l'infolist de la page.
        $this->assertSame('info', $test->get('sidePane'));
        $test->assertSeeHtml('data-split-pane="info"')
            ->assertSeeHtml("--pane-width: {$width}%")
            ->assertSee($infolistText);

        // Le bouton d'en-tête le ferme, puis le rouvre.
        $test->callAction('paneInfo');
        $this->assertNull($test->get('sidePane'));
        $test->assertSeeHtml('data-split-view')->assertDontSeeHtml('data-split-pane');

        $test->callAction('paneInfo');
        $this->assertTrue($test->instance()->isSidePaneOpen('info'));
    }

    /** @return array{Company, Contact} */
    protected function customer(): array
    {
        $company = Company::create(['title' => 'Acme', 'slug' => 'acme']);
        $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'company_id' => $company->getKey()]);

        return [$company, $contact];
    }
}
