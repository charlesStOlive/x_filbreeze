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
        Schema::create('msg_user_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('ms_id')->unique();
            $table->string('email')->unique();
            $table->string('suscription_id')->nullable();
            $table->json('services_options')->nullable();
            $table->string('abn_secret')->nullable();
            $table->dateTime('expire_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msg_user_drafts');
    }
};
