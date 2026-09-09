<?php

namespace Tests\Unit;

use App\Services\Helpers\ProductFormHelper;
use PHPUnit\Framework\TestCase;

class ProductFormHelperTest extends TestCase
{
    public function test_quantity_and_unit_cost_update_product_totals_reactively(): void
    {
        $fields = ProductFormHelper::getDynamicFormFields('jours', fn () => null);

        foreach ([$fields[0], $fields[1]] as $field) {
            $this->assertSame(300, $field->getLiveDebounce());
            $this->assertNotEmpty($field->getAfterStateUpdatedJs());
        }
    }
}
