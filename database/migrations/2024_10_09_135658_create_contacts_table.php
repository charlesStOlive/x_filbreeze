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
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('firstName');
            $table->string('lastName');
            $table->string('civ')->nullable()->default('Mme/M.');
            $table->string('email');
            $table->text('memo')->nullable();
            $table->boolean('is_ex')->nullable()->default(false);
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->onDelete('cascade');
            $table->string('tel')->nullable();
            $table->string('linkedin_ext_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
