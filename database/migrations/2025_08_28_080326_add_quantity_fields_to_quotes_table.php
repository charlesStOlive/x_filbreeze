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
        Schema::table('crm_quotes', function (Blueprint $table) {
            $table->decimal('total_jours', 10, 2)->nullable()->after('total_options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_quotes', function (Blueprint $table) {
            $table->dropColumn(['total_jours']);
        });
    }
};
