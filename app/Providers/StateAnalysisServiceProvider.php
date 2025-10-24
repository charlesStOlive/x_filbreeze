<?php

namespace App\Providers;

use App\Contracts\StateFormatterInterface;
use App\Services\StateAnalysisService;
use App\Services\StateFormatterService;
use App\Services\StateParserService;
use App\Services\Formatters\ArrayFormatter;
use App\Services\Formatters\JsonFormatter;
use App\Services\Formatters\MermaidFormatter;
use Illuminate\Support\ServiceProvider;

class StateAnalysisServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the parser service as singleton
        $this->app->singleton(StateParserService::class, function ($app) {
            return new StateParserService();
        });

        // Register the formatter service as singleton
        $this->app->singleton(StateFormatterService::class, function ($app) {
            return new StateFormatterService();
        });

        // Register the main facade service as singleton
        $this->app->singleton(StateAnalysisService::class, function ($app) {
            return new StateAnalysisService(
                $app->make(StateParserService::class),
                $app->make(StateFormatterService::class)
            );
        });

        // Register core formatters
        $this->registerCoreFormatters();

        // Create facade alias
        $this->app->alias(StateAnalysisService::class, 'state.analysis');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register formatters after container is fully built
        $this->app->booted(function () {
            $this->bootFormatters();
        });

        // Publish configuration if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/state-analysis.php' => config_path('state-analysis.php'),
            ], 'state-analysis-config');
        }
    }

    /**
     * Register core formatters as singletons
     */
    protected function registerCoreFormatters(): void
    {
        $this->app->singleton(MermaidFormatter::class);
        $this->app->singleton(JsonFormatter::class);
        $this->app->singleton(ArrayFormatter::class);
        $this->app->singleton(\App\Services\Formatters\MarkdownFormatter::class);
    }

    /**
     * Boot and register formatters with the formatter service
     */
    protected function bootFormatters(): void
    {
        $formatterService = $this->app->make(StateFormatterService::class);

        // Register core formatters
        $coreFormatters = [
            MermaidFormatter::class,
            JsonFormatter::class,
            ArrayFormatter::class,
            \App\Services\Formatters\MarkdownFormatter::class,
        ];

        foreach ($coreFormatters as $formatterClass) {
            $formatter = $this->app->make($formatterClass);
            $formatterService->registerFormatter($formatter);
        }

        // Register custom formatters from config
        $customFormatters = config('state-analysis.custom_formatters', []);
        foreach ($customFormatters as $formatterClass) {
            if (class_exists($formatterClass) && 
                in_array(StateFormatterInterface::class, class_implements($formatterClass))) {
                $formatter = $this->app->make($formatterClass);
                $formatterService->registerFormatter($formatter);
            }
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            StateParserService::class,
            StateFormatterService::class,
            StateAnalysisService::class,
            'state.analysis',
            MermaidFormatter::class,
            JsonFormatter::class,
            ArrayFormatter::class,
            \App\Services\Formatters\MarkdownFormatter::class,
        ];
    }
}