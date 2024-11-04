<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuppliersTable extends Migration
{
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nom du fournisseur
            $table->string('email')->unique(); // Email du fournisseur
            $table->string('phone')->nullable(); // Téléphone du fournisseur
            $table->string('address')->nullable(); // Adresse du fournisseur
            $table->string('city')->nullable(); // Ville
            $table->string('country')->nullable(); // Pays
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('suppliers');
    }
}
