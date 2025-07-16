<?php

namespace App\Services\Pdf\Templates\Base;

use App\Services\Pdf\PdfRenderer;
use Spatie\Browsershot\Browsershot;
use App\Services\Helpers\ViteHelper;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use App\Services\Pdf\PdfTemplateRegistry;
use App\Services\Pdf\Templates\Contracts\PdfTemplate;

abstract class BasePdfTemplate implements PdfTemplate
{
    abstract public function getView(): string;

    abstract public function getData(array $options = []): array;

    abstract public function getFileName(array $options = []): string;

    public function saveTo(string $path, array $options = []): string
    {
        $html = app(PdfRenderer::class)->render($this, $options, false);

        Browsershot::html($html)
            ->format('A4')
            ->scale(0.75)
            ->margins(25, 25, 25, 25, 'px')
            ->emulateMedia('screen')
            ->showBackground()
            ->savePdf($path);

        return $path;
    }

    public function download(array $options = [])
    {
        $fileName = $this->getFileName($options) . '.pdf';
        $path = storage_path('app/public/' . $fileName);

        $this->saveTo($path, $options);

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function generateFile(array $options = []): array
    {
        $fileName = $this->getFileName($options) . '.pdf';
        $path = storage_path('app/public/' . $fileName);

        $this->saveTo($path, $options);

        return [
            'name' => $fileName,
            'path' => $path,
            'mime' => 'application/pdf',
        ];
    }

    public static function getPreviewData(callable $get, $record): array
    {
        $templateKey = $get('template');
        $options = $get('template_options') ?? [];

        $templateClass = collect(PdfTemplateRegistry::getTemplatesFor(
            PdfTemplateRegistry::resolveModelTypeFromRecord($record)
        ))->first(fn($cls) => $cls::key() === $templateKey);

        if (! $templateClass) {
            return ['html' => '<p>Template introuvable</p>'];
        }

        $template = new $templateClass($record);
        $html = app(PdfRenderer::class)->render($template, $options);

        return ['html' => $html];
    }
}
