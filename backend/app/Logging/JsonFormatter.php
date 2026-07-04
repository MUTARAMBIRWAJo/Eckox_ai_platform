<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends MonologJsonFormatter
{
    /**
     * Format a log record.
     */
    public function format(LogRecord $record): string
    {
        $traceId = null;
        if (class_exists(\Illuminate\Support\Facades\Context::class)) {
            $traceId = \Illuminate\Support\Facades\Context::get('trace_id');
        }

        $formatted = [
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
            'level' => $record->level->getName(),
            'channel' => $record->channel,
            'message' => $record->message,
            'context' => $record->context,
            'extra' => $record->extra,
        ];

        if ($traceId) {
            $formatted['trace_id'] = $traceId;
        }

        // Add custom trace ID from request if context doesn't have it
        if (!$traceId && request() && request()->headers->has('X-Trace-ID')) {
            $formatted['trace_id'] = request()->header('X-Trace-ID');
        }

        // Process exceptions for cleaner JSON output
        if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
            $e = $record->context['exception'];
            $formatted['exception'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
            ];
            unset($formatted['context']['exception']);
        }

        return $this->toJson($formatted) . "\n";
    }
}
