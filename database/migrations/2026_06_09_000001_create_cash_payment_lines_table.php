<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_transaction_id')->constrained('cash_transactions')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('transaction_code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->string('reference_no')->nullable();
            $table->timestamps();

            $table->unique(['cash_transaction_id', 'line_no']);
            $table->index('debit_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_payment_lines');
    }
};
