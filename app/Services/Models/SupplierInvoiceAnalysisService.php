<?php

namespace App\Services\Models;

use Throwable;
use App\Models\Supplier;
use App\DTO\SupplierInvoiceExtractionDTO;
use Prism\Prism\Facades\Prism;
use Illuminate\Support\Facades\Log;
use App\Services\Processors\FileProcessor; // Votre processeur existant
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Support\CompanyNameHelper;



class SupplierInvoiceAnalysisService
{
    protected FileProcessor $processor;

    public function __construct(FileProcessor $processor)
    {
        $this->processor = $processor;
    }

    public function analyze(TemporaryUploadedFile $file): array
    {
        try {
            // 1. Extraction du texte (Votre logique existante conservée)
            $extractedData = $this->processor->processFile($file->getRealPath());
            $textContent = $extractedData['content'] ?? '';

            if (empty($textContent)) {
                throw new \Exception("Impossible d'extraire le texte du fichier.");
            }

            $schema = SupplierInvoiceExtractionDTO::prismSchema(
                name: 'supplier_invoice_extraction',
                description: 'Extraction de données structurées depuis une facture fournisseur'
            );

            $prismResult = Prism::structured()
                ->using('mistral', 'mistral-large-latest')
                ->withSchema($schema) // ✅ CORRECT
                ->withSystemPrompt(
                    "Tu es un assistant comptable expert spécialisé dans l’analyse de factures fournisseurs.

                    Règles STRICTES :
                    - Tu dois répondre uniquement au format demandé par le schéma.
                    - Si une information est absente ou introuvable dans le document, mets la valeur null.
                    - Ne devine jamais et n’invente aucune donnée.
                    - Les montants doivent être numériques, sans symbole (€, %, etc.).
                    - Les dates doivent être fournies sous forme de texte (idéalement YYYY-MM-DD si possible).

                    Objectif :
                    Extraire les données de la facture et identifier le fournisseur,
                    en recherchant spécifiquement le SIRET et le numéro de TVA."
                )
                ->withPrompt("Analyse le contenu suivant :\n" . $textContent)
                ->asStructured(); // ✅ CORRECT


            $dto = SupplierInvoiceExtractionDTO::from($prismResult->structured);

            // 3. Logique de Matching PHP (On cherche le fournisseur en BDD)
            $supplier = $this->findSupplierInDatabase($dto);

            return [
                'success' => true,
                'dto' => $dto,
                'supplier_id' => $supplier?->id, // Null si non trouvé
                'supplier_name_from_invoice' => $dto->supplier_name, // Le nom trouvé sur la facture
                'warning' => $supplier ? null : 'Fournisseur non reconnu en BDD',
            ];
        } catch (Throwable $e) {
            Log::error("Erreur analyse facture : " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Tente de trouver le fournisseur par SIRET, TVA, ou Nom (avec recherche intelligente)
     */
    protected function findSupplierInDatabase(SupplierInvoiceExtractionDTO $dto): ?Supplier
    {
        // 1. Priorité absolue : SIRET (Nettoyage des espaces)
        // if ($dto->supplier_siret) {
        //     $cleanSiret = preg_replace('/[^0-9]/', '', $dto->supplier_siret);
        //     $supplier = Supplier::where('siret', 'LIKE', "%$cleanSiret%")->first();
        //     if ($supplier) {
        //         Log::info("Fournisseur trouvé par SIRET : {$supplier->name}");
        //         return $supplier;
        //     }
        // }

        // 2. Priorité : TVA Intracom
        // if ($dto->supplier_vat) {
        //     $cleanVat = str_replace(' ', '', $dto->supplier_vat);
        //     $supplier = Supplier::where('vat_number', 'LIKE', "%$cleanVat%")->first();
        //     if ($supplier) {
        //         Log::info("Fournisseur trouvé par TVA : {$supplier->name}");
        //         return $supplier;
        //     }
        // }

        // 3. Recherche intelligente par nom
        if ($dto->supplier_name) {
            return $this->searchSupplierByName($dto->supplier_name);
        }

        return null;
    }

    /**
     * Recherche intelligente par nom avec plusieurs stratégies
     */
    protected function searchSupplierByName(string $name): ?Supplier
    {
        Log::info("Recherche fournisseur par nom : $name");

        // Stratégie 0 : Recherche dans les noms alternatifs
        $supplier = Supplier::whereJsonContains('alternative_names', $name)->first();
        if ($supplier) {
            Log::info("Trouvé (via alternative_names) : {$supplier->name}");
            return $supplier;
        }

        // Stratégie 1 : Recherche directe
        $supplier = Supplier::where('name', 'LIKE', "%$name%")->first();
        if ($supplier) {
            Log::info("Trouvé (recherche directe) : {$supplier->name}");
            return $supplier;
        }

        // Stratégie 2 : Nettoyage des formes juridiques
        $cleanedName = CompanyNameHelper::removeCompanyLegalForms($name);
        if ($cleanedName !== $name) {
            $supplier = Supplier::where('name', 'LIKE', "%$cleanedName%")->first();
            if ($supplier) {
                Log::info("Trouvé (sans forme juridique) : {$supplier->name}");
                return $supplier;
            }
        }

        // Stratégie 3 : Recherche par mots-clés significatifs
        $keywords = CompanyNameHelper::extractSignificantKeywords($name);
        foreach ($keywords as $keyword) {
            if (strlen($keyword) >= 3) { // Ignorer les mots trop courts
                $supplier = Supplier::where('name', 'LIKE', "%$keyword%")->first();
                if ($supplier) {
                    Log::info("Trouvé (par mot-clé '$keyword') : {$supplier->name}");
                    return $supplier;
                }
            }
        }

        Log::warning("Aucun fournisseur trouvé pour : $name");
        return null;
    }
}