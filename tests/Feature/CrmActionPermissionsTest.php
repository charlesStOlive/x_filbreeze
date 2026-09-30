<?php

namespace Tests\Feature;

use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Document\DocumentPermissions;
use CharlesStOlive\FilamentPermissionManager\Support\PermissionCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Les boutons du CRM et le moteur d'édition ont leurs droits (filament-permission-manager) : actions propres par
 * famille (Qonto…), éditions lues dans leurs registres (PDF, e-mails, Excel), passages d'état sans filtrer les listes.
 */
class CrmActionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionCatalog::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function invoice(): Invoice
    {
        $company = Company::create(['title' => 'Acme', 'slug' => 'acme']);
        $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'company_id' => $company->getKey()]);

        return Invoice::create(['title' => 'Acompte', 'company_id' => $company->getKey(), 'contact_id' => $contact->getKey(), 'items' => [], 'state' => 'draft']);
    }

    public function test_editions_are_listed_from_their_registries(): void
    {
        $actions = DocumentPermissions::actionsFor(Invoice::class);

        $this->assertArrayHasKey('pdf.invoice_summary_pdf', $actions);
        $this->assertArrayHasKey('email.invoice_summary', $actions);
        $this->assertSame(count(config('templates-pdf.invoice.templates')), count(array_filter(array_keys($actions), fn (string $name): bool => str_starts_with($name, 'pdf.'))));
    }

    public function test_the_role_screen_groups_them_by_family_with_transitions(): void
    {
        $invoice = collect(PermissionCatalog::build()['groups'])->flatMap(fn (array $group): array => $group['resources'])->firstWhere('key', 'crm.invoice');

        $families = collect($invoice['families'])->keyBy('label');
        $this->assertSame('crm.invoice.qonto.*', $families['Qonto']['joker']);
        $this->assertSame('crm.invoice.pdf.*', $families['Éditions PDF']['joker']);
        $this->assertContains('Finaliser Factur-X', array_column($invoice['transitions'], 'label'));
        // Les passages d'état ne filtrent pas la liste des factures.
        $this->assertNull($invoice['states']);
    }

    public function test_a_family_joker_opens_every_edition_of_the_family_and_nothing_else(): void
    {
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'crm.invoice.pdf.*')->create());

        $this->assertTrue(Gate::allows(InvoiceResource::class.'.pdf.invoice_summary_pdf'));
        $this->assertTrue(Gate::allows(InvoiceResource::class.'.pdf.invoice_complete_pdf'));
        $this->assertFalse(Gate::allows(InvoiceResource::class.'.email.invoice_summary'));
        $this->assertFalse(Gate::allows(InvoiceResource::class.'.qonto.send'));
    }

    public function test_the_invoice_page_shows_only_the_buttons_allowed(): void
    {
        $invoice = $this->invoice();

        // Ouvrir et modifier une facture, sans les éditions, ni Qonto, ni les passages d'état.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'crm.invoice.viewany', 'crm.invoice.view', 'crm.invoice.edit')->create());

        Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->assertActionHidden('downloadPdf')
            ->assertActionHidden('sync_qonto_draft')
            ->assertActionHidden('state_submited');

        // Avec toute la famille PDF et le passage « Finaliser » : ces boutons-là, pas les autres.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'crm.invoice.viewany', 'crm.invoice.view', 'crm.invoice.edit', 'crm.invoice.pdf.*', 'crm.invoice.transition.finaliser')->create());

        Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->assertActionVisible('downloadPdf')
            ->assertActionVisible('state_submited')
            ->assertActionHidden('sync_qonto_draft');
    }
}
