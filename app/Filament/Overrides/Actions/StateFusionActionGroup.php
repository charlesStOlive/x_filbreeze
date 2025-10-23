<?php

namespace App\Filament\Overrides\Actions;

use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\HasName;
use Illuminate\Support\Str;
use App\Filament\Overrides\Actions\StateFusionAction;

class StateFusionActionGroup extends ActionGroup
{
    use HasName;

    public $stateClass = null;

    public function __construct(?string $name, ?string $stateClass = null)
    {
        $this->name($name);
        $this->stateClass($stateClass);
        // Ne génère pas les actions ici, mais dans setUp()
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Génère les actions seulement quand la configuration est terminée
        if ($this->stateClass) {
            $this->actions($this->generateStateTransitionActions($this->stateClass, $this->getName()));
        }
    }

    public static function generate(string $columnName, string $stateClass): static
    {
        $static = app(static::class, [
            'name' => $columnName,
            'stateClass' => $stateClass,
        ]);
        $static->configure();

        return $static;
    }

    protected function generateStateTransitionActions(string $stateClass, string $name): array
    {
        // Get all state classes
        $stateClasses = $stateClass::all();

        $actions = [];

        foreach ($stateClasses as $stateClass) {
            $state = new $stateClass(null);

            $action = StateFusionAction::make(Str::slug($state::getMorphClass()))
                ->attribute($name)
                ->transitionTo($state);

            $actions[] = $action;
        }

        return $actions;
    }

    public function stateClass(string $stateClass): static
    {
        $this->stateClass = $stateClass;

        return $this;
    }

    public function getStateClass()
    {
        return $this->stateClass;
    }
}