<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuppliersTable extends Migration
{
    public function up()
    {
        Schema::create('crm_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nom du fournisseur
            $table->string('slug'); // Nom du fournisseur
            $table->string('email')->unique()->nullable(); // Email du fournisseur
            $table->string('incoming_email')->unique()->nullable(); // Email du fournisseur
            $table->string('incoming_email_title_filter')->nullable(); // Email du fournisseur
            $table->string('phone')->nullable(); // Téléphone du fournisseur
            $table->string('address')->nullable(); // Adresse du fournisseur
            $table->string('country')->nullable(); // Pays
            $table->string('city')->nullable(); // Ville
            $table->text('memo')->nullable(); // 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('crm_suppliers');
    }
}
