<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declarations', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->json('covered_months');

            // Instantané des montants au moment de la création.
            $table->bigInteger('turnover_excluding_tax_cents')->default(0);
            $table->bigInteger('vat_collected_cents')->default(0);
            $table->bigInteger('vat_deductible_cents')->default(0);
            $table->bigInteger('vat_due_cents')->default(0);
            $table->json('calculation_details')->nullable();

            // Ces champs serviront ensuite au rapprochement avec le paiement Qonto.
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('qonto_transaction_id')->nullable()->index();
            $table->timestamp('filed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['type', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations');
    }
};
