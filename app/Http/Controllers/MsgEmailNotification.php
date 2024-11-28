<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\MsGraph\MsGraphNotificationService;
use App\Models\MsgEmailDraft;
use App\Models\MsgEmailIn;

class MsgEmailNotification extends Controller
{
    protected MsGraphNotificationService $notificationService;

    public function __construct(MsGraphNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Gestion des notifications pour les emails entrants
     */
    public function handleIncoming(Request $request)
    {
        // Validation d'abonnement
        if ($request->has('validationToken')) {
            return response($request->input('validationToken'))
                ->header('Content-Type', 'text/plain');
        }

        // Traitement des notifications d'emails entrants
        $notificationData = $request->all();
        try {
            Log::info('Processing incoming email notification', $notificationData);

            // Appel au service pour traiter la notification
            $this->notificationService->processEmailNotification($notificationData);

            return response()->json([
                'status' => 'success',
                'message' => 'Email processed successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to process incoming email: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process incoming email'
            ], 500);
        }
    }

    /**
     * Gestion des notifications pour les brouillons
     */
    public function handleDraft(Request $request)
    {
        \Log::info('MsgEmailNotification handleDraft start-----------------');
        // Validation d'abonnement
        if ($request->has('validationToken')) {
            return response($request->input('validationToken'))
                ->header('Content-Type', 'text/plain');
        }
        // Traitement des notifications pour les brouillons
        $notificationData = $request->all();
        //
        if (isset($notificationData['value'][0]['resourceData']['id'])) {
            $messageId = $notificationData['value'][0]['resourceData']['id'];
            $existingEmail = MsgEmailDraft::where('email_id', $messageId)->whereNotIn('status', ['end', 'error'])->first();
            if ($existingEmail) {
                \Log::info('le mail existe déjà');
                return response()->json([
                    'status' => 'success',
                    'message' => 'Draft processed successfully'
                ], 200);
            }
        } else {
            \Log::warning('Aucun messageId trouvé dans la notification.');
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid notification data'
            ], 400);
        }
        //
        try {
            // Appel au service pour traiter la notification
            $this->notificationService->processDraftNotification($notificationData);
            \Log::info('MsgEmailNotification handleDraft (avant response) FIN-----------------');
            return response()->json([
                'status' => 'success',
                'message' => 'Draft processed successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to process draft: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process draft'
            ], 500);
        }
    }
}
