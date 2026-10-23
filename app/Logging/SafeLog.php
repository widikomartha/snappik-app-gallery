<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/** Project only operational identifiers; never serialize requests, models or exceptions. */
final class SafeLog
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $context = [];
            foreach (['correlation_id', 'operation_id', 'attempt_id', 'job_id', 'order_id', 'event_id'] as $key) {
                $value = $record->context[$key] ?? null;
                if (is_string($value) && preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/i', $value)) {
                    $context[$key] = $value;
                }
            }
            $exception = $record->context['exception'] ?? null;
            if ($exception instanceof \Throwable) {
                // Anonymous class names can include filesystem paths.
                $context['error_class'] = str_contains($exception::class, '@') ? 'Throwable' : $exception::class;
            } else {
                // A stack can run the projection again after a child channel.
                $class = $record->context['error_class'] ?? null;
                if ($class === 'Throwable' || (is_string($class) && ! str_contains($class, '@')
                    && class_exists($class, false) && is_a($class, \Throwable::class, true))) {
                    $context['error_class'] = $class;
                }
            }
            $status = $record->context['provider_status'] ?? null;
            if (is_int($status) && $status >= 100 && $status <= 599) {
                $context['provider_status'] = $status;
            }

            return $record->with(
                message: $record->message === 'payment_review_required' ? 'payment_review_required' : 'application_event',
                context: $context,
                extra: [],
            );
        });
    }
}
