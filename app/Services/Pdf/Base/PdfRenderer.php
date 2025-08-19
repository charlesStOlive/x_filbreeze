<?php

namespace App\Services\Pdf\Base;

use App\Services\Helpers\ViteHelper;
use Illuminate\Support\Facades\View;
use App\Services\Pdf\Base\PdfTemplate;


class PdfRenderer
{
    public function render(BasePdfTemplate $template, array $options = [], bool $hotReload = false): string
    {
        \Log::info('Rendering PDF with template: ' . get_class($template) . 'and preview ' . $hotReload);
        return View::make($template->getView(), array_merge(
            $template->getData($options),
            [
                'hotReload' => $hotReload,
                'cssPath' => ViteHelper::viteAsset('resources/css/pdf/theme.css'),
            ]
        ))->render();
    }

    public function renderPartial(string $view, array $data = []): string
    {
        return view($view, array_merge(
            $data,
            ['cssPath' => ViteHelper::viteAsset('resources/css/pdf/theme.css')],
        ))->render();
    }
}
