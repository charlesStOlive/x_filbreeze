<?php

namespace App\Filament\Clusters\Administration\UserResource\Tables;

use CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin;
use CharlesStOlive\FilamentPermissionManager\Services\ApiTokenService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Rôles')
                    ->badge()
                    ->color(fn(string $state): string => SuperAdmin::isRole($state) ? 'danger' : 'primary')
                    ->separator(','),
                IconColumn::make('app_authentication_secret')
                    ->label('MFA App')
                    ->boolean()
                    ->getStateUsing(fn($record) => ! empty($record->app_authentication_secret))
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rôles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('resetMfa')
                    ->label('Réinitialiser MFA')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn($record) => ! empty($record->app_authentication_secret) || $record->has_email_authentication)
                    ->action(function ($record) {
                        $record->forceFill([
                            'app_authentication_secret'          => null,
                            'app_authentication_recovery_codes'  => null,
                            'has_email_authentication'           => false,
                        ])->save();
                    }),
                // Propre à filbreeze : une clé API pour cet utilisateur, réservée à qui peut le modifier.
                Action::make('generateApiToken')
                    ->label('Générer clé API')
                    ->icon('heroicon-o-key')
                    ->visible(fn($record) => \App\Filament\Clusters\Administration\UserResource::canEdit($record))
                    ->action(function ($record) {
                        app(ApiTokenService::class)->createToken($record, 'managed-from-user-resource', ['*']);
                    }),
                EditAction::make()
                    ->visible(fn($record) => \App\Filament\Clusters\Administration\UserResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn($record) => \App\Filament\Clusters\Administration\UserResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            $records
                                ->filter(function ($record) {
                                    if (SuperAdmin::is($record)) {
                                        return SuperAdmin::is(auth()->user());
                                    }

                                    return true;
                                })
                                ->each->delete();
                        }),
                ]),
            ]);
    }
}
