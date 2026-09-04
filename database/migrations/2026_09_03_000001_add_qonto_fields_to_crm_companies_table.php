<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_companies', 'qonto_client_id')) {
                $table->string('qonto_client_id')->nullable()->unique()->after('slug');
            }

            if (! Schema::hasColumn('crm_companies', 'qonto_client_synced_at')) {
                $table->timestamp('qonto_client_synced_at')->nullable()->index()->after('qonto_client_id');
            }

            if (! Schema::hasColumn('crm_companies', 'qonto_e_invoicing_reachable')) {
                $table->boolean('qonto_e_invoicing_reachable')->nullable()->after('qonto_client_synced_at');
            }

            if (! Schema::hasColumn('crm_companies', 'qonto_raw')) {
                $table->json('qonto_raw')->nullable()->after('qonto_e_invoicing_reachable');
            }

            if (! Schema::hasColumn('crm_companies', 'vat_number')) {
                $table->string('vat_number')->nullable()->index()->after('siret');
            }

            if (! Schema::hasColumn('crm_companies', 'tax_identification_number')) {
                $table->string('tax_identification_number')->nullable()->index()->after('vat_number');
            }

            if (! Schema::hasColumn('crm_companies', 'e_invoicing_address')) {
                $table->string('e_invoicing_address')->nullable()->index()->after('tax_identification_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_companies', function (Blueprint $table): void {
            foreach ([
                'e_invoicing_address',
                'tax_identification_number',
                'vat_number',
                'qonto_raw',
                'qonto_e_invoicing_reachable',
                'qonto_client_synced_at',
                'qonto_client_id',
            ] as $column) {
                if (Schema::hasColumn('crm_companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
