<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * State Analysis Facade
 *
 * @method static mixed analyze(string $modelClass, string $formatName = 'array', array $options = [])
 * @method static mixed analyzeInstance(\Illuminate\Database\Eloquent\Model $model, string $formatName = 'array', array $options = [])
 * @method static array getModelsWithStates()
 * @method static void registerFormatter(\App\Contracts\StateFormatterInterface $formatter)
 * @method static array getAvailableFormats()
 * @method static array getFormattersInfo()
 * @method static void clearCache(string $modelClass)
 * @method static array getStatistics()
 * @method static array batchAnalyze(array $modelClasses, string $formatName = 'array', array $options = [])
 * @method static string toMermaid(string $modelClass, array $options = [])
 * @method static string toJson(string $modelClass, array $options = [])
 * @method static array toArray(string $modelClass, array $options = [])
 *
 * @see \App\Services\StateAnalysisService
 */
class StateAnalysis extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'state.analysis';
    }
}