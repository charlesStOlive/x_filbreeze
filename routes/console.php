<?php

use App\Models\MsgUserIn;
use App\Models\MsgUserDraft;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();



Schedule::call(function () {
    $msgUsers = MsgUserIn::where('subscription_id', '<>', null)->get();
    foreach ($msgUsers as $msgUser) {
        $msgUser->refreshSubscription();
    }
    $msgUsers = MsgUserDraft::where('subscription_id', '<>', null)->get();
    foreach ($msgUsers as $msgUser) {
        $msgUser->refreshSubscription();
    }
})->dailyAt('18:40')->timezone('Europe/Paris');

// Ouvre automatiquement les déclarations TVA/URSSAF dont la période a commencé,
// au lieu de compter sur une création manuelle mois par mois.
Schedule::command('declarations:create-due')->dailyAt('00:10')->timezone('Europe/Paris');

// Rafraîchit les données Qonto locales avant le recalcul du matin.
Schedule::command('qonto:sync')->dailyAt('07:45')->timezone('Europe/Paris');

// Recalcule les déclarations en cours et détecte les paiements Qonto des
// déclarations déjà déclarées (le passage à "Déclarée" reste manuel).
Schedule::command('declarations:refresh')->dailyAt('08:00')->timezone('Europe/Paris');
