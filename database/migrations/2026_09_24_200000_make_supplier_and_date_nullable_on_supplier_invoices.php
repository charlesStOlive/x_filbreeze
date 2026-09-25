<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une facture extraite par l'IA est d'abord un brouillon (état Draft), même
 * quand le fournisseur n'a pas été reconnu ou la date pas lue : c'est la
 * validation (DraftToValidated) qui la met alors en Warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_supplier_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            $table->date('invoice_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('crm_supplier_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            $table->date('invoice_at')->nullable(false)->change();
        });
    }
};
