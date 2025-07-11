<?php 

namespace App\Services\MsGraph\EmailDraft;

use Illuminate\Support\Facades\View;
use App\Services\MsGraph\EmailDraft\Templates\Contracts\EmailDraftTemplate;

class EmailDraftRenderer
{
    public function render(EmailDraftTemplate $template, array $options = []): array
{
    return [
        'subject' => $template->getSubject(),
        'body' => View::make($template->getView(), $template->getData($options))->render(),
    ];
}
}