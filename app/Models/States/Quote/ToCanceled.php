<?php

namespace App\Models\States\Quote;

use Closure;
use Filament\Forms;
use App\Models\Quote;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;
use Filament\Support\Contracts\HasIcon;
use App\Filament\Contracts\HasRedirection;
use App\Filament\Clusters\Crm\Resources\QuoteResource;

class ToCanceled extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon, HasRedirection
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private Quote $quote,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Abandonner');
    }
 
    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-x-mark';
    }

    public function handle(): Quote
    {
        $this->quote->state = new Canceled($this->quote);
        $this->quote->validated_at = null;
        $this->quote->save();
        return $this->quote;
    }

    public function getRedirectUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        \Log::info('ToCanceled: getRedirectUrl appelée', [
            'record_id' => $record->id,
            'record_type' => get_class($record)
        ]);
        
        // Redirige vers l'index des devis après annulation
        $url = QuoteResource::getUrl('index');
        \Log::info('ToCanceled: URL générée', ['url' => $url]);
        
        return $url;
    }

}