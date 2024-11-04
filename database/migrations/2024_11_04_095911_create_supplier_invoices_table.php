<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplierInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade'); // Référence au fournisseur
            $table->string('invoice_number')->unique(); // Numéro de la facture
            $table->date('invoice_date'); // Date de la facture
            $table->decimal('total_amount', 15, 2); // Montant total
            $table->string('status')->default('pending'); // Statut de la facture (ex: pending, paid, overdue)
            $table->text('notes')->nullable(); // Notes ou remarques
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplier_invoices');
    }
}
