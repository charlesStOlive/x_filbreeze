<?php

namespace App\Services\Prism;

use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use RuntimeException;
use Throwable;

class PrismTextService
{
    public function translateHtml(string $html, string $targetLanguage, ?string $model = null): string
    {
        return $this->generateText(
            systemPrompt: "Tu es un traducteur professionnel.
Traduis le contenu vers la langue cible demandée.
Conserve le HTML, les liens, les variables, les signatures, les espaces utiles et la mise en forme.
Ne rajoute aucun commentaire et réponds uniquement avec le contenu traduit.",
            prompt: "Langue cible: {$targetLanguage}

Contenu HTML:
{$html}",
            model: $model ?: config('ai.translation.model')
        );
    }

    protected function generateText(string $systemPrompt, string $prompt, ?string $model = null): string
    {
        try {
            return Prism::text()
                ->using(config('ai.provider'), $model ?: config('ai.model'))
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($prompt)
                ->asText()
                ->text;
        } catch (Throwable $e) {
            Log::error('Erreur Prism', [
                'provider' => config('ai.provider'),
                'model' => $model ?: config('ai.model'),
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Erreur lors de l’appel au service IA : ' . $e->getMessage(), previous: $e);
        }
    }
}
