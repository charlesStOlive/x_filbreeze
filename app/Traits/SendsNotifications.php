<?php

namespace App\Traits;

use Filament\Actions\Action;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

trait SendsNotifications
{
    public function notifyError(string $title, string $message, $mode = 'both'): void
    {
        $user = $this->resolveNotificationRecipient();

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
        $user = $this->resolveNotificationRecipient();

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

    private function resolveNotificationRecipient(): ?User
    {
        if (Auth::check()) {
            return Auth::user();
        }

        $email = config('notifications.system_user_email');

        return $email ? User::where('email', $email)->first() : null;
    }
}
