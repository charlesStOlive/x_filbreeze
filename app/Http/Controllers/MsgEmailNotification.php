<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Facades\MsGraph\MsgConnect;
use Illuminate\Support\Facades\Http;
use App\Jobs\ProcessEmailNotification;

class MsgEmailNotification extends Controller
{
    public function handle(Request $request)
    {
        // \Log::info('Request data:');
        // \Log::info($request->all());
        // \Log::info('Request has validation data:');
        // \Log::info($request->has('validationToken'));

        // Check if the request contains a validation token dans ce cas c est une validation d'abonemment
        if ($request->has('validationToken')) {
            //\Log::info('Validation token received:');
            //\Log::info($request->input('validationToken'));
            // Respond with the validation token as plain text
            return response($request->input('validationToken'))->header('Content-Type', 'text/plain');
        }

        $notificationData = $request->all();
        // \Log::error('notificationData');
        // \Log::error($notificationData);
        // Traitement de la notification
        try {
            //Lancement analyse
            \Log::info('Lancement analyse ----------------------------------------------');
            MsgConnect::processEmailNotification($notificationData);
            // \Log::info('Lancement analyse JOB ----------------------------------------------');
            // ProcessEmailNotification::dispatch($notificationData);
            return response()->json(['status' => 'success', 'message' => 'Email processed successfully'], 200);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to process email: ' . $e->getMessage()], 500);
        }
    }

}
