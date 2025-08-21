<?php

namespace App\Services\Models;

class AnalysisResult
{
    protected bool $success;
    protected ?string $message;
    protected array $data;

    private function __construct(bool $success, ?string $message = null, array $data = [])
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
    }

    public static function success(array $data): self
    {
        return new self(true, null, $data);
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
