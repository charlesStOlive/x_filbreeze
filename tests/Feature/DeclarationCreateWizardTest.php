<?php

namespace Tests\Feature;

use App\Filament\Resources\DeclarationResource\Pages\ListDeclarations;
use App\Models\Declaration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeclarationCreateWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_popup_creates_a_declaration_for_the_chosen_period_and_frequency(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDeclarations::class)
            ->mountAction('create')
            ->setActionData([
                'type' => Declaration::TYPE_URSSAF,
                'period_frequency' => Declaration::PERIOD_MONTHLY,
                'target_period_start' => '2026-02-01',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $declaration = Declaration::query()->where('type', Declaration::TYPE_URSSAF)->sole();

        $this->assertSame('2026-02-01', $declaration->period_start->toDateString());
        $this->assertSame('2026-02-28', $declaration->period_end->toDateString());
        $this->assertSame(['2026-02'], $declaration->covered_months);
        $this->assertSame(Declaration::MODE_AUTOMATIC, $declaration->calculation_mode);
    }

    public function test_an_already_declared_period_is_not_offered_again(): void
    {
        Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'covered_months' => ['2026-03'],
            'status' => 'draft',
        ]);

        $choices = Declaration::periodChoices(Declaration::TYPE_VAT, Declaration::PERIOD_MONTHLY);

        $this->assertArrayNotHasKey('2026-03-01', $choices);
    }
}
