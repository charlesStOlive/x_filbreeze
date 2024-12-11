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
        Schema::create('crm_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('title')->nullable();
            $table->string('status')->nullable();
            $table->integer('version')->nullable();
            $table->boolean('is_retained')->default(0);
            $table->string('parent_id')->nullable();
            $table->date('end_at')->nullable();
            $table->integer('projet_id')->unsigned()->nullable();
            $table->integer('app_id')->unsigned()->nullable();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('items')->nullable();
            $table->float('total_ht')->nullable();
            $table->float('total_ht_br')->nullable();
            $table->float('total_ttc')->nullable();
            $table->integer('number')->nullable();
            $table->date('validated_at')->nullable();
            $table->createMQY('validated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_quotes');
    }
};
