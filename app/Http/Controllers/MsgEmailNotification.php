<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Facades\MsGraph\MsgConnect;
use Illuminate\Support\Facades\Log;

class MsgEmailNotification extends Controller
{
    /**
     * Gestion des notifications pour les emails entrants
     */
    public function handleIncoming(Request $request)
    {
        // Validation d'abonnement
        if ($request->has('validationToken')) {
            return response($request->input('validationToken'))->header('Content-Type', 'text/plain');
        }

        // Traitement des notifications d'emails entrants
        $notificationData = $request->all();
        try {
            Log::info('Processing incoming email notification', $notificationData);
            MsgConnect::processEmailNotification($notificationData);
            return response()->json(['status' => 'success', 'message' => 'Email processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Failed to process incoming email: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to process incoming email'], 500);
        }
    }

    /**
     * Gestion des notifications pour les brouillons
     */
    public function handleDraft(Request $request)
    {
        Log::info('handleDraft');
        // Validation d'abonnement
        if ($request->has('validationToken')) {
            return response($request->input('validationToken'))->header('Content-Type', 'text/plain');
        }

        // Traitement des notifications pour les brouillons
        $notificationData = $request->all();
        try {
            Log::info('Processing draft notification', $notificationData);
            MsgConnect::processDraftNotification($notificationData);
            return response()->json(['status' => 'success', 'message' => 'Draft processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Failed to process draft: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to process draft'], 500);
        }
    }
}
