<?php

namespace App\Filament\Clusters\Crm\Resources\SectorResource\Widgets;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms;
use App\Models\Sector;
use Filament\Actions\CreateAction;
use App\Components\Tree\Widgets\Tree;

//


class SectorWidget extends Tree
{
    protected static string $model = Sector::class;

    protected ?string $treeTitle = 'SectorWidget';

    protected bool $enableTreeTitle = true;

    protected static int $maxDepth = 50;

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->required()
                ->maxLength(255),
            Textarea::make('contenu')
                ->columnSpanFull(),
            Textarea::make('txt_intro')
                ->columnSpanFull(),
            Textarea::make('txt_kpi')
                ->columnSpanFull(),
        ];
    }

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function hasDeleteAction(): bool
    {
        return false;
    }

    protected function hasEditAction(): bool
    {
        return true;
    }

    protected function hasViewAction(): bool
    {
        return true;
    }
}
