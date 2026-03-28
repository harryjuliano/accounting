<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_client_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('client_key', 100)->unique();
            $table->string('client_secret_hash');
            $table->string('source_module', 50);
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('client_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['source_module', 'company_id', 'branch_id', 'is_active'], 'integration_client_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_client_credentials');
    }
};
