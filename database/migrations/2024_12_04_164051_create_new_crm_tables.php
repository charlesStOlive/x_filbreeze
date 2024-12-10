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
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('slug');
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->integer('sector_id')->unsigned()->nullable();
            $table->boolean('is_ex')->nullable()->default(0);
            $table->integer('nb_collab')->nullable()->default(10);
            $table->text('address')->nullable();
            $table->string('cp')->nullable();
            $table->string('city')->nullable();
            $table->string('tel')->nullable();
            $table->string('site_url')->nullable();
            $table->string('email')->nullable();
            $table->string('siret')->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->float('distance')->nullable();
            $table->integer('country_id')->unsigned()->nullable();
            $table->text('memo')->nullable();
            $table->json('others')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name')->virtualAs('concat(first_name, " ", last_name)');
            $table->string('civ')->nullable()->default('Mme/M.');
            $table->string('email');
            $table->boolean('is_ex')->nullable()->default(0);
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->onDelete('cascade');
            $table->string('tel')->nullable();
            $table->string('linkedin_ext_id')->nullable();
            $table->text('memo')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_companies');
    }
};
