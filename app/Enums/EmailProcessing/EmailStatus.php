<?php
// app/Enums/EmailProcessing/EmailStatus.php
namespace App\Enums\EmailProcessing;

enum EmailStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case Partial = 'partial';
    case Error = 'error';
    case End = 'end';
}
