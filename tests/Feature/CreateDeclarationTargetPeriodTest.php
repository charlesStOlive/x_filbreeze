<?php

namespace Tests\Feature;

use App\Filament\Resources\DeclarationResource\Pages\CreateDeclaration;
use App\Models\Declaration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateDeclarationTargetPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_renders(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateDeclaration::class)->assertOk();
    }

    public function test_picking_a_target_month_recreates_that_specific_period(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateDeclaration::class)
            ->fillForm([
                'type' => Declaration::TYPE_VAT,
                'calculation_mode' => Declaration::MODE_AUTOMATIC,
                'target_period_start' => '2026-03-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $declaration = Declaration::query()->where('type', Declaration::TYPE_VAT)->sole();

        $this->assertSame('2026-03-01', $declaration->period_start->toDateString());
        $this->assertSame('2026-03-31', $declaration->period_end->toDateString());
    }
}
