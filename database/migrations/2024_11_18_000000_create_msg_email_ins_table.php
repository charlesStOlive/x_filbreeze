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
        Schema::create('msg_email_ins', function (Blueprint $table) {
            $table->id();
            $table->string('msg_user_id')->nullable();
            $table->json('services')->nullable();
            $table->json('data_mail')->nullable();
            $table->json('datas_metas')->nullable();
            $table->string('from')->nullable();
            $table->string('subject')->nullable();
            $table->json('tos')->nullable();
            //
            //Elements à ajouter dans services_metas preevoir les casts
            // $table->boolean('is_rejected')->default(0);
            // $table->string('reject_info')->nullable();
            // $table->boolean('is_mail_response')->default(0);
            // $table->boolean('is_from_commercial')->default(0);
            // $table->string('regex_key_value')->nullable();
            
            
            // A ajouter dans un sous ensmeble datas_metas.selssy
            // $table->json('data_sellsy')->nullable();
            // $table->boolean('has_sellsy_call')->default(0);
            // $table->boolean('has_client')->default(0);
            // $table->boolean('has_contact')->default(0);
            // $table->boolean('has_staff')->default(0);
            // $table->boolean('has_contact_job')->default(0);
            
            //A ajouter dans un sous ensemble datas_metas.rewrite_subject
            // $table->string('new_subject')->nullable();

            //A ajouter dans un sous ensemble datas_metas.score
            // $table->boolean('has_score')->default(0);
            // $table->integer('score')->nullable();
            // $table->integer('score_job')->nullable();
            // $table->string('category')->nullable();
            

            //A ajouter dans un sous ensemble datas_metas.forward
            // $table->string('forwarded_to')->nullable();
            // $table->string('move_to_folder')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msg_email_ins');
    }
};
