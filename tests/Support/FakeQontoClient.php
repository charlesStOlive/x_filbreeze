<?php

namespace Tests\Support;

use Closure;
use CharlesStOlive\FilamentQonto\Http\QontoClient;

/**
 * L'API Qonto en mémoire : chaque appel est noté, la réponse vient de $respond(méthode, uri, options).
 * À installer avant de résoudre un service Qonto : `$this->app->instance(QontoClient::class, $fake)`.
 */
class FakeQontoClient extends QontoClient
{
    /** @var array<int, array{method: string, uri: string, options: array<string, mixed>}> */
    public array $calls = [];

    public function __construct(private Closure $respond)
    {
    }

    public function request(string $method, string $uri, array $options = []): array
    {
        $this->calls[] = ['method' => $method, 'uri' => $uri, 'options' => $options];

        return ($this->respond)($method, $uri, $options) ?? [];
    }

    /** @return array<int, array{method: string, uri: string, options: array<string, mixed>}> */
    public function callsTo(string $method, string $uri): array
    {
        return array_values(array_filter(
            $this->calls,
            fn (array $call): bool => $call['method'] === $method && $call['uri'] === $uri,
        ));
    }

    /** @return array<int, string> « MÉTHODE uri », dans l'ordre */
    public function summary(): array
    {
        return array_map(fn (array $call): string => $call['method'].' '.$call['uri'], $this->calls);
    }
}
