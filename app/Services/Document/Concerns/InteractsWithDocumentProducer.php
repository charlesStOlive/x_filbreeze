<?php

namespace App\Services\Document\Concerns;

use App\Services\Document\Dto\GeneratedDocumentDTO;
use Illuminate\Support\Facades\Storage;

trait InteractsWithDocumentProducer
{
    public static function getExportDirectory(): string
    {
        return 'exports';
    }

    public static function getPublicPath(GeneratedDocumentDTO $generated): string
    {
        return Storage::disk('public')->path(static::getExportDirectory() . '/' . basename($generated->path));
    }

    public static function getDownloadUrl(GeneratedDocumentDTO $generated): string
    {
        return Storage::disk('public')->url(static::getExportDirectory() . '/' . basename($generated->path));
    }
}
