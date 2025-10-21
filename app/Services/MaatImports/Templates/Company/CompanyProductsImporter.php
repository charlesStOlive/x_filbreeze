<?php

namespace App\Services\MaatImports\Templates\Company;

use Exception;
use Throwable;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Support\Collection;
use Filament\Schemas\Components\Text;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Services\MaatImports\Base\BaseMaatImporter;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class CompanyProductsImporter extends BaseMaatImporter implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    public static function key(): string
    {
        return 'company_products_importer';
    }

    public static function label(): string
    {
        return 'Import Produits Entreprise';
    }

    public static function getDefaultOptions(): array
    {
        return [];
    }

    public function getForm(): array
    {
        return [
            Text::make('Attention seul les infos contenus dans l\'excel seront conservés, le reste sera supprimé.')
                ->color('info')
                ->icon('heroicon-o-information-circle')
        ];
    }

    public function collection(Collection $rows): void
    {
        $company = $this->getRecord();

        if (! $company instanceof Company) {
            $this->errors[] = [
                'line' => 0,
                'code' => null,
                'error' => 'Aucune entreprise (Company) n’a été transmise pour l’import.',
            ];
            return;
        }

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $code = $row['code'] ?? null;
            $unitPrice = $row['unit_price'] ?? null;

            try {
                if (!$code || !is_numeric($unitPrice)) {
                    throw new Exception("Données manquantes ou invalides.");
                }

                $product = Product::where('code', $code)->first();

                if (!$product) {
                    throw new Exception("Produit avec code '$code' introuvable.");
                }

                $company->products()->syncWithoutDetaching([
                    $product->id => ['unit_price' => $unitPrice],
                ]);

                $this->updated++;
            } catch (Throwable $e) {
                $this->errors[] = [
                    'line' => $line,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }
}
