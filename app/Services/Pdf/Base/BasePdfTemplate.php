<?php

namespace App\Services\Pdf\Base;

use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;
use App\Services\Pdf\Base\PdfRenderer;
use App\Services\Helpers\ViteHelper;
use App\Services\Document\Dto\GeneratedDocumentDTO;
use App\Services\Document\Contracts\DocumentProducer;
use App\Services\Document\Concerns\InteractsWithDocumentProducer;

abstract class BasePdfTemplate implements DocumentProducer
{
    use InteractsWithDocumentProducer;

    protected mixed $record;
    protected ?array $options = null;

    public function __construct(mixed $record, ?array $options = null)
    {
        $this->record = $record;

        $this->options = $options !== null
            ? array_merge(static::getDefaultOptions(), $options)
            : null;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    public static function getDefaultOptions(): array
    {
        return [];
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? static::getDefaultOptions()[$key] ?? $default;
    }

    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options ?? []);
    }

    public function getMergedOptions(array $runtimeOptions = []): array
    {
        return array_merge(
            static::getDefaultOptions(),
            $this->options ?? [],
            $runtimeOptions
        );
    }

    abstract public function getView(): string;

    abstract public function getFileName(array $options = []): string;

    public function getData(array $options = []): array
    {
        return [
            'options' => $this->getMergedOptions($options),
        ];
    }

    public function getHeaderView(): ?string
    {
        // return 'pdf.layouts.header'; // à créer dans resources/views/pdf/layouts/header.blade.php
        return null; // Si pas de header, retourner null
    }

    public function getFooterView(): ?string
    {
        return 'pdf.layouts.footer'; // à créer dans resources/views/pdf/layouts/footer.blade.php
    }

    public function getHeaderData(array $options = []): array
    {
        return [];
    }

    public function getFooterData(array $options = []): array
    {
        return [];
    }

    public function createDocument(array $options = []): string
    {
        $mergedOptions = $this->getMergedOptions($options);

        $html = app(PdfRenderer::class)->render($this, $mergedOptions, false);

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->scale(0.75)
            ->margins(25, 25, 50, 25, 'px')
            ->emulateMedia('screen')
            ->showBackground();

        $headerView = $this->getHeaderView();
        $footerView = $this->getFooterView();

        if ($headerView || $footerView) {
            $browsershot->showBrowserHeaderAndFooter();

            if ($headerView) {
                $headerHtml = app(PdfRenderer::class)->renderPartial($headerView, $this->getHeaderData($mergedOptions));
                $browsershot->headerHtml($headerHtml);
            }

            if ($footerView) {
                $footerHtml = app(PdfRenderer::class)->renderPartial($footerView, $this->getFooterData($mergedOptions));
                $browsershot->footerHtml($footerHtml);
            }
        }

        $browsershot->savePdf($tempPath);


        return $tempPath;
    }

    public function generateFile(array $options = []): GeneratedDocumentDTO
    {
        $mergedOptions = $this->getMergedOptions($options);

        $fileName = $this->getFileName($mergedOptions) . '.pdf';
        $relativePath = static::getExportDirectory() . '/' . $fileName;
        $publicPath = Storage::disk('public')->path($relativePath);

        $tempPath = $this->createDocument($mergedOptions);

        Storage::disk('public')->put($relativePath, file_get_contents($tempPath));
        @unlink($tempPath);

        return new GeneratedDocumentDTO(
            path: $publicPath,
            name: $fileName,
            mime: 'application/pdf',
        );
    }

    public function download(array $options = []): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $generated = $this->generateFile($options);

        return response()->download($generated->path, $generated->name)
            ->deleteFileAfterSend(true);
    }

    public static function getPreviewData(callable $get, mixed $record): array
    {
        $templateKey = $get('template');
        $options = $get('template_options') ?? [];

        $templateClass = collect(PdfTemplateRegistry::getTemplatesFor(
            PdfTemplateRegistry::resolveModelTypeFromRecord($record)
        ))->first(fn($cls) => $cls::key() === $templateKey);

        if (! $templateClass) {
            return ['html' => '<p>Template introuvable</p>'];
        }

        $template = new $templateClass($record, $options);
        $html = app(PdfRenderer::class)->render($template, $options, true); // Hot reload pour les previews

        return ['html' => $html];
    }

    public static function key(): string
    {
        return 'pdf_' . str(class_basename(static::class))->beforeLast('PdfTemplate')->kebab();
    }

    public static function label(): string
    {
        return 'PDF – ' . str(class_basename(static::class))->beforeLast('PdfTemplate')->headline();
    }
}
