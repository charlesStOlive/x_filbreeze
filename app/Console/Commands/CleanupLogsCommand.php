<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupLogsCommand extends Command
{
    /**
     * Nom et signature de la commande.
     *
     * Exemples :
     *  php artisan logs:cleanup
     *  php artisan logs:cleanup --comment
     *  php artisan logs:cleanup --delete --include-error
     *  php artisan logs:cleanup --path=app --path=packages
     */
    protected $signature = 'logs:cleanup
        {--comment : Commente les appels à Log au lieu de seulement les lister}
        {--delete : Supprime les appels à Log au lieu de seulement les lister}
        {--include-error : Inclut aussi les Log::error() / \\Log::error() dans le traitement}
        {--path=* : Répertoires à scanner (par défaut: app, routes, packages)}';

    /**
     * Description de la commande.
     */
    protected $description = 'Scanner le code pour trouver les appels à Log::...() / \\Log::...() (une ou plusieurs lignes), puis les lister, commenter ou supprimer.';

    public function handle(): int
    {
        $modeComment = (bool) $this->option('comment');
        $modeDelete  = (bool) $this->option('delete');

        if ($modeComment && $modeDelete) {
            $this->error('Tu ne peux pas utiliser --comment et --delete en même temps.');
            return self::INVALID;
        }

        $includeError = (bool) $this->option('include-error');

        $paths = $this->option('path');

        // Par défaut, on scanne app, routes, packages
        if (empty($paths)) {
            $paths = ['app', 'routes', 'packages', 'tests'];
        }

        $files = collect();

        foreach ($paths as $relativePath) {
            $fullPath = base_path($relativePath);

            if (! is_dir($fullPath)) {
                $this->warn("Répertoire introuvable (ignoré) : {$relativePath}");
                continue;
            }

            $files = $files->merge(File::allFiles($fullPath));
        }

        if ($files->isEmpty()) {
            $this->info('Aucun fichier trouvé à scanner.');
            return self::SUCCESS;
        }

        $totalMatches = 0;
        $totalFilesTouched = 0;

        foreach ($files as $file) {
            $result = $this->processFile(
                $file->getPathname(),
                $includeError,
                $modeComment,
                $modeDelete
            );

            if ($result['matches'] > 0) {
                $totalMatches += $result['matches'];
                if ($result['touched']) {
                    $totalFilesTouched++;
                }
            }
        }

        $this->info("Analyse terminée. {$totalMatches} bloc(s) Log trouvé(s), {$totalFilesTouched} fichier(s) modifié(s).");

        if (! $modeComment && ! $modeDelete) {
            $this->info('Aucune modification appliquée (mode lecture seule). Utilise --comment ou --delete pour modifier le code.');
        }

        return self::SUCCESS;
    }

    /**
     * Traite un fichier : détecte les blocs Log, et éventuellement les commente / supprime.
     *
     * @return array{matches:int,touched:bool}
     */
    protected function processFile(string $path, bool $includeError, bool $modeComment, bool $modeDelete): array
    {
        // 🔒 On ne travaille que sur les fichiers PHP (y compris *.blade.php)
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension !== 'php') {
            return ['matches' => 0, 'touched' => false];
        }

        // Lire le fichier en préservant l'encodage original
        $originalContent = file_get_contents($path);

        // 🔐 Détecter le type de fin de lignes utilisé dans le fichier
        // Si on trouve \r\n, on suppose du CRLF, sinon LF
        $eol = str_contains($originalContent, "\r\n") ? "\r\n" : "\n";

        // Normaliser les fins de ligne puis découper
        $normalizedContent = str_replace(["\r\n", "\r"], "\n", $originalContent);
        $lines = explode("\n", $normalizedContent);

        $newLines = [];
        $matchesCount = 0;
        $touched = false;

        $inLogBlock = false;
        $logBlockLines = [];
        $logBlockStartLine = null;
        $logLevel = null;

        /**
         * Regex de début de bloc Log :
         * - début de ligne + indentation éventuelle
         * - \Log::xxx( ou Log::xxx(
         * - xxx ∈ niveaux standards + success/log
         */
        $startPattern = '/^\s*\\\?Log::(?P<level>emergency|alert|critical|error|warning|notice|info|debug|success|log)\s*\(/';

        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];

            if (! $inLogBlock) {
                if (preg_match($startPattern, $line, $matches)) {
                    $detectedLevel = $matches['level'] ?? null;

                    // Par défaut, on exclut les error, sauf si --include-error
                    if (! $includeError && $detectedLevel === 'error') {
                        $newLines[] = $line;
                        continue;
                    }

                    $inLogBlock = true;
                    $logBlockLines = [$line];
                    $logBlockStartLine = $i + 1; // numérotation 1-based pour affichage
                    $logLevel = $detectedLevel;

                    // Cas une seule ligne : on a déjà un ';'
                    if (str_contains($line, ';')) {
                        $inLogBlock = false;
                        $matchesCount++;
                        $touched = $touched || $modeComment || $modeDelete;

                        $this->handleLogBlock(
                            $logBlockLines,
                            $newLines,
                            $modeComment,
                            $modeDelete,
                            $path,
                            $logBlockStartLine,
                            $logLevel
                        );

                        // reset
                        $logBlockLines = [];
                        $logBlockStartLine = null;
                        $logLevel = null;
                    }
                } else {
                    $newLines[] = $line;
                }
            } else {
                // On est déjà dans un bloc Log multi-lignes
                $logBlockLines[] = $line;

                // On arrête au premier ';' (normalement le ); de fin de log)
                if (str_contains($line, ';')) {
                    $inLogBlock = false;
                    $matchesCount++;
                    $touched = $touched || $modeComment || $modeDelete;

                    $this->handleLogBlock(
                        $logBlockLines,
                        $newLines,
                        $modeComment,
                        $modeDelete,
                        $path,
                        $logBlockStartLine ?? ($i + 1),
                        $logLevel
                    );

                    // reset
                    $logBlockLines = [];
                    $logBlockStartLine = null;
                    $logLevel = null;
                }
            }
        }

        // Si un bloc a été commencé mais pas terminé proprement, on le laisse tel quel
        if ($inLogBlock && ! empty($logBlockLines)) {
            foreach ($logBlockLines as $remainingLine) {
                $newLines[] = $remainingLine;
            }
        }

        // On ne réécrit le fichier que si on est en mode comment/delete
        if ($modeComment || $modeDelete) {
            // On reconstruit le contenu avec le même EOL qu’à l’origine
            $newContent = implode($eol, $newLines);

            // Préserver la présence ou absence de fin de ligne finale
            $originalEndsWithEol = $originalContent !== '' && str_ends_with($originalContent, $eol);
            $newEndsWithEol = $newContent !== '' && str_ends_with($newContent, $eol);

            if ($originalEndsWithEol && ! $newEndsWithEol) {
                $newContent .= $eol;
            } elseif (! $originalEndsWithEol && $newEndsWithEol) {
                // On enlève l’EOL final si le fichier original n’en avait pas
                $newContent = substr($newContent, 0, -strlen($eol));
            }

            if ($newContent !== $originalContent) {
                file_put_contents($path, $newContent, LOCK_EX);
                $this->line("Modifié : {$path}");
            }
        }

        return [
            'matches' => $matchesCount,
            'touched' => ($modeComment || $modeDelete) ? $matchesCount > 0 : false,
        ];
    }

    /**
     * Gère un bloc Log trouvé : affichage + ajout dans $newLines commenté ou non.
     *
     * @param  string[]  $logBlockLines
     * @param  string[]  $newLines  (référence)
     */
    protected function handleLogBlock(
        array $logBlockLines,
        array &$newLines,
        bool $modeComment,
        bool $modeDelete,
        string $path,
        int $lineNumber,
        ?string $logLevel
    ): void {
        $preview = trim(implode(' ', $logBlockLines));
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117) . '...';
        }

        $levelInfo = $logLevel ? " [level: {$logLevel}]" : '';
        $this->line(" - {$path}:{$lineNumber}{$levelInfo} → {$preview}");

        if ($modeDelete) {
            // On n’ajoute rien → bloc supprimé
            return;
        }

        if ($modeComment) {
            foreach ($logBlockLines as $blockLine) {
                // On préserve l’indentation et on ajoute //
                $newLines[] = preg_replace('/^(\s*)/', '$1// ', $blockLine);
            }

            return;
        }

        // Mode lecture seule : on recopie tel quel
        foreach ($logBlockLines as $blockLine) {
            $newLines[] = $blockLine;
        }
    }
}
