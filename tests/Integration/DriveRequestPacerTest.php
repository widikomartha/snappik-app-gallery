<?php

namespace Tests\Integration;

use App\Services\Drive\DriveRequestPacer;
use App\Services\ProviderFailure;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DriveRequestPacerTest extends TestCase
{
    public function test_same_credential_is_paced_across_independent_instances(): void
    {
        Cache::flush();
        $now = 100.0;
        $sleeps = [];
        $clock = function () use (&$now): float {
            return $now;
        };
        $sleep = function (int $microseconds) use (&$now, &$sleeps): void {
            $now += $microseconds / 1000000;
            $sleeps[] = $microseconds;
        };
        $first = new DriveRequestPacer('synthetic-key-one', clock: $clock, sleep: $sleep);
        $second = new DriveRequestPacer('synthetic-key-one', clock: $clock, sleep: $sleep);
        $other = new DriveRequestPacer('synthetic-key-two', clock: $clock, sleep: $sleep);
        $first->wait();
        $second->wait();
        $other->wait();
        $first->wait();
        $this->assertSame([1000000, 1000000], $sleeps);
        $this->assertSame(102.0, $now);
    }

    public static function invalidIntervals(): array
    {
        return [[0], [-1], [99], [10001]];
    }

    #[DataProvider('invalidIntervals')]
    public function test_invalid_provider_interval_fails_instead_of_disabling_pacing(int $interval): void
    {
        $this->expectExceptionMessage('drive_request_interval_invalid');
        new DriveRequestPacer('synthetic-key', $interval);
    }

    public function test_failed_pacing_store_never_sends_an_unpaced_request(): void
    {
        Cache::shouldReceive('lock')->once()->andThrow(new \RuntimeException('synthetic-private-marker'));
        try {
            (new DriveRequestPacer('synthetic-key'))->wait();
            $this->fail('Expected unavailable pacing failure');
        } catch (ProviderFailure $error) {
            $this->assertSame('drive_request_pacing_unavailable', $error->reason);
            $this->assertTrue($error->retryable);
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString('synthetic-private-marker', $error->getMessage());
        }
    }
}
