<?php

namespace App\Contracts;

interface StateFormatterInterface
{
    /**
     * Format the parsed state data into the desired output format
     */
    public function format(array $parsedData, array $options = []): mixed;

    /**
     * Get the supported output format name
     */
    public function getFormatName(): string;

    /**
     * Get the MIME type for this format (if applicable)
     */
    public function getMimeType(): ?string;

    /**
     * Get the file extension for this format (if applicable)
     */
    public function getFileExtension(): ?string;

    /**
     * Validate the options for this formatter
     */
    public function validateOptions(array $options): bool;

    /**
     * Get the default options for this formatter
     */
    public function getDefaultOptions(): array;
}