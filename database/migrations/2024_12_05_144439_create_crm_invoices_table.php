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
        Schema::create('crm_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('title')->nullable();
            $table->string('state')->nullable();
            $table->string('modalite')->nullable();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->json('items')->nullable();
            $table->json('remise')->nullable();
            $table->float('total_ht_br')->nullable();
            $table->float('total_ht')->nullable();
            $table->boolean('has_tva')->nullable()->default(false);
            $table->string('tx_tva')->nullable();
            $table->float('tva')->nullable();
            $table->float('total_ttc')->nullable();
            $table->date('payed_at')->nullable();
            $table->createMQY('payed_at');
            $table->date('submited_at')->nullable();
            $table->createMQY('submited_at');
            $table->timestamps();
            $table->createMQY('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_invoices');
    }
};
