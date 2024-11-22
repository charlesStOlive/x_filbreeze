<?php 

namespace App\Casts\MsGraph;

use RuntimeException;
use Illuminate\Support\Arr;
use App\Services\EmailsProcessorRegisterServices;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DynamicEmailServicesCast implements CastsAttributes
{
    protected string $processor;
    protected string $option;
    protected string $attributeKey; // Attribut JSON : `services` ou `results`

    public function __construct(string $processor, string $option, string $attributeKey = 'services')
    {
        $this->processor = $processor;
        $this->option = $option;
        $this->attributeKey = $attributeKey;

        // Validation via ServiceRegistry
        $service = EmailsProcessorRegisterServices::get($processor);

        if (!$service) {
            throw new RuntimeException("Le service {$processor} n'est pas enregistré.");
        }
    }

    public function get($model, string $key, $value, array $attributes)
    {
        $data = json_decode($attributes[$this->attributeKey] ?? '{}', true);
        return Arr::get($data, "{$this->processor}.{$this->option}");
    }

    public function set($model, string $key, $value, array $attributes)
    {
        $data = json_decode($attributes[$this->attributeKey] ?? '{}', true);
        Arr::set($data, "{$this->processor}.{$this->option}", $value);

        return [$this->attributeKey => json_encode($data)];
    }

    public static function generateCasts(array $servicesConfig, string $attributeKey = 'services'): array
    {
        $method = $attributeKey === 'services' ? 'options' : 'results';
        $casts = [];

        foreach ($servicesConfig as $key => $service) {
            foreach ($service[$method] as $optionKey => $option) {
                $casts["{$attributeKey}.{$key}.{$optionKey}"] = self::class . ":{$key},{$optionKey},{$attributeKey}";
            }
        }
        \Log::info($casts);

        return $casts;
    }
}
