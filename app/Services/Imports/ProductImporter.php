<?php 

namespace App\Services\Imports;

use App\Models\Product;
use App\Models\Gamme;
use App\Enums\ProductType;
use Filament\Forms\Components\Checkbox;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class ProductImporter extends BaseFilImporter implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    public function getForm(): array
    {
        return [
            Checkbox::make('create_missing_gamme')
                ->label('Créer automatiquement les gammes manquantes')
                ->live()
                ->default($this->options['create_missing_gamme'] ?? false),

            Checkbox::make('block_if_gamme_missing')
                ->label('Refuser la création si la gamme est absente')
                ->default($this->options['block_if_gamme_missing'] ?? true)
                ->live(),
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $r) {
            $line = $index + 2;

            $id         = $r['id'] ?? null;
            $code       = $r['code'] ?? null;
            $title      = $r['title'] ?? null;
            $type       = $this->normalizeType($r['type'] ?? null);
            $gammeName  = $r['gamme'] ?? null;
            $unitPrice  = $r['unit_price'] ?? 0;

            try {
                if (!$code || !$title) {
                    throw new \Exception("Champs obligatoires manquants (code ou title)");
                }

                // Gestion de la gamme
                $gammeId = null;
                if ($gammeName) {
                    $existing = Gamme::firstWhere('name', $gammeName);
                    if ($existing) {
                        $gammeId = $existing->id;
                    } elseif ($this->options['create_missing_gamme'] ?? false) {
                        $gamme = Gamme::create([
                            'name' => $gammeName,
                            'slug' => str($gammeName)->slug(),
                        ]);
                        $gammeId = $gamme->id;
                    } elseif ($this->options['block_if_gamme_missing'] ?? false) {
                        throw new \Exception("Gamme '$gammeName' introuvable.");
                    }
                } elseif ($this->options['block_if_gamme_missing'] ?? false) {
                    throw new \Exception("Gamme absente.");
                }

                if ($id && $product = Product::find($id)) {
                    if (Product::where('code', $code)->where('id', '!=', $id)->exists()) {
                        throw new \Exception("Code '$code' déjà utilisé.");
                    }

                    $product->update([
                        'code'       => $code,
                        'title'      => $title,
                        'type'       => $type,
                        'gamme_id'   => $gammeId,
                        'unit_price' => $unitPrice,
                    ]);
                    $this->updated++;
                    continue;
                }

                if (Product::where('code', $code)->exists()) {
                    throw new \Exception("Code '$code' déjà existant.");
                }

                Product::create([
                    'code'       => $code,
                    'title'      => $title,
                    'type'       => $type,
                    'gamme_id'   => $gammeId,
                    'unit_price' => $unitPrice,
                ]);
                $this->created++;
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'line'  => $line,
                    'code'  => $code,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    protected function normalizeType(?string $type): string
    {
        $valid = collect(ProductType::cases())->pluck('value');
        return $valid->contains($type)
            ? $type
            : ProductType::FORFAIT_U->value;
    }
}
