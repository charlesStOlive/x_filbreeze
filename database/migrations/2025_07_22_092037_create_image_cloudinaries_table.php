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
        Schema::create('image_cloudinaries', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('collection')->nullable(); // ex: 'logo', 'banner'
            $table->string('file_name');
            $table->string('url');
            $table->string('public_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_cloudinaries');
    }
};
