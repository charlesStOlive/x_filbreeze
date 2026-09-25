<?php

namespace App\Ai;

use CharlesStOlive\FilamentPrism\Resources\AiResource;

/**
 * Traduction du message d'un brouillon Outlook, déclenchée par le code `## traduit -en ##`
 * (voir `TradEmailProcessor`). Seul le message rédigé est envoyé, sans l'historique de la
 * conversation ni l'enveloppe du document (`DraftBody`).
 *
 * Entièrement automatique : pas de formulaire d'entrée ni de réception. C'est le processeur,
 * dans la file d'attente, qui appelle `AiRunner::run()` puis applique lui-même le résultat
 * au brouillon. Cette ressource ne décrit donc que l'appel : quoi envoyer, et comment lire
 * la réponse.
 */
class EmailTranslation extends AiResource
{
    public function key(): string
    {
        return 'email-translation';
    }

    public function label(): string
    {
        return 'Traduction des emails';
    }

    public function model(): string
    {
        return (string) config('filament-prism.email_translation.model');
    }

    // ── L'appel ───────────────────────────────────────────────────────────

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
            Tu es un traducteur professionnel. On te donne le message HTML d'un email (sans
            l'historique de la conversation) et la langue cible, sous forme de code ISO 639-1
            (en = anglais, es = espagnol, de = allemand...).

            Règles :
            - Traduis uniquement le texte lisible vers la langue cible.
            - Conserve exactement le HTML : balises, attributs, styles, liens, images. C'est
              souvent un fragment dont des balises restent ouvertes : ne ferme, n'ajoute et
              ne retire aucune balise.
            - Ne traduis pas les noms propres, adresses email, numéros, variables ni les
              signatures (nom, fonction, coordonnées).
            - Réponds uniquement avec le HTML traduit, sans commentaire ni bloc de code Markdown.
            PROMPT;
    }

    /** @param  array{html: string, language: string}  $input */
    public function prompt(array $input, array $context): string
    {
        return "Langue cible : {$input['language']}\n\nContenu HTML :\n{$input['html']}";
    }

    // Pas de responseSchema() : la réponse est du texte libre (output = ['text' => ...]).

    // ── Après l'appel ─────────────────────────────────────────────────────

    /**
     * Retire le bloc de code Markdown (```html ... ```) dont l'IA entoure parfois sa réponse
     * malgré la consigne : envoyé tel quel, il s'afficherait dans l'email.
     *
     * @param  array{text?: string}  $output
     * @return array{text: string}
     */
    public function resolve(array $output, array $input, array $context): array
    {
        $text = trim((string) ($output['text'] ?? ''));

        if (preg_match('/^```[a-z]*\s*\n(.*)\n```$/is', $text, $matches)) {
            $text = trim($matches[1]);
        }

        return ['text' => $text];
    }
}
