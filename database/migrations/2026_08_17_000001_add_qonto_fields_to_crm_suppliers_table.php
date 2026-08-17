<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_suppliers', 'qonto_supplier_id')) {
                $table->string('qonto_supplier_id')->nullable()->unique()->after('slug');
            }

            if (! Schema::hasColumn('crm_suppliers', 'qonto_supplier_name')) {
                $table->string('qonto_supplier_name')->nullable()->index()->after('qonto_supplier_id');
            }

            if (! Schema::hasColumn('crm_suppliers', 'legal_name')) {
                $table->string('legal_name')->nullable()->index()->after('name');
            }

            if (! Schema::hasColumn('crm_suppliers', 'vat_number')) {
                $table->string('vat_number')->nullable()->index()->after('country');
            }

            if (! Schema::hasColumn('crm_suppliers', 'tax_identification_number')) {
                $table->string('tax_identification_number')->nullable()->index()->after('vat_number');
            }

            if (! Schema::hasColumn('crm_suppliers', 'siren')) {
                $table->string('siren')->nullable()->index()->after('tax_identification_number');
            }

            if (! Schema::hasColumn('crm_suppliers', 'siret')) {
                $table->string('siret')->nullable()->index()->after('siren');
            }

            if (! Schema::hasColumn('crm_suppliers', 'postal_code')) {
                $table->string('postal_code')->nullable()->after('city');
            }

            if (! Schema::hasColumn('crm_suppliers', 'iban')) {
                $table->string('iban')->nullable()->index()->after('siret');
            }

            if (! Schema::hasColumn('crm_suppliers', 'bic')) {
                $table->string('bic')->nullable()->after('iban');
            }

            if (! Schema::hasColumn('crm_suppliers', 'qonto_last_synced_at')) {
                $table->timestamp('qonto_last_synced_at')->nullable()->index()->after('bic');
            }

            if (! Schema::hasColumn('crm_suppliers', 'qonto_raw')) {
                $table->json('qonto_raw')->nullable()->after('qonto_last_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_suppliers', function (Blueprint $table): void {
            foreach ([
                'qonto_raw',
                'qonto_last_synced_at',
                'bic',
                'iban',
                'postal_code',
                'siret',
                'siren',
                'tax_identification_number',
                'vat_number',
                'legal_name',
                'qonto_supplier_name',
                'qonto_supplier_id',
            ] as $column) {
                if (Schema::hasColumn('crm_suppliers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
