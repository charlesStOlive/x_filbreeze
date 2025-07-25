<?php

namespace App\Services\MaatImports\Templates\Product;

use App\Models\Gamme;
use App\Models\Product;
use App\Enums\ProductType;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Radio;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Services\MaatImports\Base\BaseMaatImporter;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class ProductImporter extends BaseMaatImporter implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    public static function getDefaultOptions(): array
    {
        return [
            'create_missing_gamme' => false,
        ];
    }

    public function getForm(): array
    {
        return [
            Radio::make('create_missing_gamme')
                ->label('Les gammes inexistantes seront-elles créées ?')
                ->options([
                    true => 'Créer automatiquement les gammes manquantes',
                    false => 'Bloquer une gamme inexistante',
                ])
                ->descriptions([
                    true => 'La gamme sera créée à partir de la cellule Excel. Attention aux erreurs de frappe.',
                    false => 'Si la gamme est manquante, la ligne sera ignorée si cette option est désactivée.',
                ])
                ->default($this->getOption('create_missing_gamme')),
        ];
    }

    public function collection(Collection $rows): void
    {
        $options = $this->getMergedOptions();

        foreach ($rows as $index => $r) {
            $line = $index + 2;

            $id         = $r['id'] ?? null;
            $code       = $r['code'] ?? null;
            $title      = $r['title'] ?? null;
            $type       = $this->normalizeType($r['type'] ?? null);
            $gammeSlug  = $r['gamme'] ?? null;
            $unitPrice  = $r['unit_price'] ?? 0;

            try {
                if (!$code || !$title) {
                    throw new \Exception("Champs obligatoires manquants (code ou title)");
                }

                // Gestion de la gamme
                $gammeId = null;
                if ($gammeSlug) {
                    $existing = Gamme::firstWhere('name', $gammeSlug);
                    if ($existing) {
                        $gammeId = $existing->id;
                    } elseif ($options['create_missing_gamme']) {
                        $gamme = Gamme::create([
                            'name' => Str::headline($gammeSlug),
                            'slug' => Str::slug($gammeSlug),
                        ]);
                        $gammeId = $gamme->id;
                    } else {
                        throw new \Exception("Gamme slug '$gammeSlug' introuvable.");
                    }
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
