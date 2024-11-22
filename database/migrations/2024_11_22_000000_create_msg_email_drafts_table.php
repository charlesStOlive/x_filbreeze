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
        Schema::create('msg_email_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('msg_user_draft_id')->nullable();
            $table->json('services')->nullable();
            $table->json('data_mail')->nullable();
            $table->json('results')->nullable();
            $table->string('from')->nullable();
            $table->string('subject')->nullable();
            $table->string('tos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msg_email_drafts');
    }
};
