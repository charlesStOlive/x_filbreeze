<?php

namespace App\Ai;

use App\Models\States\SupplierInvoice\Draft;
use App\Models\States\SupplierInvoice\Validated;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Processors\FileProcessor;
use App\Support\CompanyNameHelper;
use Carbon\Carbon;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use CharlesStOlive\FilamentPrism\Resources\AiResource;
use CharlesStOlive\FilamentPrism\Services\AiRunner;
use CharlesStOlive\FilamentPrism\Support\AiProviderException;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Throwable;

/**
 * Création de factures fournisseurs à partir de fichiers (PDF ou images).
 *
 * - Avant l'appel : les fichiers, scannés (texte du PDF, sinon OCR) ; la liste des
 *   fournisseurs connus, pour que l'IA désigne directement le bon.
 * - Après l'appel : `resolve()` rapproche le fournisseur (SIRET, TVA, choix de l'IA,
 *   nom), normalise date et montants, et signale ce qui cloche. Chaque fichier devient
 *   aussitôt une facture à l'état Draft, avec son fichier — rien n'est perdu si la
 *   modale est fermée, et relancer le même fichier réutilise la réponse déjà payée.
 * - Réception : ces brouillons, à vérifier ; `apply()` les enregistre puis les valide
 *   (DraftToValidated : Warning s'il manque fournisseur ou numéro, Error si le numéro
 *   existe déjà pour ce fournisseur).
 */
class SupplierInvoiceExtraction extends AiResource
{
    /** Valeur de `known_supplier_id` quand aucun fournisseur connu ne correspond. */
    private const NO_KNOWN_SUPPLIER = 'aucun';

    /**
     * Les colonnes de la facture que l'extraction remplit, et les seules que la réception peut
     * réécrire : tout le reste de la réponse (avertissements, nom lu, méthode de rapprochement)
     * sert à la vérification mais ne va jamais en base.
     */
    private const INVOICE_FIELDS = ['supplier_id', 'invoice_number', 'invoice_at', 'currency', 'has_tva', 'total_ht', 'tx_tva', 'tva', 'total_ttc', 'notes'];

    public function key(): string
    {
        return 'supplier-invoice-extraction';
    }

    public function label(): string
    {
        return 'Factures fournisseurs depuis fichiers';
    }

    public function model(): string
    {
        return (string) config('filament-prism.supplier_invoice_extraction.model');
    }

    // ── Avant l'appel ─────────────────────────────────────────────────────

    public function inputSchema(): array
    {
        return [
            FileUpload::make('files')
                ->label('Factures (PDF ou images)')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->multiple()
                // Les fichiers restent en temporaire Livewire : c'est draftFor() qui les copie
                // dans la médiathèque de la facture, une fois l'appel réussi.
                ->storeFiles(false)
                ->required(),
        ];
    }

    /**
     * Remplace le comportement par défaut (un seul appel pour tout le formulaire) : un appel par
     * fichier, chacun isolé, pour qu'un fichier illisible ou un refus du provider n'empêche pas
     * les autres. Le résultat est l'état initial de la réception : une ligne `invoices` par
     * fichier, avec soit son brouillon, soit son erreur.
     *
     * @param  array{files?: array<TemporaryUploadedFile>}  $data
     */
    public function handleInput(array $data): array
    {
        return [
            'invoices' => collect($data['files'] ?? [])
                ->map(fn (TemporaryUploadedFile $file): array => $this->extractFile($file))
                ->values()
                ->all(),
        ];
    }

    /**
     * Rechargée à chaque fichier, et partagée par les trois étapes qui en ont besoin : le
     * prompt (la liste lue par l'IA), le schéma (l'enum `known_supplier_id`) et `resolve()`
     * (le rapprochement). Les trois voient donc exactement la même liste.
     *
     * @return array{suppliers: Collection<int, Supplier>}
     */
    public function context(array $input): array
    {
        return [
            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name', 'legal_name', 'siren', 'siret', 'vat_number', 'alternative_names']),
        ];
    }

    // ── L'appel ───────────────────────────────────────────────────────────

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
            Tu es un assistant comptable qui lit des factures fournisseurs reçues par notre société.

            On te donne le texte d'une facture (extrait du PDF, ou lu par OCR : il peut contenir
            des erreurs de lecture) et la liste des fournisseurs que nous connaissons déjà.

            Règles :
            - Le fournisseur est l'émetteur de la facture. Le client/destinataire, c'est nous :
              ne le confonds jamais avec le fournisseur.
            - known_supplier_id : l'identifiant du fournisseur connu qui a émis la facture, en
              t'aidant du nom, de la raison sociale, du SIRET/SIREN ou du numéro de TVA ; "aucun"
              si aucun ne correspond avec certitude.
            - Si une information est absente ou illisible, mets null. Ne devine rien, n'invente rien.
            - Montants : nombres, sans symbole ni séparateur de milliers. Taux de TVA en pourcentage
              (20 pour 20 %).
            - Date d'émission au format YYYY-MM-DD.
            - notes : les ambiguïtés, informations manquantes ou hypothèses faites ; null si rien
              à signaler.
            PROMPT;
    }

    /**
     * Un fournisseur par ligne, avec tout ce qui peut l'identifier sur une facture. Le texte
     * scanné est entre triples guillemets, pour que l'IA ne le prenne pas pour des consignes.
     *
     * Seul le texte scanné est dans `$input` : c'est donc lui seul qui décide si une réponse
     * déjà payée peut être réutilisée. Un fournisseur ajouté depuis ne relance pas l'appel.
     *
     * @param  array{suppliers: Collection<int, Supplier>}  $context
     */
    public function prompt(array $input, array $context): string
    {
        $suppliers = $context['suppliers']
            ->map(fn (Supplier $supplier): string => '- '.$supplier->id.' : '.collect([
                $supplier->name,
                filled($supplier->legal_name) ? "raison sociale {$supplier->legal_name}" : null,
                filled($supplier->siret) ? "SIRET {$supplier->siret}" : (filled($supplier->siren) ? "SIREN {$supplier->siren}" : null),
                filled($supplier->vat_number) ? "TVA {$supplier->vat_number}" : null,
                filled($supplier->alternative_names) ? 'aussi « '.implode(' », « ', $supplier->alternative_names).' »' : null,
            ])->filter()->implode(' ; '))
            ->implode("\n");

        return "Fournisseurs connus (identifiant : informations) :\n".($suppliers ?: '(aucun)')
            ."\n\nTexte de la facture :\n\"\"\"\n{$input['content']}\n\"\"\"";
    }

    /**
     * Le document lui-même, en plus du texte scanné — seulement si
     * `filament-prism.supplier_invoice_extraction.attach_document` est activé.
     *
     * @param  array{path?: string, mime_type?: string}  $context
     */
    public function attachments(array $input, array $context): array
    {
        if (! config('filament-prism.supplier_invoice_extraction.attach_document') || ! isset($context['path'])) {
            return [];
        }

        return [
            str_starts_with($context['mime_type'] ?? '', 'image/')
                ? Image::fromLocalPath($context['path'], $context['mime_type'])
                : Document::fromLocalPath($context['path']),
        ];
    }

    /**
     * Tous les champs sont obligatoires mais acceptent null : le mode strict d'OpenAI exige
     * que chaque propriété soit requise. Une information absente est donc un null, jamais une
     * clé manquante.
     *
     * @param  array{suppliers: Collection<int, Supplier>}  $context
     */
    public function responseSchema(array $input, array $context): ObjectSchema
    {
        $properties = [
            // Choix fermé : l'IA ne peut répondre qu'un identifiant existant ou « aucun ». Les
            // identifiants sont en chaînes pour ne pas mélanger les types dans l'enum.
            new EnumSchema(
                'known_supplier_id',
                'Identifiant du fournisseur connu qui a émis la facture, ou "'.self::NO_KNOWN_SUPPLIER.'".',
                [...$context['suppliers']->map(fn (Supplier $supplier): string => (string) $supplier->id)->all(), self::NO_KNOWN_SUPPLIER],
            ),
            new StringSchema('supplier_name', 'Nom du fournisseur (émetteur) tel qu’affiché sur la facture.', nullable: true),
            new StringSchema('supplier_siret', 'SIRET du fournisseur (14 chiffres).', nullable: true),
            new StringSchema('supplier_vat', 'Numéro de TVA intracommunautaire du fournisseur.', nullable: true),
            new StringSchema('invoice_number', 'Numéro de la facture.', nullable: true),
            new StringSchema('invoice_at', 'Date d’émission, au format YYYY-MM-DD.', nullable: true),
            new StringSchema('currency', 'Code ISO de la devise (EUR, USD...).', nullable: true),
            new NumberSchema('total_ht', 'Total hors taxes.', nullable: true),
            new NumberSchema('tx_tva', 'Taux de TVA en pourcentage (20 pour 20 %).', nullable: true),
            new NumberSchema('tva', 'Montant de la TVA.', nullable: true),
            new NumberSchema('total_ttc', 'Total toutes taxes comprises.', nullable: true),
            new StringSchema('notes', 'Ambiguïtés, informations manquantes ou hypothèses ; null si rien à signaler.', nullable: true),
        ];

        return new ObjectSchema(
            name: 'supplier_invoice',
            description: 'Les données d’une facture fournisseur.',
            properties: $properties,
            requiredFields: array_map(fn ($property): string => $property->name(), $properties),
        );
    }

    // ── Après l'appel ─────────────────────────────────────────────────────

    /**
     * La réponse brute devient ce qui est persisté dans l'interaction, puis copié dans le
     * brouillon. Rien n'est bloquant ici : ce qui manque ou cloche devient un avertissement
     * affiché à la vérification. C'est la transition DraftToValidated qui décide de l'état
     * final.
     *
     * @param  array{suppliers: Collection<int, Supplier>}  $context
     */
    public function resolve(array $output, array $input, array $context): array
    {
        [$supplier, $match] = $this->matchSupplier($output, $context['suppliers']);
        $supplierName = $this->string($output['supplier_name'] ?? null);
        $invoiceNumber = $this->string($output['invoice_number'] ?? null);
        $invoiceAt = $this->date($output['invoice_at'] ?? null);
        $totalHt = $this->amount($output['total_ht'] ?? null);
        $tva = $this->amount($output['tva'] ?? null);
        $totalTtc = $this->amount($output['total_ttc'] ?? null);

        $warnings = [];

        if ($supplier === null) {
            $warnings[] = 'Fournisseur non reconnu'.($supplierName ? " : « {$supplierName} »" : '').'.';
        }

        if ($invoiceNumber === null) {
            $warnings[] = 'Numéro de facture introuvable.';
        }

        if ($invoiceAt === null) {
            $warnings[] = 'Date de facture introuvable.';
        }

        // 5 centimes de tolérance : les arrondis faits ligne par ligne sur la facture.
        if ($totalHt !== null && $totalTtc !== null && abs($totalHt + ($tva ?? 0) - $totalTtc) > 0.05) {
            $warnings[] = 'Totaux incohérents : HT + TVA ne donne pas le TTC.';
        }

        if ($supplier !== null && $invoiceNumber !== null && $this->isDuplicate($supplier->id, $invoiceNumber)) {
            $warnings[] = "Le numéro {$invoiceNumber} existe déjà pour {$supplier->name} : la facture passera en erreur à la validation.";
        }

        return [
            'supplier_id' => $supplier?->id,
            // Comment le fournisseur a été trouvé (siret, tva, ia, nom) : non affiché, mais
            // garde dans l'interaction la fiabilité du rapprochement.
            'supplier_match' => $match,
            'supplier_name_from_invoice' => $supplierName,
            'invoice_number' => $invoiceNumber,
            'invoice_at' => $invoiceAt,
            'currency' => Str::upper($this->string($output['currency'] ?? null) ?? 'EUR'),
            'total_ht' => $totalHt,
            'tx_tva' => $this->amount($output['tx_tva'] ?? null),
            'tva' => $tva,
            'total_ttc' => $totalTtc,
            // Déduit du montant plutôt que demandé à l'IA : pas de TVA, pas soumis.
            'has_tva' => ($tva ?? 0) > 0,
            'notes' => $this->string($output['notes'] ?? null),
            'warnings' => $warnings,
        ];
    }

    /**
     * Une ligne par fichier. Une ligne en erreur n'affiche que son message : sans brouillon
     * (`invoice_id` vide), le formulaire de la facture est masqué.
     */
    public function receptionSchema(): array
    {
        return [
            Repeater::make('invoices')
                ->hiddenLabel()
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->itemLabel(fn (array $state): ?string => $state['file_name'] ?? null)
                ->schema([
                    Hidden::make('invoice_id'),
                    Hidden::make('file_name'),
                    Text::make(fn (Get $get): string => (string) $get('error'))
                        ->color('danger')
                        ->visible(fn (Get $get): bool => filled($get('error'))),
                    Text::make(fn (Get $get): string => implode(' ', $get('warnings') ?? []))
                        ->color('warning')
                        ->visible(fn (Get $get): bool => filled($get('warnings'))),
                    Fieldset::make('Facture')
                        ->hidden(fn (Get $get): bool => blank($get('invoice_id')))
                        ->columns(4)
                        ->schema([
                            TextInput::make('supplier_name_from_invoice')
                                ->label('Nom sur la facture')
                                ->helperText('Enregistré comme nom alternatif du fournisseur choisi s’il en diffère.')
                                ->disabled()
                                // En lecture seule mais renvoyé : apply() en a besoin pour le nom alternatif.
                                ->dehydrated()
                                ->columnSpan(2),
                            Select::make('supplier_id')
                                ->label('Fournisseur')
                                ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->columnSpan(2),
                            DatePicker::make('invoice_at')->label('Date'),
                            TextInput::make('invoice_number')->label('N° facture'),
                            TextInput::make('currency')->label('Devise'),
                            Toggle::make('has_tva')->label('Soumis à TVA')->inline(false),
                            TextInput::make('total_ht')->label('Total HT')->numeric(),
                            TextInput::make('tx_tva')->label('Taux TVA (%)')->numeric(),
                            TextInput::make('tva')->label('Montant TVA')->numeric(),
                            TextInput::make('total_ttc')->label('Total TTC')->numeric(),
                            Textarea::make('notes')->label('Notes')->rows(2)->columnSpanFull(),
                        ]),
                ]),
        ];
    }

    /**
     * Valide chaque brouillon vérifié. Les lignes en erreur (sans brouillon) ou déjà validées
     * entre-temps sont ignorées. La notification compte les factures par état obtenu.
     *
     * @param  array{invoices?: array<int, array<string, mixed>>}  $data
     */
    public function apply(array $data): void
    {
        $states = collect($data['invoices'] ?? [])
            ->map(fn (array $row): ?string => $this->validateDraft($row))
            ->filter()
            ->countBy();

        if ($states->isNotEmpty()) {
            Notification::make()
                ->title($states->sum().' facture(s) traitée(s)')
                ->body($states->map(fn (int $count, string $state): string => "{$state} : {$count}")->implode(', '))
                ->success()
                ->send();
        }
    }

    // ── Détails ───────────────────────────────────────────────────────────

    /**
     * Un fichier : scan, appel (ou réponse déjà payée), brouillon. Renvoie sa ligne de
     * réception ; une erreur à l'une des étapes devient le message de la ligne, sans brouillon.
     */
    private function extractFile(TemporaryUploadedFile $file): array
    {
        $fileName = $file->getClientOriginalName();
        $path = $file->getRealPath();

        // 1. Le scan : texte du PDF, sinon OCR.
        try {
            $content = app(FileProcessor::class)->processFile($path)['content'];
        } catch (Throwable $exception) {
            report($exception);

            return ['file_name' => $fileName, 'error' => 'Lecture du fichier impossible : '.$exception->getMessage()];
        }

        // 2. L'appel. Le chemin et le type du fichier passent par le contexte et non par
        // l'input : ils ne servent qu'à attachments(), et ne doivent pas empêcher la
        // réutilisation d'une réponse (le chemin temporaire change à chaque envoi).
        try {
            $interaction = app(AiRunner::class)->run(
                $this,
                input: ['content' => $content],
                context: ['path' => $path, 'mime_type' => $file->getMimeType()],
                // L'empreinte du contenu, pas le nom : le même fichier renvoyé sous un autre
                // nom retrouve sa réponse, alors qu'un autre fichier du même nom ne la retrouve pas.
                subjectKey: 'file:'.hash_file('sha256', $path),
                meta: ['file_name' => $fileName],
            );
        } catch (AiProviderException $exception) {
            return ['file_name' => $fileName, 'error' => $exception->getMessage()];
        }

        // 3. Le brouillon, créé tout de suite pour que rien ne se perde si la modale est fermée.
        $invoice = $this->draftFor($interaction, $file);

        // Relu depuis le brouillon plutôt que depuis la réponse : à la relance d'un fichier, il
        // a pu être modifié entre-temps.
        return [
            'invoice_id' => $invoice->getKey(),
            'file_name' => $fileName,
            'supplier_name_from_invoice' => $interaction->output['supplier_name_from_invoice'] ?? null,
            'warnings' => $interaction->output['warnings'] ?? [],
            ...Arr::only($invoice->attributesToArray(), self::INVOICE_FIELDS),
            // Pas de cast sur la colonne : on la ramène en Y-m-d pour le DatePicker, que le
            // brouillon soit neuf ou relu en base.
            'invoice_at' => $invoice->invoice_at ? Carbon::parse($invoice->invoice_at)->format('Y-m-d') : null,
        ];
    }

    /**
     * Le brouillon déjà créé pour cette réponse (même fichier relancé avant validation), sinon un
     * nouveau — avec son fichier, et l'interaction rattachée à lui.
     */
    private function draftFor(AiInteraction $interaction, TemporaryUploadedFile $file): SupplierInvoice
    {
        $existing = $interaction->correctable;

        if ($existing instanceof SupplierInvoice && $existing->state instanceof Draft) {
            return $existing;
        }

        $invoice = SupplierInvoice::create(Arr::only($interaction->output ?? [], self::INVOICE_FIELDS));

        $invoice->addMedia($file->getRealPath())
            ->preservingOriginal()
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection('invoice');

        // L'interaction a été créée sans modèle, identifiée seulement par l'empreinte du fichier.
        // La rattacher au brouillon permet de le retrouver à la relance (ci-dessus), et de
        // marquer l'interaction appliquée quand il est validé (validateDraft()).
        $interaction->forceFill([
            'correctable_type' => SupplierInvoice::class,
            'correctable_id' => $invoice->getKey(),
        ])->save();

        return $invoice;
    }

    /** Enregistre un brouillon vérifié et le valide ; renvoie l'état obtenu. */
    private function validateDraft(array $row): ?string
    {
        $invoice = SupplierInvoice::find($row['invoice_id'] ?? null);

        if (! $invoice || ! $invoice->state instanceof Draft) {
            return null;
        }

        $invoice->fill(Arr::only($row, self::INVOICE_FIELDS))->save();
        $this->rememberAlternativeName($invoice, $row['supplier_name_from_invoice'] ?? null);

        // On demande Validated, mais DraftToValidated peut aboutir à Warning ou Error : d'où
        // le refresh() pour renvoyer l'état réellement obtenu.
        $invoice->state->transitionTo(Validated::class);

        AiInteraction::query()
            ->where('correctable_type', SupplierInvoice::class)
            ->where('correctable_id', $invoice->getKey())
            ->where('status', 'pending')
            ->get()
            ->each->markApplied();

        return $invoice->refresh()->state->getLabel();
    }

    /**
     * Le nom lu sur la facture devient un nom alternatif du fournisseur choisi, s'il en diffère et
     * qu'aucun autre fournisseur ne le porte déjà — la prochaine facture sera reconnue d'emblée.
     */
    private function rememberAlternativeName(SupplierInvoice $invoice, ?string $name): void
    {
        $supplier = $invoice->supplier;

        if (! $supplier || blank($name) || Str::contains($supplier->name, $name, ignoreCase: true) || Str::contains($name, $supplier->name, ignoreCase: true)) {
            return;
        }

        $conflict = Supplier::query()
            ->whereKeyNot($supplier->getKey())
            ->where(fn ($query) => $query->whereJsonContains('alternative_names', $name)->orWhere('name', $name))
            ->first();

        if ($conflict) {
            Notification::make()
                ->warning()
                ->title('Nom alternatif non ajouté')
                ->body("« {$name} » est déjà associé à {$conflict->name}.")
                ->send();

            return;
        }

        $names = $supplier->alternative_names ?? [];

        if (! in_array($name, $names, true)) {
            $supplier->update(['alternative_names' => [...$names, $name]]);
        }
    }

    /**
     * SIRET/SIREN, puis TVA, puis le choix de l'IA dans la liste, puis le nom (noms alternatifs,
     * nom exact ou contenu, sans forme juridique).
     *
     * Du plus sûr au moins sûr : un identifiant légal lu sur la facture ne se trompe pas de
     * fournisseur, le choix de l'IA peut se tromper, et une recherche par nom encore plus.
     * Le second élément renvoyé est la méthode qui a trouvé (`supplier_match`).
     *
     * @param  Collection<int, Supplier>  $suppliers
     * @return array{0: ?Supplier, 1: ?string}
     */
    private function matchSupplier(array $output, Collection $suppliers): array
    {
        $digits = fn (?string $value): string => preg_replace('/\D/', '', (string) $value);
        $vat = fn (?string $value): string => Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));

        // Un SIRET commence par le SIREN (9 chiffres) : un fournisseur connu seulement par son
        // SIREN est reconnu quel que soit l'établissement qui facture.
        if (strlen($siret = $digits($output['supplier_siret'] ?? null)) >= 9) {
            $supplier = $suppliers->first(fn (Supplier $s): bool => $digits($s->siret) === $siret
                || ($digits($s->siren) !== '' && str_starts_with($siret, $digits($s->siren))));

            if ($supplier) {
                return [$supplier, 'siret'];
            }
        }

        if (($number = $vat($output['supplier_vat'] ?? null)) !== '') {
            if ($supplier = $suppliers->first(fn (Supplier $s): bool => $vat($s->vat_number) === $number)) {
                return [$supplier, 'tva'];
            }
        }

        if ($supplier = $suppliers->firstWhere('id', (int) ($output['known_supplier_id'] ?? 0))) {
            return [$supplier, 'ia'];
        }

        $name = $this->string($output['supplier_name'] ?? null);

        if ($name === null) {
            return [null, null];
        }

        $candidates = array_unique(array_filter([$name, CompanyNameHelper::removeCompanyLegalForms($name)]));

        foreach ($candidates as $candidate) {
            $supplier = $suppliers->first(fn (Supplier $s): bool => collect($s->alternative_names ?? [])->contains(fn ($alt): bool => Str::lower($alt) === Str::lower($candidate))
                || Str::contains($s->name, $candidate, ignoreCase: true)
                || Str::contains((string) $s->legal_name, $candidate, ignoreCase: true));

            if ($supplier) {
                return [$supplier, 'nom'];
            }
        }

        return [null, null];
    }

    private function isDuplicate(int $supplierId, string $invoiceNumber): bool
    {
        return SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_number', $invoiceNumber)
            ->exists();
    }

    /** Une chaîne vide ou faite d'espaces vaut null, comme une information absente. */
    private function string(mixed $value): ?string
    {
        return is_scalar($value) && filled(trim((string) $value)) ? trim((string) $value) : null;
    }

    /** Arrondi au centime ; tout ce qui n'est pas un nombre vaut null. */
    private function amount(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * Le format demandé à l'IA (Y-m-d), puis les formats français au cas où elle recopie la
     * date telle qu'écrite. Le `!` remet l'heure à zéro. Reformater la date et la comparer à
     * l'entrée écarte les dates impossibles, que Carbon accepterait en débordant (31/02 donne
     * 03/03).
     */
    private function date(mixed $value): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, (string) $value);
            } catch (Throwable) {
                continue;
            }

            if ($date && $date->format($format) === (string) $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
