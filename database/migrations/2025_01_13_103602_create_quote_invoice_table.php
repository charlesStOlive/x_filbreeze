<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuoteInvoiceTable extends Migration
{
    public function up()
    {
        Schema::create('crm_quotes_invoices', function (Blueprint $table) {
            $table->foreignId('quote_id')->constrained('crm_quotes')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->decimal('total_quote', 8, 2)->default(0);
            $table->decimal('total_quote_left', 8, 2)->default(0);
            $table->decimal('billing_percentage', 8, 2)->default(0);
            $table->decimal('total', 8, 2)->default(0);
            $table->primary(['quote_id', 'invoice_id'], 'quote_invoice_primary');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('crm_quotes_invoices');
    }
}
