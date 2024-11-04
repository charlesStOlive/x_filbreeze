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
            $table->boolean('is_ex')->nullable()->default(false);
            $table->integer('nb_collab')->nullable()->default(10);
            $table->text('address')->nullable();
            $table->string('cp')->nullable()->change();
            $table->string('city')->nullable();
            $table->string('tel')->nullable();
            $table->string('site_url')->nullable();
            $table->string('email')->nullable();
            $table->string('siret')->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->float('distance')->nullable();
            $table->string('others')->nullable();
            $table->integer('country_id')->unsigned()->nullable();
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
        Schema::dropIfExists('crm_companies');
    }
};
