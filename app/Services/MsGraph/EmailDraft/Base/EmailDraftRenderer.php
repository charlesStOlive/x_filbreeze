<?php 

namespace App\Services\MsGraph\EmailDraft\Base;

use Illuminate\Support\Facades\View;
use App\Services\MsGraph\EmailDraft\Base\BaseDraftEmailTemplate;


class EmailDraftRenderer
{
    public function render(BaseDraftEmailTemplate $template, array $options = []): array
    {
        return [
            'subject' => $template->getSubject($options),
            'body' => View::make($template->getView(), $template->getData($options))->render(),
        ];
    }
}
