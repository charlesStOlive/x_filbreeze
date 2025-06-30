<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('datasets_company_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('crm_companies')->onDelete('cascade'); // supprime juste les lignes du pivot si company supprimée
            $table->foreignId('product_id')->constrained('datasets_products')->onDelete('cascade'); // idem pour produit
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datasets_company_product');
    }
};
