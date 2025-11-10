<?php

// app/Jobs/ProcessEmailNotification.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use CharlesStOlive\MsGraphFilament\Services\Email\EmailNotificationService;

class ProcessEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notificationData;

    public function __construct($notificationData)
    {
        $this->notificationData = $notificationData;
    }

    public function handle(EmailNotificationService $notificationService)
    {
        $notificationService->processEmailNotification($this->notificationData);
    }
}
