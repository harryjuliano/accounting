<?php

use App\Models\Branch;
use App\Models\IntegrationClientCredential;
use App\Models\IntegrationEvent;
use App\Services\Integrations\InventoryAutoJournalService;
use App\Services\Integrations\InventoryPostingRuleEngine;
use App\Services\Integrations\ModulePresetAutoJournalService;
use App\Services\Integrations\ModulePresetJournalValidator;
use App\Services\Integrations\PostingMode;
use App\Services\Integrations\SalesInvoicePostingRuleEngine;
use App\Services\Integrations\VendorInvoiceAutoJournalService;
use App\Services\Integrations\VendorInvoicePostingRuleEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('integration:inventory:validate {--limit=100}', function (InventoryPostingRuleEngine $engine, ModulePresetJournalValidator $presetValidator) {
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
        $validator = PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET ? $presetValidator : $engine;
        $result = $validator->validateAndMark($event);

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


Artisan::command('integration:inventory:post {--limit=100}', function (InventoryAutoJournalService $service, ModulePresetAutoJournalService $presetService) {
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
        $postingService = PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET ? $presetService : $service;
        $result = $postingService->postValidatedEvent($event);

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



Artisan::command('integration:sales-invoice:validate {--limit=100}', function (SalesInvoicePostingRuleEngine $engine, ModulePresetJournalValidator $presetValidator) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'sales')
        ->where('event_name', 'sales.invoice.posted')
        ->whereIn('processing_status', ['received'])
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No sales invoice integration events with status received.');

        return self::SUCCESS;
    }

    $success = 0;
    $failed = 0;

    foreach ($events as $event) {
        $validator = PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET ? $presetValidator : $engine;
        $result = $validator->validateAndMark($event);

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
})->purpose('Validate received sales invoice integration events using posting rules');

Artisan::command('integration:sales-invoice:post {--limit=100}', function (ModulePresetAutoJournalService $service) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'sales')
        ->where('event_name', 'sales.invoice.posted')
        ->where('processing_status', 'validated')
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No validated sales invoice integration events ready for posting.');

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
})->purpose('Create auto journals from validated sales invoice integration events');

Artisan::command('integration:vendor-invoice:validate {--limit=100}', function (VendorInvoicePostingRuleEngine $engine, ModulePresetJournalValidator $presetValidator) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'accounts_payable')
        ->where('event_name', 'vendor.invoice.posted')
        ->whereIn('processing_status', ['received'])
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No vendor invoice integration events with status received.');

        return self::SUCCESS;
    }

    $success = 0;
    $failed = 0;

    foreach ($events as $event) {
        $validator = PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET ? $presetValidator : $engine;
        $result = $validator->validateAndMark($event);

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
})->purpose('Validate received vendor invoice integration events using posting rules');


Artisan::command('integration:vendor-invoice:post {--limit=100}', function (VendorInvoiceAutoJournalService $service, ModulePresetAutoJournalService $presetService) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'accounts_payable')
        ->where('event_name', 'vendor.invoice.posted')
        ->where('processing_status', 'validated')
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No validated vendor invoice integration events ready for posting.');

        return self::SUCCESS;
    }

    $processed = 0;
    $failed = 0;
    $duplicate = 0;

    foreach ($events as $event) {
        $postingService = PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET ? $presetService : $service;
        $result = $postingService->postValidatedEvent($event);

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
})->purpose('Create auto journals from validated vendor invoice integration events');


Artisan::command('integration:vendor-payment:validate {--limit=100}', function (VendorInvoicePostingRuleEngine $engine, ModulePresetJournalValidator $presetValidator) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'accounts_payable')
        ->where('event_name', 'vendor.payment.posted')
        ->whereIn('processing_status', ['received'])
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No vendor payment integration events with status received.');

        return self::SUCCESS;
    }

    $success = 0;
    $failed = 0;

    foreach ($events as $event) {
        $validator = PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET ? $presetValidator : $engine;
        $result = $validator->validateAndMark($event);

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
})->purpose('Validate received vendor payment integration events using posting rules');


Artisan::command('integration:vendor-payment:post {--limit=100}', function (VendorInvoiceAutoJournalService $service, ModulePresetAutoJournalService $presetService) {
    $limit = (int) $this->option('limit');

    $events = IntegrationEvent::query()
        ->where('source_module', 'accounts_payable')
        ->where('event_name', 'vendor.payment.posted')
        ->where('processing_status', 'validated')
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No validated vendor payment integration events ready for posting.');

        return self::SUCCESS;
    }

    $processed = 0;
    $failed = 0;
    $duplicate = 0;

    foreach ($events as $event) {
        $postingService = PostingMode::fromEvent($event) === PostingMode::MODULE_PRESET ? $presetService : $service;
        $result = $postingService->postValidatedEvent($event);

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
})->purpose('Create auto journals from validated vendor payment integration events');


Artisan::command('integration:module-preset:validate {--limit=100} {--module=}', function (ModulePresetJournalValidator $validator) {
    $limit = (int) $this->option('limit');
    $module = (string) $this->option('module');

    $events = IntegrationEvent::query()
        ->whereIn('processing_status', ['received'])
        ->when($module !== '', fn ($query) => $query->where('source_module', $module))
        ->where('payload_json->posting_mode', PostingMode::MODULE_PRESET)
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No module preset integration events with status received.');

        return self::SUCCESS;
    }

    $success = 0;
    $failed = 0;

    foreach ($events as $event) {
        $result = $validator->validateAndMark($event);

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
})->purpose('Validate received module preset journal integration events');


Artisan::command('integration:module-preset:post {--limit=100} {--module=}', function (ModulePresetAutoJournalService $service) {
    $limit = (int) $this->option('limit');
    $module = (string) $this->option('module');

    $events = IntegrationEvent::query()
        ->where('processing_status', 'validated')
        ->when($module !== '', fn ($query) => $query->where('source_module', $module))
        ->where('payload_json->posting_mode', PostingMode::MODULE_PRESET)
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($events->isEmpty()) {
        $this->info('No validated module preset integration events ready for posting.');

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
})->purpose('Create auto journals from validated module preset journal integration events');


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


Artisan::command('integration:client:create {company_id} {branch_id} {--module=all} {--name=}', function () {
    $companyId = (int) $this->argument('company_id');
    $branchId = (int) $this->argument('branch_id');
    $module = Str::lower((string) $this->option('module'));

    $branch = Branch::query()
        ->where('id', $branchId)
        ->where('company_id', $companyId)
        ->first();

    if (! $branch) {
        $this->error('Branch not found for the given company.');

        return self::FAILURE;
    }

    $clientKey = strtoupper($module) . '-' . Str::upper(Str::random(12));
    $clientSecret = Str::random(48);

    IntegrationClientCredential::create([
        'client_key' => $clientKey,
        'client_secret_hash' => hash('sha256', $clientSecret),
        'source_module' => $module,
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'client_name' => $this->option('name') ?: null,
        'is_active' => true,
    ]);

    $this->info('Client credential created. Save this secret now (it will not be shown again).');
    $this->line('client_key: ' . $clientKey);
    $this->line('client_secret: ' . $clientSecret);

    return self::SUCCESS;
})->purpose('Create client_key/client_secret for integration payload verification scope (use --module=all for all modules)');
