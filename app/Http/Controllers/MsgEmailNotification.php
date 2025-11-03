<?php

namespace App\Http\Controllers;

use Exception;
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
            // Appel au service pour traiter la notification
            $this->notificationService->processEmailNotification($notificationData);
            return response()->json([
                'status' => 'success',
                'message' => 'Email processed successfully'
            ], 200);
        } catch (Exception $e) {
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
        // Log de début avec toutes les données reçues
        \Log::info('=== WEBHOOK DRAFT NOTIFICATION RECEIVED ===');
        \Log::info('Request Headers:', $request->headers->all());
        \Log::info('Request Body (All Data):', $request->all());
        \Log::info('Request Method:', [$request->method()]);
        \Log::info('Request URL:', [$request->fullUrl()]);

        // Validation d'abonnement
        if ($request->has('validationToken')) {
            return response($request->input('validationToken'))
                ->header('Content-Type', 'text/plain');
        }
        // Traitement des notifications pour les brouillons
        $notificationData = $request->all();
        \Log::info('Notification Data Extracted:', $notificationData);
        //
        if (isset($notificationData['value'][0]['resourceData']['id'])) {
            $messageId = $notificationData['value'][0]['resourceData']['id'];
            $existingEmail = MsgEmailDraft::where('email_id', $messageId)->whereNotIn('status', ['end', 'error'])->first();
            if ($existingEmail) {
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
            //\Log::info('MsgEmailNotification handleDraft (avant response) FIN-----------------');
            return response()->json([
                'status' => 'success',
                'message' => 'Draft processed successfully'
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to process draft: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process draft'
            ], 500);
        }
    }
}