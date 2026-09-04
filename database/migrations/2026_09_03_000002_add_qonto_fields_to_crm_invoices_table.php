<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_invoices', 'qonto_invoice_id')) {
                $table->string('qonto_invoice_id')->nullable()->unique()->after('code');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_invoice_number')) {
                $table->string('qonto_invoice_number')->nullable()->index()->after('qonto_invoice_id');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_invoice_url')) {
                $table->string('qonto_invoice_url')->nullable()->after('qonto_invoice_number');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_attachment_id')) {
                $table->string('qonto_attachment_id')->nullable()->index()->after('qonto_invoice_url');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_status')) {
                $table->string('qonto_status')->nullable()->index()->after('qonto_attachment_id');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_einvoicing_status')) {
                $table->string('qonto_einvoicing_status')->nullable()->index()->after('qonto_status');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_pdf_disk')) {
                $table->string('qonto_pdf_disk')->nullable()->after('qonto_einvoicing_status');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_pdf_path')) {
                $table->string('qonto_pdf_path')->nullable()->after('qonto_pdf_disk');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_synced_at')) {
                $table->timestamp('qonto_synced_at')->nullable()->index()->after('qonto_pdf_path');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_finalized_at')) {
                $table->timestamp('qonto_finalized_at')->nullable()->index()->after('qonto_synced_at');
            }

            if (! Schema::hasColumn('crm_invoices', 'qonto_raw')) {
                $table->json('qonto_raw')->nullable()->after('qonto_finalized_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table): void {
            foreach ([
                'qonto_raw',
                'qonto_finalized_at',
                'qonto_synced_at',
                'qonto_pdf_path',
                'qonto_pdf_disk',
                'qonto_einvoicing_status',
                'qonto_status',
                'qonto_attachment_id',
                'qonto_invoice_url',
                'qonto_invoice_number',
                'qonto_invoice_id',
            ] as $column) {
                if (Schema::hasColumn('crm_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
