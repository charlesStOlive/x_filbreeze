<?php 

namespace App\Traits;

trait HasFactuItems
{
    /**
     * Boot the trait and ensure 'items' is cast as JSON.
     */
    public static function bootHasItems()
    {
        static::retrieved(function ($model) {
            if (!array_key_exists('items', $model->casts)) {
                $model->casts['items'] = 'json';
            }
        });
    }

    /**
     * Ajouter un item au champ items.
     */
    public function addItem(string $type, array $data): self
    {
        $items = $this->items ?? []; // Récupère les items existants ou initialise un tableau vide

        $items[] = [
            'type' => $type,
            'data' => $data,
        ];

        $this->items = $items; // Met à jour les items

        return $this;
    
    }

    /**
     * Calculer des totaux personnalisés (exemple).
     */
    public function calculateTotals(): self
    {
        $items = $this->items ?? [];

        $totals = collect($items)->partition(fn($item) => $item['type'] === 'remise');

        $totalRemise = $totals[0]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        $totalHtBr = $totals[1]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        $totalHt = $totalHtBr - $totalRemise;

        $this->total_ht_br = $totalHtBr;
        $this->total_ht = $totalHt;

        return $this;
    }
}
