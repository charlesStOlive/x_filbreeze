<?php 

namespace App\Services\Models;

use Illuminate\Support\Collection;

class ItemsManager
{
    protected Collection $items;

    public function __construct(array $items = [])
    {
        $this->items = collect($items);
    }

    public function all(): Collection
    {
        return $this->items;
    }

    public function add(string $type, array $data): self
    {
        $this->items->push([
            'type' => $type,
            'data' => $data,
        ]);

        return $this;
    }

    public function update(int $index, array $data): self
    {
        if ($this->items->has($index)) {
            $this->items[$index]['data'] = array_merge($this->items[$index]['data'], $data);
        }

        return $this;
    }

    public function remove(int $index): self
    {
        $this->items->forget($index);

        return $this;
    }

    public function calculateTotals(): array
    {
        $totals = $this->items->partition(fn($item) => $item['type'] === 'remise');

        $totalRemise = $totals[0]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        $totalHtBr = $totals[1]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        $totalHt = $totalHtBr - $totalRemise;

        return [
            'total_ht_br' => $totalHtBr,
            'total_ht' => $totalHt,
        ];
    }
}
