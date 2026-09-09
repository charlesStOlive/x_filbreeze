<?php

namespace Tests\Unit;

use CharlesStOlive\FilamentQonto\Services\ClientsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QontoPackageCompatibilityTest extends TestCase
{
    #[DataProvider('requiredClientLookupMethods')]
    public function test_installed_qonto_package_supports_required_client_lookups(string $method): void
    {
        $this->assertTrue(
            method_exists(ClientsService::class, $method),
            "The installed Qonto package does not provide ClientsService::{$method}().",
        );
    }

    public static function requiredClientLookupMethods(): array
    {
        return [
            ['findByEmail'],
            ['findByTaxIdentificationNumber'],
            ['findByVatNumber'],
        ];
    }
}
