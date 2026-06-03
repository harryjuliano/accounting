<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('source_module_name')->nullable()->after('source_module');
            $table->string('counterparty_type')->nullable()->after('source_document_no');
            $table->string('counterparty_code')->nullable()->after('counterparty_type');
            $table->string('counterparty_name')->nullable()->after('counterparty_code');
            $table->string('salesperson_code')->nullable()->after('counterparty_name');
            $table->string('salesperson_name')->nullable()->after('salesperson_code');
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('item_code')->nullable()->after('description');
            $table->string('item_name')->nullable()->after('item_code');
            $table->decimal('quantity', 20, 4)->nullable()->after('item_name');
            $table->string('quantity_uom', 50)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn([
                'item_code',
                'item_name',
                'quantity',
                'quantity_uom',
            ]);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn([
                'source_module_name',
                'counterparty_type',
                'counterparty_code',
                'counterparty_name',
                'salesperson_code',
                'salesperson_name',
            ]);
        });
    }
};
