<?php

namespace App\Traits;

use CharlesStOlive\FilamentPrism\Concerns\Correctable;
use CharlesStOlive\FilamentPrism\Support\CorrectableField;
use Illuminate\Support\Arr;

/**
 * Correction orthographique (filament-prism) d'un document à lignes (devis,
 * facture) : son titre et sa description, plus le titre et la description de
 * chaque ligne du Builder `items` (`items.*.data.title|description`).
 *
 * `Correctable` ne gère que des colonnes à plat, déclarées statiquement : les
 * lignes, propres à chaque document, sont donc exposées à l'IA comme autant de
 * champs à plat (`item_1_title`, `item_1_description`...), puis réécrites à
 * leur place dans `items` par `applyValues()`. Un texte vide n'est pas envoyé.
 *
 * Le modèle doit aussi déclarer `implements CorrectionSubject`.
 */
trait CorrectableWithItems
{
    use Correctable;

    /** @return array<int, CorrectableField> */
    public static function correctableFields(): array
    {
        return [
            CorrectableField::make('title')->label('Titre'),
            CorrectableField::make('description')->label('Description'),
        ];
    }

    /** @return array<int, CorrectableField> */
    public function fields(): array
    {
        return collect($this->correctableTexts())
            ->map(fn (array $text, string $name): CorrectableField => CorrectableField::make($name)->label($text['label']))
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    public function extractValues(): array
    {
        return collect($this->correctableTexts())
            ->map(fn (array $text): string => (string) data_get($this, $text['path']))
            ->all();
    }

    /** @param  array<string, mixed>  $values */
    public function applyValues(array $values): void
    {
        $items = $this->items ?? [];

        foreach ($this->correctableTexts() as $name => $text) {
            if (! array_key_exists($name, $values)) {
                continue;
            }

            [$attribute, $itemPath] = array_pad(explode('.', $text['path'], 2), 2, null);

            if ($itemPath === null) {
                $this->{$attribute} = $values[$name];
            } else {
                Arr::set($items, $itemPath, $values[$name]);
            }
        }

        $this->items = $items;
    }

    /**
     * Les textes non vides à corriger, par nom de champ envoyé à l'IA.
     *
     * @return array<string, array{path: string, label: string}>
     */
    protected function correctableTexts(): array
    {
        $texts = [];

        foreach (static::correctableFields() as $field) {
            if (filled($this->{$field->name})) {
                $texts[$field->name] = ['path' => $field->name, 'label' => $field->getLabel()];
            }
        }

        $position = 0;

        foreach ($this->items ?? [] as $index => $item) {
            $position++;

            foreach (['title' => 'titre', 'description' => 'description'] as $key => $label) {
                if (is_string($item['data'][$key] ?? null) && filled($item['data'][$key])) {
                    $texts["item_{$position}_{$key}"] = [
                        'path' => "items.{$index}.data.{$key}",
                        'label' => "Ligne {$position} — {$label}",
                    ];
                }
            }
        }

        return $texts;
    }
}
