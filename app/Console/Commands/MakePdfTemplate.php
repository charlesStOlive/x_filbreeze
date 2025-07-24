<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakePdfTemplate extends Command
{
    protected $signature = 'make:template-pdf {model} {name} {--register}';
    protected $description = 'Crée un template PDF Laravel/Filament dynamique pour un modèle donné';

    public function handle()
    {
        $model = Str::studly($this->argument('model'));        // Invoice

        if (!class_exists("App\\Models\\{$model}")) {
            $this->error("❌ Le modèle App\\Models\\{$model} n'existe pas.");
            return Command::FAILURE;
        }

        $name = Str::lower($this->argument('name'));           // base
        $baseStudly = Str::studly($name);                      // Base
        $baseSnake = Str::snake($name);                        // base
        $baseKebab = Str::kebab($name);                        // base

        $var = Str::camel($model);                             // $invoice
        $kebab = Str::kebab($model);                           // invoice
        $className = "{$model}{$baseStudly}PdfTemplate";       // InvoiceBasePdfTemplate
        $viewPath = "pdf.{$kebab}.{$baseKebab}";               // pdf.invoice.base

        $templatePath = app_path("Services/Pdf/Templates/{$model}/{$className}.php");
        $viewFilePath = resource_path("views/pdf/{$kebab}/{$baseKebab}.blade.php");

        $this->makeDirectory(dirname($templatePath));
        $this->makeDirectory(dirname($viewFilePath));

        $this->generateClass($templatePath, [
            'model' => $model,
            'var' => $var,
            'view' => $viewPath,
            'key' => "{$kebab}_{$baseSnake}_pdf",
            'label' => "{$model} {$baseStudly}",
            'className' => $className,
        ]);

        $this->generateView($viewFilePath, ['var' => $var]);

        $this->info("✅ Classe créée : {$templatePath}");
        $this->info("✅ Vue créée    : {$viewFilePath}");

        if ($this->option('register')) {
            $this->registerInConfig($model, $className);
            $this->info("🔧 Enregistré dans config/templates-pdf.php");

            $this->callSilent('config:clear');
            $this->info("♻️  Cache config vidé (config:clear)");
        }
    }

    protected function generateClass(string $path, array $replacements)
    {
        $stub = file_get_contents(__DIR__ . '/stubs/templates/pdf/template.stub');

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ ' . $key . ' }}', $value, $stub);
        }

        file_put_contents($path, $stub);
    }

    protected function generateView(string $path, array $replacements)
    {
        $stub = file_get_contents(__DIR__ . '/stubs/templates/pdf/view.stub');

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ ' . $key . ' }}', $value, $stub);
        }

        file_put_contents($path, $stub);
    }

    protected function makeDirectory(string $path)
    {
        if (!is_dir($path)) {
            (new Filesystem)->makeDirectory($path, 0755, true);
        }
    }

    protected function registerInConfig(string $model, string $className)
    {
        $configPath = config_path('templates-pdf.php');
        $originalContent = file_get_contents($configPath);
        $content = $originalContent;

        $fqcnModel = "\\App\\Models\\{$model}::class";
        $fqcnTemplate = "\\App\\Services\\Pdf\\Templates\\{$model}\\{$className}::class";
        $modelKey = Str::kebab($model);

        /**
         * 1. Ajouter dans 'types' si absent
         */
        $typesPattern = "/'types'\s*=>\s*\[([\s\S]*?)\],/";
        if (preg_match($typesPattern, $content, $matches)) {
            $typesBlock = $matches[1];

            if (!Str::contains($typesBlock, "{$fqcnModel} =>")) {
                $newTypesBlock = rtrim($typesBlock) . "\n        {$fqcnModel} => '{$modelKey}',";
                $content = str_replace($typesBlock, $newTypesBlock, $content);
            }
        }

        /**
         * 2. Ajouter le bloc {modelKey} => [...] si absent
         */
        if (!Str::contains($content, "'{$modelKey}' => [")) {
            $insertion = "\n\n    '{$modelKey}' => [\n" .
                "        'default' => {$fqcnTemplate},\n" .
                "        'templates' => [\n" .
                "            {$fqcnTemplate},\n" .
                "        ],\n    ],";

            $content = preg_replace('/\],\s*\n\];/', '],' . $insertion . "\n];", $content);
            if ($content !== $originalContent) {
                file_put_contents($configPath, $content);
            }
            return;
        }

        /**
         * 3. Ajouter dans 'templates'[] si manquant
         */
        $templatesPattern = "/'{$modelKey}'\s*=>\s*\[([\s\S]*?)'templates'\s*=>\s*\[([\s\S]*?)\],/m";
        if (preg_match($templatesPattern, $content, $matches)) {
            $fullSection = $matches[0];
            $beforeTemplates = $matches[1];
            $templatesBlock = $matches[2];

            if (!Str::contains($templatesBlock, $fqcnTemplate)) {
                $newTemplatesBlock = rtrim($templatesBlock) . "\n            {$fqcnTemplate},";
                $updatedSection = str_replace($templatesBlock, $newTemplatesBlock, $fullSection);
                $content = str_replace($fullSection, $updatedSection, $content);
            }

            /**
             * 4. Ajouter 'default' si absent
             */
            if (!Str::contains($beforeTemplates, "'default'")) {
                $newBefore = "        'default' => {$fqcnTemplate},\n        " . trim($beforeTemplates);
                $updatedSection = str_replace($beforeTemplates, $newBefore, $updatedSection ?? $fullSection);
                $content = str_replace($fullSection, $updatedSection, $content);
            }
        }

        /**
         * 5. Écriture si modifié
         */
        if ($content !== $originalContent) {
            file_put_contents($configPath, $content);
        }
    }
}
