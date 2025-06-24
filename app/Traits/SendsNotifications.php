<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

trait SendsNotifications
{
    public function notifyError(string $title, string $message, $mode = 'both'): void
    {
        $user = Auth::user() ?? User::getSystemUser();

        if ($mode == 'live' || $mode == 'both') {
            Notification::make()
                ->title($title)
                ->body($message)
                ->danger()
                ->send();
        }


        if ($mode == 'database' || $mode == 'both') {
            Notification::make()
                ->title($title)
                ->body($message)
                ->danger()
                ->actions([
                    Action::make('vue')
                        ->button()
                        ->markAsRead()
                        ->close()
                ])
                ->sendToDatabase($user);
        }
    }

    public function notifySuccess(string $title, string $message, $mode = 'both'): void
    {
        $user = Auth::user() ?? User::getSystemUser();

        if ($mode == 'live' || $mode == 'both') {
            Notification::make()
                ->title($title)
                ->body($message)
                ->success()
                ->send();
        }

        if ($mode == 'database' || $mode == 'both') {
            Notification::make()
                ->title($title)
                ->body($message)
                ->success()
                ->actions([
                    Action::make('vue')
                        ->button()
                        ->markAsRead()
                        ->close()
                ])
                ->sendToDatabase($user);
        }
    }

    // Tu peux aussi ajouter notifyInfo(), notifyWarning() si besoin
}
