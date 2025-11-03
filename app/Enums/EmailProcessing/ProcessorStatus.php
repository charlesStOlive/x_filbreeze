<?php
// app/Enums/EmailProcessing/ProcessorStatus.php
namespace App\Enums\EmailProcessing;

enum ProcessorStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Processing = 'processing';
    case Success = 'success';
    case Blocked = 'blocked';
    case Error = 'error';
}