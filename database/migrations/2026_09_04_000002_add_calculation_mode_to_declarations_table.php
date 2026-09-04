<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table): void {
            $table->string('calculation_mode')
                ->default('automatic')
                ->index()
                ->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table): void {
            $table->dropColumn('calculation_mode');
        });
    }
};
