<?php
// app/Services/Processors/Emails/Support/PreflightResult.php
namespace App\Services\Processors\Emails\Support;

final class PreflightResult
{
    public function __construct(
        public bool $proceed,
        public ?string $reason = null
    ) {}

    public static function ok(): self
    {
        return new self(true);
    }

    public static function success(string $reason = null): self
    {
        return new self(true, $reason);
    }

    public static function blocked(string $reason): self
    {
        return new self(false, $reason);
    }

    public static function error(string $reason): self
    {
        return new self(false, $reason);
    }
}
