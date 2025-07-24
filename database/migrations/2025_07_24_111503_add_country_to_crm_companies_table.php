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
        Schema::table('crm_companies', function (Blueprint $table) {
            $table->dropColumn('country_id');
            $table->string('country')->nullable()->after('city')->default('FR');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_companies', function (Blueprint $table) {
            $table->integer('country_id')->unsigned()->nullable();
            $table->dropColumn('country');
        });
    }
};
