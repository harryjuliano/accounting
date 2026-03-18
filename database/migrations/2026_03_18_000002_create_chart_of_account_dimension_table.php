<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_account_dimension', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained('dimensions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chart_of_account_id', 'dimension_id'], 'coa_dimension_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_account_dimension');
    }
};
