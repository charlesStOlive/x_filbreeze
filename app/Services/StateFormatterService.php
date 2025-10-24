<?php

namespace App\Services;

use App\Contracts\StateFormatterInterface;
use Illuminate\Support\Collection;

class StateFormatterService
{
    protected Collection $formatters;

    public function __construct()
    {
        $this->formatters = collect();
    }

    /**
     * Register a formatter
     */
    public function registerFormatter(StateFormatterInterface $formatter): void
    {
        $this->formatters->put($formatter->getFormatName(), $formatter);
    }

    /**
     * Get a formatter by name
     */
    public function getFormatter(string $formatName): ?StateFormatterInterface
    {
        return $this->formatters->get($formatName);
    }

    /**
     * Get all registered formatters
     */
    public function getAllFormatters(): Collection
    {
        return $this->formatters;
    }

    /**
     * Get available format names
     */
    public function getAvailableFormats(): array
    {
        return $this->formatters->keys()->toArray();
    }

    /**
     * Format parsed data using a specific formatter
     */
    public function format(array $parsedData, string $formatName, array $options = []): mixed
    {
        $formatter = $this->getFormatter($formatName);
        
        if (!$formatter) {
            throw new \InvalidArgumentException("Formatter '{$formatName}' not found. Available: " . implode(', ', $this->getAvailableFormats()));
        }

        // Validate options
        if (!$formatter->validateOptions($options)) {
            throw new \InvalidArgumentException("Invalid options for formatter '{$formatName}'");
        }

        // Merge with default options
        $options = array_merge($formatter->getDefaultOptions(), $options);

        return $formatter->format($parsedData, $options);
    }

    /**
     * Check if a formatter is registered
     */
    public function hasFormatter(string $formatName): bool
    {
        return $this->formatters->has($formatName);
    }

    /**
     * Get formatter info
     */
    public function getFormatterInfo(string $formatName): ?array
    {
        $formatter = $this->getFormatter($formatName);
        
        if (!$formatter) {
            return null;
        }

        return [
            'name' => $formatter->getFormatName(),
            'mime_type' => $formatter->getMimeType(),
            'file_extension' => $formatter->getFileExtension(),
            'default_options' => $formatter->getDefaultOptions(),
            'class' => get_class($formatter)
        ];
    }

    /**
     * Get all formatters info
     */
    public function getAllFormattersInfo(): array
    {
        return $this->formatters->map(function (StateFormatterInterface $formatter) {
            return [
                'name' => $formatter->getFormatName(),
                'mime_type' => $formatter->getMimeType(),
                'file_extension' => $formatter->getFileExtension(),
                'default_options' => $formatter->getDefaultOptions(),
                'class' => get_class($formatter)
            ];
        })->toArray();
    }
}