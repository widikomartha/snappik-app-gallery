<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\StreamHandler;
use Tests\TestCase;

class LogPrivacyTest extends TestCase
{
    public function test_formatted_logs_drop_free_text_exception_arguments_nested_data_and_extra(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'snappik-log-');
        $secret = 'synthetic-'.Str::random(40);
        $id = (string) Str::uuid();
        try {
            $logger = Log::build(['driver' => 'monolog', 'handler' => StreamHandler::class,
                'handler_with' => ['stream' => $path]]);
            $logger->getLogger()->pushProcessor(fn ($record) => $record->with(extra: ['request_uri' => '/g/'.$secret]));
            $logger->error('Failed https://example.test/g/'.$secret.'?key='.$secret, [
                'exception' => new \RuntimeException('SQL bindings: '.$secret, previous: new \RuntimeException($secret)),
                'password' => $secret, 'nested' => ['Authorization' => $secret], 'order_id' => $id,
                'event_id' => $secret, 'provider_status' => 503, 'request' => request(),
            ]);
            $logger->warning('payment_review_required', ['order_id' => $id, 'reason' => $secret]);
            $logger->getLogger()->close();
            config(['logging.channels.task014-file' => ['driver' => 'single', 'path' => $path],
                'logging.channels.task014-stack' => ['driver' => 'stack', 'channels' => ['task014-file']]]);
            $stack = Log::channel('task014-stack');
            $stack->error($secret, ['exception' => new \LogicException($secret)]);
            $stack->getLogger()->close();
            config(['logging.channels.emergency.path' => $path]);
            $emergency = Log::channel('missing-'.$secret);
            $emergency->error($secret, ['exception' => new \RuntimeException($secret)]);
            $emergency->getLogger()->close();
            $output = file_get_contents($path);
            $this->assertStringNotContainsString($secret, $output);
            $this->assertStringNotContainsString('/g/', $output);
            $this->assertStringNotContainsString('SQL bindings', $output);
            $this->assertStringContainsString($id, $output);
            $this->assertStringContainsString('RuntimeException', $output);
            $this->assertStringContainsString('LogicException', $output);
            $this->assertStringContainsString('503', $output);
            $this->assertStringContainsString('payment_review_required', $output);
            file_put_contents(storage_path('logs/task014-privacy.log'), $output);
        } finally {
            unlink($path);
        }
    }
}
