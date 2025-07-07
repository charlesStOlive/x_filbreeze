<?php

namespace App\Services\Imports;

use App\Models\Company;
use App\Models\Product;
use App\Contracts\HasFillForm;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Hidden;
use App\Services\Imports\BaseImporter;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class CompanyProductsImporter extends BaseFilImporter implements ToCollection, WithHeadingRow, WithCalculatedFormulas, HasFillForm
{
    protected Company $company;

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        // Exiger une company dans les options
        if (!isset($options['company_id']) || ! $this->company = Company::find($options['company_id'])) {
            throw new \InvalidArgumentException("Company manquante ou introuvable pour l'import.");
        }
    }

    public static function getFillForm(mixed $livewire): array
    {
        return ['company_id' => $livewire->getOwnerRecord()->id];
    }

    public static function getForm(): array
    {
        return [
            Hidden::make('company_id'),
        ];
    }

    

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $code = $row['code'] ?? null;
            $unitPrice = $row['unit_price'] ?? null;

            try {
                if (!$code || !is_numeric($unitPrice)) {
                    throw new \Exception("Données manquantes ou invalides.");
                }

                $product = Product::where('code', $code)->first();

                if (!$product) {
                    throw new \Exception("Produit avec code '$code' introuvable.");
                }

                $this->company->products()->syncWithoutDetaching([
                    $product->id => ['unit_price' => $unitPrice],
                ]);

                $this->updated++;
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'line' => $line,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }
}
