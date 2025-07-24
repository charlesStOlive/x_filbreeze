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
            $table->float('total_options')->nullable();
            $table->float('total_avant_options')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_quotes', function (Blueprint $table) {
             $table->dropColumn('total_avant_options');
             $table->dropColumn('total_options');
        });
    }
};
