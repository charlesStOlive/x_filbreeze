<?php

namespace Tests\Feature;

use App\Ai\SupplierInvoiceExtraction;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages\ListSupplierInvoices;
use App\Models\States\SupplierInvoice\Draft;
use App\Models\States\SupplierInvoice\Error;
use App\Models\States\SupplierInvoice\Validated;
use App\Models\States\SupplierInvoice\Warning;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Processors\FileProcessor;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

/**
 * Création de factures fournisseurs depuis des fichiers (ressource IA
 * `supplier-invoice-extraction`) : scan, extraction, brouillons, validation.
 */
class SupplierInvoiceExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $ovh;

    protected function setUp(): void
    {
        parent::setUp();

        config(['prism.providers.openai.api_key' => 'test-key']);
        Storage::fake('public');
        Storage::fake('sharepoint');
        Storage::fake(FileUploadConfiguration::disk());
        $this->actingAs(User::factory()->crmManager()->create());

        // Le scan (texte du PDF/OCR) est hors sujet ici : on fixe le texte « lu ».
        $this->app->instance(FileProcessor::class, new class extends FileProcessor
        {
            public function processFile(string $filePath): array
            {
                return ['type' => 'text', 'content' => 'Facture '.file_get_contents($filePath)];
            }
        });

        $this->ovh = Supplier::create(['name' => 'OVH', 'slug' => 'ovh', 'siret' => '424 761 419 00045', 'vat_number' => 'FR22424761419']);
        Supplier::create(['name' => 'Scaleway', 'slug' => 'scaleway', 'alternative_names' => ['Online SAS']]);
    }

    public function test_files_become_drafts_then_validated_invoices(): void
    {
        $this->fakeAi([
            'known_supplier_id' => 'aucun',
            'supplier_name' => 'Hébergement du Nord',
            'supplier_siret' => '42476141900045',
            'invoice_number' => 'FR-123',
            'invoice_at' => '2026-09-01',
            'total_ht' => 100,
            'tx_tva' => 20,
            'tva' => 20,
            'total_ttc' => 120,
        ]);

        $test = Livewire::test(ListSupplierInvoices::class)
            ->mountAction('createFromFile')
            ->fillForm(['input' => ['files' => [UploadedFile::fake()->create('ovh.pdf', 10, 'application/pdf')]]])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors();

        // Brouillon créé dès l'extraction, avec son fichier, rattaché à l'appel IA.
        $invoice = SupplierInvoice::sole();
        $this->assertInstanceOf(Draft::class, $invoice->state);
        $this->assertSame($this->ovh->getKey(), $invoice->supplier_id); // reconnu par son SIRET
        $this->assertSame('2026-09-01', $invoice->invoice_at);
        $this->assertTrue((bool) $invoice->has_tva);
        $this->assertNotNull($invoice->getFirstMedia('invoice'));
        $this->assertTrue(AiInteraction::sole()->correctable->is($invoice));

        $test->callMountedAction()->assertHasNoFormErrors();

        $invoice->refresh();
        $this->assertInstanceOf(Validated::class, $invoice->state);
        $this->assertTrue(AiInteraction::sole()->isStatus(AiInteraction::STATUS_ACCEPTED));
        // Le nom lu sur la facture, différent du sien, devient un nom alternatif du fournisseur.
        $this->assertSame(['Hébergement du Nord'], $this->ovh->refresh()->alternative_names);
    }

    public function test_the_same_file_reuses_the_answer_and_the_draft(): void
    {
        $this->fakeAi(['known_supplier_id' => (string) $this->ovh->getKey(), 'invoice_number' => 'A-1', 'invoice_at' => '01/09/2026']);
        $resource = app(SupplierInvoiceExtraction::class);
        $file = UploadedFile::fake()->createWithContent('a.pdf', 'contenu');

        $first = $resource->handleInput(['files' => [$this->temporary($file)]]);
        $second = $resource->handleInput(['files' => [$this->temporary($file)]]);

        $this->assertSame(1, AiInteraction::count());
        $this->assertSame(1, SupplierInvoice::count());
        $this->assertSame($first['invoices'][0]['invoice_id'], $second['invoices'][0]['invoice_id']);
        $this->assertSame('2026-09-01', $first['invoices'][0]['invoice_at']);
    }

    public function test_resolve_matches_suppliers_and_reports_what_is_wrong(): void
    {
        $resource = app(SupplierInvoiceExtraction::class);
        $context = $resource->context([]);
        $resolve = fn (array $output): array => $resource->resolve($output, [], $context);

        $this->assertSame('tva', $resolve(['supplier_vat' => 'FR 22 424761419'])['supplier_match']);
        $this->assertSame('ia', $resolve(['known_supplier_id' => (string) $this->ovh->getKey()])['supplier_match']);
        $this->assertSame('nom', $resolve(['known_supplier_id' => 'aucun', 'supplier_name' => 'online sas'])['supplier_match']);

        $unknown = $resolve(['known_supplier_id' => 'aucun', 'supplier_name' => 'Inconnu SARL', 'total_ht' => 100, 'tva' => 20, 'total_ttc' => 130]);
        $this->assertNull($unknown['supplier_id']);
        $this->assertEqualsCanonicalizing([
            'Fournisseur non reconnu : « Inconnu SARL ».',
            'Numéro de facture introuvable.',
            'Date de facture introuvable.',
            'Totaux incohérents : HT + TVA ne donne pas le TTC.',
        ], $unknown['warnings']);

        SupplierInvoice::create(['supplier_id' => $this->ovh->getKey(), 'invoice_number' => 'X-9', 'invoice_at' => '2026-01-01']);
        $this->assertStringContainsString('existe déjà', implode(' ', $resolve(['known_supplier_id' => (string) $this->ovh->getKey(), 'invoice_number' => 'X-9', 'invoice_at' => '2026-02-01'])['warnings']));
    }

    public function test_validation_puts_incomplete_or_duplicate_drafts_aside(): void
    {
        SupplierInvoice::create(['supplier_id' => $this->ovh->getKey(), 'invoice_number' => 'DUP', 'invoice_at' => '2026-01-01', 'state' => 'validated']);
        $unknown = SupplierInvoice::create(['invoice_at' => '2026-09-01']);
        $duplicate = SupplierInvoice::create(['supplier_id' => $this->ovh->getKey(), 'invoice_number' => 'DUP', 'invoice_at' => '2026-09-01']);

        app(SupplierInvoiceExtraction::class)->apply(['invoices' => [
            ['invoice_id' => $unknown->getKey(), 'supplier_id' => null, 'invoice_number' => 'N-1', 'invoice_at' => '2026-09-01', 'has_tva' => false],
            ['invoice_id' => $duplicate->getKey(), 'supplier_id' => $this->ovh->getKey(), 'invoice_number' => 'DUP', 'invoice_at' => '2026-09-01', 'has_tva' => true],
        ]]);

        $this->assertInstanceOf(Warning::class, $unknown->refresh()->state);
        $this->assertInstanceOf(Error::class, $duplicate->refresh()->state);
    }

    /** @param  array<string, mixed>  $structured */
    protected function fakeAi(array $structured): void
    {
        $defaults = array_fill_keys(['known_supplier_id', 'supplier_name', 'supplier_siret', 'supplier_vat', 'invoice_number', 'invoice_at', 'currency', 'total_ht', 'tx_tva', 'tva', 'total_ttc', 'notes'], null);

        Prism::fake([StructuredResponseFake::make()->withStructured([...$defaults, ...$structured])->withUsage(new Usage(100, 50))]);
    }

    /** Un fichier tel que le reçoit `handleInput()` : un `TemporaryUploadedFile` Livewire. */
    protected function temporary(UploadedFile $file): TemporaryUploadedFile
    {
        $name = Str::random(10).'-meta'.base64_encode($file->getClientOriginalName()).'-.pdf';
        $file->storeAs(FileUploadConfiguration::path(), $name, ['disk' => FileUploadConfiguration::disk()]);

        return TemporaryUploadedFile::createFromLivewire($name);
    }
}
