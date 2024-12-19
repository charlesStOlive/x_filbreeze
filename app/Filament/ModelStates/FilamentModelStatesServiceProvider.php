<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentModelStatesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'model-states-for-filament';

    #[Override]
    public function configurePackage(Package $package): void
    {
        $package->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations();
    }
}
