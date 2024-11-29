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
