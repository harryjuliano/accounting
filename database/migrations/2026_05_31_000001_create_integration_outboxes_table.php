<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_outboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('integration_event_id')->nullable()->constrained('integration_events')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('source_module');
            $table->string('destination_system');
            $table->string('event_name');
            $table->string('idempotency_key');
            $table->json('payload_json');
            $table->enum('status', ['pending', 'dispatched', 'failed', 'cancelled'])->default('pending');
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['destination_system', 'idempotency_key'], 'integration_outboxes_destination_idempotency_unique');
            $table->index(['destination_system', 'status', 'available_at'], 'integration_outboxes_dispatch_lookup');
            $table->index(['source_module', 'event_name'], 'integration_outboxes_source_event_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_outboxes');
    }
};
