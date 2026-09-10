<?php

namespace App\Filament\Resources\DeclarationResource\Pages;

use App\Filament\Resources\DeclarationResource;
use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Wizard\Step;

class ListDeclarations extends ListRecords
{
    protected static string $resource = DeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouvelle déclaration')
                ->modalHeading('Nouvelle déclaration')
                ->steps([
                    Step::make('type')
                        ->label('Type')
                        ->description('Quelle déclaration ?')
                        ->schema([
                            Radio::make('type')
                                ->hiddenLabel()
                                ->options(Declaration::typeOptions())
                                ->default(Declaration::TYPE_VAT)
                                ->required()
                                ->live(),
                        ]),

                    Step::make('frequency')
                        ->label('Fréquence')
                        ->description('Mensuelle ou trimestrielle ?')
                        ->schema([
                            Radio::make('period_frequency')
                                ->hiddenLabel()
                                ->options(Declaration::periodOptions())
                                ->default(fn (callable $get): string => Declaration::defaultPeriodFor($get('type') ?? Declaration::TYPE_VAT))
                                ->required()
                                ->live(),
                        ]),

                    Step::make('period')
                        ->label('Période')
                        ->description('Quel mois ou quel trimestre ?')
                        ->schema([
                            Select::make('target_period_start')
                                ->label('Période à générer')
                                ->native(false)
                                ->required()
                                ->options(fn (callable $get): array => Declaration::periodChoices(
                                    $get('type') ?? Declaration::TYPE_VAT,
                                    $get('period_frequency') ?? Declaration::PERIOD_MONTHLY,
                                )),
                        ]),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $calculated = app(DeclarationCalculator::class)->calculate(
                        $data['type'],
                        $data['target_period_start'],
                        $data['period_frequency'],
                    );

                    return array_merge($calculated, [
                        'calculation_mode' => Declaration::MODE_AUTOMATIC,
                        'created_by' => auth()->id(),
                    ]);
                }),
        ];
    }
}
