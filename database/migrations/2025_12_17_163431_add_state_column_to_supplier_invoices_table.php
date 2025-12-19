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
            $table->string('state')->default('draft')->after('notes');
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_supplier_invoices', function (Blueprint $table) {
            $table->dropColumn('state');
            $table->string('status')->default('pending');
        });
    }
};
