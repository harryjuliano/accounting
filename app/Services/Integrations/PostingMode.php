<?php

namespace App\Services\Integrations;

use App\Models\IntegrationEvent;

class PostingMode
{
    public const RULE = 'rule';
    public const MODULE_PRESET = 'module_preset';

    public static function fromPayload(array $payload): string
    {
        $mode = (string) ($payload['posting_mode'] ?? self::RULE);

        return $mode === self::MODULE_PRESET ? self::MODULE_PRESET : self::RULE;
    }

    public static function fromEvent(IntegrationEvent $event): string
    {
        $payload = is_array($event->payload_json) ? $event->payload_json : [];

        return self::fromPayload($payload);
    }
}
