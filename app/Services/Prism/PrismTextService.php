<?php

namespace App\Services\Prism;

use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use RuntimeException;
use Throwable;

class PrismTextService
{
    public function correctJsonText(string $jsonText): string
    {
        $text = $this->generateText(
            systemPrompt: "Tu es un correcteur orthographique et grammatical francophone.
Tu reçois un JSON contenant des textes applicatifs.
Corrige uniquement l'orthographe, la grammaire, les accords et la ponctuation des valeurs textuelles.
Conserve strictement la structure JSON, les clés, les types, les nombres, les dates, les identifiants et les balises éventuelles.
Réponds uniquement avec le JSON corrigé, sans markdown ni commentaire.",
            prompt: $jsonText,
            model: config('ai.orthography.model')
        );

        return $this->extractJson($text);
    }

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

    protected function extractJson(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $startCandidates = array_filter([
                strpos($text, '{'),
                strpos($text, '['),
            ], fn($position) => $position !== false);

            $end = max(strrpos($text, '}') ?: -1, strrpos($text, ']') ?: -1);

            if ($startCandidates !== [] && $end >= 0) {
                $start = min($startCandidates);

                if ($end >= $start) {
                    $text = substr($text, $start, $end - $start + 1);
                    $decoded = json_decode($text, true);
                }
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Réponse IA invalide : format JSON incorrect.');
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
