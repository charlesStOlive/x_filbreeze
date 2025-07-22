<?php

namespace App\Services\Pdf\Base;



use Spatie\Browsershot\Browsershot;
use App\Services\Helpers\ViteHelper;
use Illuminate\Support\Facades\View;
use App\Services\Pdf\Base\PdfRenderer;
use App\Services\Pdf\Base\PdfTemplate;
use Illuminate\Support\Facades\Storage;

use App\Services\Pdf\Base\PdfTemplateRegistry;
use App\Services\Document\Dto\GeneratedDocumentDTO;
use App\Services\Document\Contracts\DocumentProducer;
use App\Services\Document\Concerns\InteractsWithDocumentProducer;

abstract class BasePdfTemplate implements DocumentProducer
{
    use InteractsWithDocumentProducer;

    abstract public function getView(): string;

    abstract public function getData(array $options = []): array;

    abstract public function getFileName(array $options = []): string;

    public static function key(): string
    {
        return 'pdf_' . str(class_basename(static::class))->kebab();
    }

    public static function label(): string
    {
        return 'PDF - ' . str(class_basename(static::class))->headline();
    }

    public function createDocument(array $options = []): string
    {
        $html = app(PdfRenderer::class)->render($this, $options, false);

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');

        Browsershot::html($html)
            ->format('A4')
            ->scale(0.75)
            ->margins(25, 25, 25, 25, 'px')
            ->emulateMedia('screen')
            ->showBackground()
            ->savePdf($tempPath);

        return $tempPath;
    }



    public function generateFile(array $options = []): GeneratedDocumentDTO
    {
        $fileName = $this->getFileName($options) . '.pdf';
        $relativePath = static::getExportDirectory() . '/' . $fileName;
        $publicPath = \Storage::disk('public')->path($relativePath);

        $tempPath = $this->createDocument($options);

        \Storage::disk('public')->put($relativePath, file_get_contents($tempPath));
        @unlink($tempPath);

        return new GeneratedDocumentDTO(
            path: $publicPath,
            name: $fileName,
            mime: 'application/pdf',
        );
    }

    public function download(array $options = [])
    {
        $generated = $this->generateFile($options);

        return response()->download($generated->path, $generated->name)
            ->deleteFileAfterSend(true);
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
