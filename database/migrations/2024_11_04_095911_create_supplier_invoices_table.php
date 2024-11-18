<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplierInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('crm_supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('crm_suppliers')->onDelete('cascade'); // Référence au fournisseur
            $table->string('invoice_number')->nullable(); // Numéro de la facture
            $table->date('invoice_at'); // Date de la facture
            $table->string('invoice_my')->nullable(); // Format ANNEE_MOIS
            $table->string('invoice_qy')->nullable(); // Format ANNEE_Q{Q}SEMESTRE
            $table->string('currency')->nullable(); //
            $table->decimal('total_ht', 15, 2)->nullable(); //
            $table->boolean('has_tva')->default(false); // 
            $table->decimal('tx_tva', 4, 2)->nullable(); // 
            $table->decimal('tva', 15, 2)->nullable(); // 
            $table->decimal('total_ttc', 15, 2)->nullable(); // 
            $table->string('status')->default('pending'); // Statut de la facture (ex: pending, paid, overdue)
            $table->text('notes')->nullable(); // Notes ou remarques
            $table->string('sharepoint_path')->nullable(); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('crm_supplier_invoices');
    }
}
