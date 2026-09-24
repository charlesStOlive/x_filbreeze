<?php

namespace Tests\Feature;

use CharlesStOlive\FilamentUi\Colors\UiColors;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Support\Colors\Color;
use Filament\Support\Colors\ColorManager;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FilamentUiTest extends TestCase
{
    public function test_the_panel_and_the_pdf_share_the_configured_colors(): void
    {
        filament()->getPanel('admin')->register();
        $panel = FilamentColor::getColors();

        foreach (['primary', 'secondary', 'tertiary'] as $name) {
            $this->assertSame(UiColors::palettes()[$name], $panel[$name], $name);
        }

        // Les couleurs d'état ne sont pas dans la configuration : ce sont celles de Filament.
        foreach (UiColors::STATUS as $name) {
            $this->assertArrayNotHasKey($name, config('filament-ui.colors'));
            $this->assertSame(ColorManager::DEFAULT_COLORS[$name], $panel[$name], $name);
        }

        // Les PDF reçoivent les mêmes variables que celles que Filament pose dans le panel.
        $style = Blade::render('<x-filament-ui::colors />');

        foreach (['primary', 'secondary', 'tertiary', ...UiColors::STATUS] as $name) {
            foreach ([50, 500, 950] as $shade) {
                $this->assertStringContainsString("--{$name}-{$shade}:{$panel[$name][$shade]};", $style, "{$name}-{$shade}");
            }
        }
    }

    public function test_the_500_shade_is_the_configured_color(): void
    {
        $this->assertSame('#db8e57', Color::convertToHex(UiColors::palettes()['primary'][500]));
        $this->assertSame('#55cec4', Color::convertToHex(UiColors::palettes()['secondary'][500]));
        $this->assertSame('#737acf', Color::convertToHex(UiColors::palettes()['tertiary'][500]));
    }

    public function test_the_stylesheets_and_the_pdf_layout_load_the_colors(): void
    {
        $this->assertStringContainsString('filament-ui/resources/css/filament-ui.css', File::get(resource_path('css/filament/admin/filament.css')));

        foreach (['css/front/front.css', 'css/pdf/pdf.css'] as $stylesheet) {
            $this->assertStringContainsString('filament-ui/resources/css/colors.css', File::get(resource_path($stylesheet)), $stylesheet);
        }

        $this->assertStringContainsString('<x-filament-ui::colors />', File::get(resource_path('views/pdf/layouts/main.blade.php')));
    }

    public function test_the_schema_macros_are_registered_by_the_package(): void
    {
        $this->assertTrue(Section::hasMacro('borderNone'));
        $this->assertTrue(Action::hasMacro('modalPaddingNone'));
        $this->assertSame('none', Section::make()->borderNone()->getExtraAttributes()['data-ui-border']);
    }
}
