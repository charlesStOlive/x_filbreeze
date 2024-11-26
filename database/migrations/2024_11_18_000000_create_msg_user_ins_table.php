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
        Schema::create('msg_user_ins', function (Blueprint $table) {
            $table->id();
            $table->string('ms_id', 36)->unique(); // Taille fixe pour UUID
            $table->string('email')->unique();
            $table->string('suscription_id', 100)->nullable(); // Taille optimisée si applicable
            $table->json('services_options')->nullable();
            $table->string('abn_secret', 100)->nullable(); // Taille réduite
            $table->dateTime('expire_at')->nullable()->index(); // Index pour des filtres fréquents
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msg_user_ins');
    }
};
