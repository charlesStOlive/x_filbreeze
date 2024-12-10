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
        Schema::table('crm_supplier_invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_my', 'invoice_qy']);
        });
        Schema::table('crm_supplier_invoices', function (Blueprint $table) {
            // Utilisation de la méthode personnalisée
            $table->createMQY('invoice_at');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_supplier_invoices', function (Blueprint $table) {
            // Utilisation de la méthode deleteMQY
            $table->deleteMQY('invoice_at');
        });

        Schema::table('crm_supplier_invoices', function (Blueprint $table) {
            // Recréer les colonnes comme des colonnes classiques
            $table->string('invoice_my')->nullable();
            $table->string('invoice_qy')->nullable();
        });
    }
};
