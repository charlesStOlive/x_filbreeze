<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cloudinary abandonné (cloudinary-labs/cloudinary-laravel ne suit pas Laravel 13) : le logo d'une société vit dans
 * la collection média « logo », que lit aussi le détecteur de couleurs. Avant de supprimer la table, les logos
 * Cloudinary des sociétés qui n'ont pas de logo média y sont rapatriés. L'ancien code est sur la branche
 * archive/cloudinary-logo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('image_cloudinaries')) {
            return;
        }

        DB::table('image_cloudinaries')
            ->where('model_type', Company::class)
            ->orderBy('id')
            ->get()
            ->each(function (object $image): void {
                $company = Company::find($image->model_id);

                if (! $company || $company->getFirstMedia('logo')) {
                    return;
                }

                try {
                    $company->addMediaFromUrl($image->url)
                        ->usingFileName($image->file_name)
                        ->toMediaCollection('logo');
                } catch (\Throwable $e) {
                    Log::warning("Logo Cloudinary non rapatrié pour la société {$company->id} ({$image->url}) : {$e->getMessage()}");
                }
            });

        Schema::drop('image_cloudinaries');
    }

    public function down(): void
    {
        Schema::create('image_cloudinaries', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('collection')->nullable();
            $table->string('file_name');
            $table->string('url');
            $table->string('public_id');
            $table->timestamps();
        });
    }
};
