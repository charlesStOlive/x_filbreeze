<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeEDraftEmailTemplate extends Command
{
    protected $signature = 'make:template-draft-email {model} {name} {--register}';
    protected $description = 'Crée un template Email Draft Laravel/Filament dynamique pour un modèle donné';

    public function handle()
    {
        $model = Str::studly($this->argument('model'));

        if (!class_exists("App\\Models\\{$model}")) {
            $this->error("❌ Le modèle App\\Models\\{$model} n'existe pas.");
            return Command::FAILURE;
        }

        $name = Str::lower($this->argument('name'));
        $baseStudly = Str::studly($name);
        $baseSnake = Str::snake($name);
        $baseKebab = Str::kebab($name);

        $var = Str::camel($model);
        $kebab = Str::kebab($model);
        $className = "{$model}{$baseStudly}Template";
        $viewPath = "emails.drafts.{$kebab}.{$baseKebab}";

        $templatePath = app_path("Services/MsGraph/EmailDraft/Templates/{$model}/{$className}.php");
        $viewFilePath = resource_path("views/emails/drafts/{$kebab}/{$baseKebab}.blade.php");

        $this->makeDirectory(dirname($templatePath));
        $this->makeDirectory(dirname($viewFilePath));

        $implementsHasPj = $this->confirm('Souhaitez-vous que ce template Email ait des Pièces Jointes (implements HasPj) ?');

        $this->generateClass($templatePath, [
            'model' => $model,
            'var' => $var,
            'view' => $viewPath,
            'key' => "{$kebab}_{$baseSnake}",
            'label' => "{$model} {$baseStudly}",
            'className' => $className,
            'hasPj' => $implementsHasPj,
        ]);

        $this->generateView($viewFilePath, ['var' => $var]);

        $this->info("✅ Classe créée : {$templatePath}");
        $this->info("✅ Vue créée    : {$viewFilePath}");

        if ($this->option('register')) {
            $this->registerInConfig($model, $className);
            $this->info("🔧 Enregistré dans config/templates-email-draft.php");
            $this->callSilent('config:clear');
            $this->info("♻️  Cache config vidé (config:clear)");
        }
    }

    protected function generateClass(string $path, array $replacements)
    {
        $stubFile = $replacements['hasPj']
            ? '/stubs/templates/draft-email/template_with_pj.stub'
            : '/stubs/templates/draft-email/template.stub';

        $stub = file_get_contents(__DIR__ . $stubFile);

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ ' . $key . ' }}', $value, $stub);
        }

        file_put_contents($path, $stub);
    }

    protected function generateView(string $path, array $replacements)
    {
        $stub = file_get_contents(__DIR__ . '/stubs/templates/draft-email/view.stub');

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

    protected function getHasPjBlock(): string
    {
        return "\n    public static function getAvailableAttachments(): array\n    {\n        return [\n            // App\\Services\\Pdf\\Templates\\Invoice\\InvoicePdfTemplate::class => true,\n        ];\n    }\n";
    }

    protected function registerInConfig(string $model, string $className)
    {
        $configPath = config_path('templates-email-draft.php');
        $originalContent = file_get_contents($configPath);
        $content = $originalContent;

        $fqcnModel = "\\App\\Models\\{$model}::class";
        $fqcnTemplate = "\\App\\Services\\MsGraph\\EmailDraft\\Templates\\{$model}\\{$className}::class";
        $modelKey = Str::kebab($model);

        $typesPattern = "/'types'\s*=>\s*\[([\s\S]*?)\],/";
        if (preg_match($typesPattern, $content, $matches)) {
            $typesBlock = $matches[1];
            if (!Str::contains($typesBlock, "{$fqcnModel} =>")) {
                $newTypesBlock = rtrim($typesBlock) . "\n        {$fqcnModel} => '{$modelKey}',";
                $content = str_replace($typesBlock, $newTypesBlock, $content);
            }
        }

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

            if (!Str::contains($beforeTemplates, "'default'")) {
                $newBefore = "        'default' => {$fqcnTemplate},\n        " . trim($beforeTemplates);
                $updatedSection = str_replace($beforeTemplates, $newBefore, $updatedSection ?? $fullSection);
                $content = str_replace($fullSection, $updatedSection, $content);
            }
        }

        if ($content !== $originalContent) {
            file_put_contents($configPath, $content);
        }
    }
}
