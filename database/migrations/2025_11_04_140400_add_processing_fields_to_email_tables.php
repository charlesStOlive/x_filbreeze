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
        // Ajouter les champs de processing à msg_email_drafts
        Schema::table('msg_email_drafts', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable()->after('updated_at');
            $table->integer('active_jobs')->default(0)->after('status');
            $table->boolean('has_error')->default(false)->after('active_jobs');
        });

        // Ajouter les champs de processing à msg_email_ins  
        Schema::table('msg_email_ins', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable()->after('updated_at');
            $table->integer('active_jobs')->default(0)->after('status');
            $table->boolean('has_error')->default(false)->after('active_jobs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msg_email_drafts', function (Blueprint $table) {
            $table->dropColumn(['finished_at', 'active_jobs', 'has_error']);
        });

        Schema::table('msg_email_ins', function (Blueprint $table) {
            $table->dropColumn(['finished_at', 'active_jobs', 'has_error']);
        });
    }
};
