<?php

namespace App\Services\Pdf\Base;

use App\Services\Pdf\Base\PdfRenderer;
use App\Services\Helpers\ViteHelper;
use Illuminate\Support\Facades\Storage;
use App\Services\Document\Dto\GeneratedDocumentDTO;
use App\Services\Document\Contracts\DocumentProducer;
use App\Services\Document\Concerns\InteractsWithDocumentProducer;

use Spatie\Browsershot\Browsershot;

abstract class BasePdfTemplate implements DocumentProducer
{
    use InteractsWithDocumentProducer;

    protected ?array $options = null;

    public function __construct(protected mixed $record, ?array $options = null)
    {
        // Stocke les options fusionnées avec les valeurs par défaut
        $this->options = $options !== null
            ? array_merge(static::getDefaultOptions(), $options)
            : null;
    }

    abstract public function getView(): string;

    abstract public function getFileName(array $options = []): string;

    public function getData(array $options = []): array
    {
        $mergedOptions = array_merge(
            static::getDefaultOptions(),
            $this->options ?? [],
            $options
        );

        return [
            'options' => $mergedOptions,
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [];
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options ?? []);
    }

    public function createDocument(array $options = []): string
    {
        $mergedOptions = array_merge(
            static::getDefaultOptions(),
            $this->options ?? [],
            $options
        );

        $html = app(PdfRenderer::class)->render($this, $mergedOptions, false);
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
        $mergedOptions = array_merge(
            static::getDefaultOptions(),
            $this->options ?? [],
            $options
        );

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

    public function download(array $options = [])
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
        $html = app(PdfRenderer::class)->render($template, $options);

        return ['html' => $html];
    }

    public static function key(): string
    {
        return 'pdf_' . str(class_basename(static::class))->beforeLast('PdfTemplate')->kebab();
    }

    public static function label(): string
    {
        return 'PDF – ' . str(class_basename(static::class))->headline();
    }
}
