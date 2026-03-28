<?php

use App\Models\IntegrationEvent;
use App\Services\Integrations\InventoryAutoJournalService;
use App\Services\Integrations\InventoryPostingRuleEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('integration:inventory:validate {--limit=100}', function (InventoryPostingRuleEngine $engine) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'inventory')
        ->whereIn('processing_status', ['received'])
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No inventory integration events with status received.');

        return self::SUCCESS;
    }

    $success = 0;
    $failed = 0;

    foreach ($events as $event) {
        $result = $engine->validateAndMark($event);

        if ($result['status'] === 'validated') {
            $success++;
            $this->line("[OK] event_id={$event->id} status=validated");

            continue;
        }

        $failed++;
        $this->warn("[FAIL] event_id={$event->id} error={$result['error']}");
    }

    $this->info("Done. validated={$success}, failed={$failed}");

    return self::SUCCESS;
})->purpose('Validate received inventory integration events using posting rules');


Artisan::command('integration:inventory:post {--limit=100}', function (InventoryAutoJournalService $service) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'inventory')
        ->where('processing_status', 'validated')
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No validated inventory integration events ready for posting.');

        return self::SUCCESS;
    }

    $processed = 0;
    $failed = 0;
    $duplicate = 0;

    foreach ($events as $event) {
        $result = $service->postValidatedEvent($event);

        if ($result['status'] === 'processed') {
            $processed++;
            $this->line("[POSTED] event_id={$event->id} journal_id={$result['journal_entry_id']}");

            continue;
        }

        if ($result['status'] === 'duplicate') {
            $duplicate++;
            $this->line("[DUPLICATE] event_id={$event->id} journal_id={$result['journal_entry_id']}");

            continue;
        }

        $failed++;
        $this->warn("[FAIL] event_id={$event->id} error={$result['error']}");
    }

    $this->info("Done. processed={$processed}, duplicate={$duplicate}, failed={$failed}");

    return self::SUCCESS;
})->purpose('Create auto journals from validated inventory integration events');


Artisan::command('integration:inventory:retry-failed {--limit=100} {--stage=all}', function () {
    $limit = (int) $this->option('limit');
    $stage = (string) $this->option('stage');

    $events = IntegrationEvent::query()
        ->where('source_module', 'inventory')
        ->where('processing_status', 'failed')
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No failed inventory integration events found.');

        return self::SUCCESS;
    }

    $requeued = 0;

    foreach ($events as $event) {
        $failureStage = $event->failures()->whereNull('resolved_at')->latest('id')->value('failure_stage');

        if ($failureStage === 'validation' && in_array($stage, ['all', 'validate'], true)) {
            $event->update(['processing_status' => 'received', 'error_message' => null]);
            $event->failures()->whereNull('resolved_at')->update(['resolved_at' => now()]);
            $event->logs()->create([
                'log_time' => now(),
                'level' => 'info',
                'message' => 'Failed validation event requeued to received.',
                'context_json' => ['previous_stage' => 'validation'],
            ]);
            $requeued++;
            continue;
        }

        if ($failureStage === 'posting' && in_array($stage, ['all', 'post'], true)) {
            $event->update(['processing_status' => 'validated', 'error_message' => null]);
            $event->failures()->whereNull('resolved_at')->update(['resolved_at' => now()]);
            $event->logs()->create([
                'log_time' => now(),
                'level' => 'info',
                'message' => 'Failed posting event requeued to validated.',
                'context_json' => ['previous_stage' => 'posting'],
            ]);
            $requeued++;
        }
    }

    $this->info("Done. requeued={$requeued}");

    return self::SUCCESS;
})->purpose('Requeue failed inventory integration events for retry');
