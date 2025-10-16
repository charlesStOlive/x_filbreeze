<?php

namespace App\Services\Pdf\Base;

use Log;
use App\Services\Helpers\ViteHelper;
use Illuminate\Support\Facades\View;
use App\Services\Pdf\Base\PdfTemplate;


class PdfRenderer
{
    public function render(BasePdfTemplate $template, array $options = [], bool $hotReload = false): string
    {
        Log::info('Rendering PDF with template: ' . get_class($template));
        
        return View::make($template->getView(), array_merge(
            $template->getData($options),
            [
                'cssPath' => $hotReload 
                    ? ViteHelper::viteAsset('resources/css/pdf/pdf.css', false) // Mode preview avec hot reload si disponible
                    : ViteHelper::getCompiledCssPath('resources/css/pdf/pdf.css'), // Mode production pour Browsershot
            ]
        ))->render();
    }

    public function renderPartial(string $view, array $data = []): string
    {
        return view($view, array_merge(
            $data,
            ['cssPath' => ViteHelper::getCompiledCssPath('resources/css/pdf/pdf.css')], // Toujours en mode production pour les headers/footers
        ))->render();
    }
}
