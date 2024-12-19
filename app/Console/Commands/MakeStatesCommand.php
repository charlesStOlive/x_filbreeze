<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeStatesCommand extends Command
{
    protected $signature = 'make:states {model}';
    protected $description = 'Générer des états et transitions pour un modèle avec laravel-model-states';

    private Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $model = Str::studly($this->argument('model'));
        $modelLowercase = Str::camel($this->argument('model'));
        $states = [];

        // Création des états
        while (true) {
            $state = $this->ask('Ajouter un état (laisser vide pour terminer)');

            if (empty($state)) {
                break;
            }

            $state = Str::studly($state);
            $states[] = $state;
            $this->createStateFile($model, $modelLowercase, $state);
            $this->info("État '$state' créé avec succès.");
        }

        // Création des transitions
        $transitions = [];
        if (!empty($states)) {
            $this->info('Configuration des transitions possibles :');
            $combinations = [];

            foreach ($states as $from) {
                foreach ($states as $to) {
                    if ($from !== $to) {
                        $combinations[] = "$from -> $to";
                    }
                }
            }

            foreach ($states as $to) {
                $combinations[] = "To $to";
            }

            foreach ($combinations as $index => $combination) {
                $this->line("$index: $combination");
            }

            while (true) {
                $choices = $this->ask('Choisir des transitions (index séparés par des virgules, ou "non" pour terminer)', 'non');

                if (strtolower($choices) === 'non') {
                    break;
                }

                $indexes = array_map('trim', explode(',', $choices));

                foreach ($indexes as $choice) {
                    if (!isset($combinations[$choice])) {
                        $this->error("Choix invalide : $choice");
                        continue;
                    }

                    $combination = $combinations[$choice];

                    if (str_starts_with($combination, 'To ')) {
                        $to = str_replace('To ', '', $combination);
                        $transitions[] = [null, $to];
                        $this->createToTransitionFile($model, $modelLowercase, $to);
                        $this->info("Transition 'To $to' créée avec succès.");
                    } else {
                        [$from, $to] = explode(' -> ', $combination);
                        $transitions[] = [$from, $to];
                        $this->createFromToTransitionFile($model, $modelLowercase, $from, $to);
                        $this->info("Transition '$from -> $to' créée avec succès.");
                    }
                }
            }
        }

        // Création du fichier InvoiceState
        $this->createStateClass($model, $modelLowercase, $states, $transitions);
        $this->info("Fichier {$model}State créé avec succès.");
    }

    private function createStateFile(string $model, string $modelLowercase, string $state)
    {
        $stub = $this->files->get(base_path('stubs/state.stub'));
        $stateContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ state }}',
            '{{ state_lowercase }}'
        ], [
            $model,
            $modelLowercase,
            $state,
            strtolower($state)
        ], $stub);

        $path = app_path("Models/States/{$model}/{$state}.php");
        $this->makeDirectory(dirname($path));

        $this->files->put($path, $stateContent);
    }

    private function createToTransitionFile(string $model, string $modelLowercase, string $to)
    {
        $stub = $this->files->get(base_path('stubs/to_transition.stub'));
        $transitionContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ to }}'
        ], [
            $model,
            $modelLowercase,
            $to
        ], $stub);

        $path = app_path("Models/States/{$model}/To{$to}.php");
        $this->makeDirectory(dirname($path));

        $this->files->put($path, $transitionContent);
    }

    private function createFromToTransitionFile(string $model, string $modelLowercase, string $from, string $to)
    {
        $stub = $this->files->get(base_path('stubs/from_to_transition.stub'));
        $transitionContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ from }}',
            '{{ to }}'
        ], [
            $model,
            $modelLowercase,
            $from,
            $to
        ], $stub);

        $path = app_path("Models/States/{$model}/Transitions/From{$from}To{$to}.php");
        $this->makeDirectory(dirname($path));

        $this->files->put($path, $transitionContent);
    }

    private function createStateClass(string $model, string $modelLowercase, array $states, array $transitions)
    {
        $stub = $this->files->get(base_path('stubs/state_class.stub'));

        $statesList = implode(",\n        ", array_map(fn($state) => "{$model}\\$state::class", $states));
        $firstState = $states[0] ?? 'Draft';

        $transitionComments = [];
        foreach ($transitions as $transition) {
            if ($transition[0] === null) {
                $transitionComments[] = "//->allowTransition(To{$transition[1]}::class)";
            } else {
                $transitionComments[] = "//->allowTransition({{$transition[0]}::class, {$model}\\To{$transition[1]}::class)";
            }
        }
        $transitionsBlock = implode("\n            ", $transitionComments);

        $stateClassContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ states }}',
            '{{ first_state_created }}',
            '{{ transitions }}'
        ], [
            $model,
            $modelLowercase,
            $statesList,
            $firstState,
            $transitionsBlock
        ], $stub);

        $path = app_path("Models/States/{$model}/{$model}State.php");
        $this->makeDirectory(dirname($path));

        $this->files->put($path, $stateClassContent);
    }

    private function makeDirectory(string $path)
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
