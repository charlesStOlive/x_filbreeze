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

            $stateName = Str::studly($state);
            $stateLabel = $this->ask("Label pour l'état '$stateName'", $state);
            $states[] = ['name' => $stateName, 'label' => $stateLabel];
            $this->createStateFile($model, $modelLowercase, $stateName, $stateLabel);
            $this->info("État '$stateName' créé avec succès.");
        }

        // Création des transitions
        $transitions = [];
        if (!empty($states)) {
            $this->info('Configuration des transitions possibles :');
            $combinations = [];

            foreach ($states as $from) {
                foreach ($states as $to) {
                    if ($from['name'] !== $to['name']) {
                        $combinations[] = $from['name'] . " -> " . $to['name'];
                    }
                }
            }

            foreach ($states as $to) {
                $combinations[] = "To " . $to['name'];
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
                        $toStateName = str_replace('To ', '', $combination);
                        $transitionLabel = $this->ask("Label pour la transition 'To $toStateName'", "Passer à " . strtolower($toStateName));
                        $transitions[] = [null, $toStateName, $transitionLabel];
                        $this->createToTransitionFile($model, $modelLowercase, $toStateName, $transitionLabel);
                        $this->info("Transition 'To $toStateName' créée avec succès.");
                    } else {
                        [$from, $to] = explode(' -> ', $combination);
                        $transitionLabel = $this->ask("Label pour la transition '$from -> $to'", "Passer de $from à $to");
                        $transitions[] = [$from, $to, $transitionLabel];
                        $this->createFromToTransitionFile($model, $modelLowercase, $from, $to, $transitionLabel);
                        $this->info("Transition '$from -> $to' créée avec succès.");
                    }
                }
            }
        }

        // Création du fichier InvoiceState
        $this->createStateClass($model, $modelLowercase, $states, $transitions);
        $this->info("Fichier {$model}State créé avec succès.");
    }

    private function createStateFile(string $model, string $modelLowercase, string $state, string $stateLabel)
    {
        $stub = $this->files->get(base_path('stubs/state.stub'));
        $stateContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ state }}',
            '{{ state_lowercase }}',
            '{{ state_label }}'
        ], [
            $model,
            $modelLowercase,
            $state,
            strtolower($state),
            $stateLabel
        ], $stub);

        $path = app_path("Models/States/{$model}/{$state}.php");
        $this->makeDirectory(dirname($path));

        $this->files->put($path, $stateContent);
    }

    private function createToTransitionFile(string $model, string $modelLowercase, string $to, string $transitionLabel)
    {
        $stub = $this->files->get(base_path('stubs/to_transition.stub'));
        $transitionContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ to }}',
            '{{ transition_label }}'
        ], [
            $model,
            $modelLowercase,
            $to,
            $transitionLabel
        ], $stub);

        $path = app_path("Models/States/{$model}/To{$to}.php");
        $this->makeDirectory(dirname($path));

        $this->files->put($path, $transitionContent);
    }

    private function createFromToTransitionFile(string $model, string $modelLowercase, string $from, string $to, string $transitionLabel)
    {
        $stub = $this->files->get(base_path('stubs/from_to_transition.stub'));
        $transitionContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ from }}',
            '{{ to }}',
            '{{ transition_label }}'
        ], [
            $model,
            $modelLowercase,
            $from,
            $to,
            $transitionLabel
        ], $stub);

        $path = app_path("Models/States/{$model}/{$from}To{$to}.php");
        $this->makeDirectory(dirname($path));

        $this->files->put($path, $transitionContent);
    }

    private function createStateClass(string $model, string $modelLowercase, array $states, array $transitions)
    {
        $stub = $this->files->get(base_path('stubs/state_class.stub'));

        $firstState = $states[0]['name'] ?? 'Draft';

        $transitionComments = [];
        foreach ($transitions as $transition) {
            if ($transition[0] === null) {
                // To transition
                $transitionComments[] = "->allowTransition({$transition[1]}::class, To{$transition[1]}::class)";
            } else {
                // From -> To transition  
                $transitionComments[] = "->allowTransition({$transition[0]}::class, {$transition[1]}::class, {$transition[0]}To{$transition[1]}::class)";
            }
        }
        $transitionsBlock = implode("\n            ", $transitionComments);

        $stateClassContent = str_replace([
            '{{ model }}',
            '{{ model_lowercase }}',
            '{{ first_state_created }}',
            '{{ transitions }}'
        ], [
            $model,
            $modelLowercase,
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
