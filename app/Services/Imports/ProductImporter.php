<?php

namespace App\Services\Imports;

use App\Models\Product;
use App\Models\Gamme;
use App\Enums\ProductType;
use Illuminate\Support\Collection;
use App\Traits\SendsNotifications;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Filament\Forms\Components\Checkbox;

class ProductImporter implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    use SendsNotifications;

    public array $errors = [];
    public int $created = 0;
    public int $updated = 0;

    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public static function getForm(): array
    {
        return [
            Checkbox::make('create_missing_gamme')
                ->label('Créer automatiquement les gammes manquantes')
                ->live(),

            Checkbox::make('block_if_gamme_missing')
                ->label('Refuser la création si la gamme est absente')
                ->default(true)
        ];
    }

    public function collection(Collection $rows): void
    {
        \Log::info($this->options);
        foreach ($rows as $index => $r) {
            $line = $index + 2; // Excel commence à la ligne 1 avec header

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
                    \Log::info("Traitement de la gamme: $gammeName");
                    $existing = Gamme::firstWhere('name', $gammeName);
                    if ($existing) {
                        $gammeId = $existing->id;
                    } elseif ($this->options['create_missing_gamme'] ?? false) {
                        $gamme = Gamme::create([
                            'name' => $gammeName,
                            'slug' => str($gammeName)->slug(),
                        ]);
                        $gammeId = $gamme->id;
                        \Log::info("Gamme '$gammeName' créée avec ID: $gammeId");
                    } elseif ($this->options['block_if_gamme_missing'] ?? false) {
                        throw new \Exception("Gamme '$gammeName' introuvable. Ligne ignorée.");
                    }
                } elseif ($this->options['block_if_gamme_missing'] ?? false) {
                    throw new \Exception("Gamme absente. Ligne ignorée.");
                }

                if ($id && $product = Product::find($id)) {
                    if (Product::where('code', $code)->where('id', '!=', $id)->exists()) {
                        throw new \Exception("Code '$code' déjà utilisé par un autre produit.");
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
                    throw new \Exception("Code '$code' déjà existant (création refusée).");
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

    public function finalize(): void
    {
        if (empty($this->errors)) {
            $this->notifySuccess(
                'Import des produits terminé',
                "✅ {$this->created} créés, {$this->updated} mis à jour"
            );
        } else {
            $message = "❌ {$this->created} créés, {$this->updated} mis à jour, " . count($this->errors) . " erreurs.";
            $this->notifyError('Import produits partiellement échoué', $message);
        }
    }
}
