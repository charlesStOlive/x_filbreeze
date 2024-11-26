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
            $table->foreignId('msg_user_draft_id')->nullable()->constrained('msg_user_drafts')->onDelete('cascade'); // Clé étrangère
            $table->string('status', 10)->default('start')->index();
            $table->json('services_options')->nullable();
            $table->json('services_results')->nullable();
            $table->json('data_mail')->nullable();
            $table->string('from', 100)->nullable(); // Taille réduite si applicable
            $table->string('subject', 255)->nullable();
            $table->text('tos')->nullable(); // Texte long pour les adresses multiples
            $table->string('email_id', 50)->nullable()->index(); // Index pour recherches rapides
            $table->string('email_original_id', 50)->nullable();
            $table->text('errors')->nullable();
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
