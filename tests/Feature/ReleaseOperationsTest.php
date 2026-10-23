<?php

namespace Tests\Feature;

use App\Jobs\OperationsHeartbeat;
use App\Services\Operations\BackupConfiguration;
use App\Services\Operations\OperationsHealth;
use App\Services\Operations\ReleaseReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReleaseOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_probes_use_each_transactional_product_queue_and_detect_backlog_and_out_of_order_delivery(): void
    {
        Queue::fake();
        $this->artisan('snappik:operations-heartbeat')->assertSuccessful();
        foreach (['catalogues', 'archives', 'payments'] as $channel) {
            Queue::assertPushed(OperationsHeartbeat::class, fn ($job) => $job->channel === $channel && $job->connection === $channel && $job->queue === $channel);
            (new OperationsHeartbeat($channel, now()->timestamp))->handle();
            (new OperationsHeartbeat($channel, now()->subHour()->timestamp))->handle();
        }
        Cache::put('operations:backup', now()->timestamp);
        $checks = app(OperationsHealth::class)->checks();
        $this->assertNotContains(false, $checks);
        $this->artisan('snappik:operations-check', ['--json' => true])->assertSuccessful();
        $this->travel(5)->minutes();
        $checks = app(OperationsHealth::class)->checks();
        $this->assertFalse($checks['scheduler']);
        $this->assertFalse($checks['worker_archives']);
        (new OperationsHeartbeat('archives', now()->subMinutes(5)->timestamp))->handle();
        $this->assertFalse(app(OperationsHealth::class)->checks()['worker_archives']);
        $this->artisan('snappik:operations-check')->assertFailed();
    }

    public function test_missing_or_future_probe_and_expired_backup_are_unhealthy_and_review_flags_alarm(): void
    {
        $this->assertFalse(app(OperationsHealth::class)->checks()['backup_recent']);
        Cache::put('operations:backup', now()->addMinute()->timestamp);
        $this->assertFalse(app(OperationsHealth::class)->checks()['backup_recent']);
        Cache::put('operations:backup', now()->subHours(26)->timestamp);
        $this->assertFalse(app(OperationsHealth::class)->checks()['backup_recent']);
        DB::table('failed_jobs')->insert(['uuid' => fake()->uuid(), 'connection' => 'payments', 'queue' => 'payments', 'payload' => '{}', 'exception' => 'synthetic']);
        $this->assertFalse(app(OperationsHealth::class)->checks()['failed_queue_clear']);
    }

    public function test_release_check_is_read_only_and_never_promotes_sandbox_or_echoes_private_values(): void
    {
        config(['billing.server_key' => 'synthetic-secret-that-must-not-be-output', 'release.terms_url' => 'javascript:alert(1)']);
        $counts = [DB::table('jobs')->count(), DB::table('orders')->count(), DB::table('users')->count()];
        $this->artisan('snappik:release-check', ['--json' => true])
            ->expectsOutputToContain('"ready":false')->doesntExpectOutputToContain('synthetic-secret-that-must-not-be-output')->assertFailed();
        $checks = app(ReleaseReadiness::class)->checks(null);
        $this->assertFalse($checks['production_payment_adapter']);
        $this->assertFalse($checks['legal_support_links']);
        $this->assertSame($counts, [DB::table('jobs')->count(), DB::table('orders')->count(), DB::table('users')->count()]);
    }

    public function test_attestations_require_current_build_origin_freshness_and_real_record_references(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'release-evidence-');
        config(['app.release_commit' => str_repeat('a', 40)]);
        $data = ['commit' => config('app.release_commit'), 'origin' => config('app.url'), 'checked_at' => now()->timestamp,
            'checks' => array_fill_keys(ReleaseReadiness::EVIDENCE, ['passed' => true, 'record' => 'private operator record'])];
        try {
            foreach (['valid', 'old', 'future', 'build', 'origin', 'missing_record', 'invalid_json'] as $case) {
                $fixture = $data;
                if ($case === 'old') {
                    $fixture['checked_at'] -= 86400;
                } elseif ($case === 'future') {
                    $fixture['checked_at']++;
                } elseif ($case === 'build') {
                    $fixture['commit'] = str_repeat('b', 40);
                } elseif ($case === 'origin') {
                    $fixture['origin'] = 'https://other.example.test';
                } elseif ($case === 'missing_record') {
                    $fixture['checks']['real_drive_resource_key']['record'] = ' ';
                }
                file_put_contents($path, $case === 'invalid_json' ? '{' : json_encode($fixture));
                $checks = app(ReleaseReadiness::class)->checks($path);
                $this->assertSame(in_array($case, ['valid', 'missing_record'], true), $checks['evidence_bound_to_release']);
                $this->assertSame($case === 'valid', $checks['evidence_real_drive_resource_key']);
                $this->assertFalse($checks['production_payment_adapter']);
            }
        } finally {
            unlink($path);
        }
    }

    public function test_backups_require_separate_credentials_bucket_and_full_recipient_fingerprint(): void
    {
        config(['backup.r2' => ['account_id' => str_repeat('a', 32), 'access_key_id' => 'backup-only', 'secret_access_key' => 'synthetic', 'bucket' => 'private-backups'],
            'backup.recipient' => str_repeat('A', 40), 'backup.gpg_home' => '/private/public-key-home',
            'drive_proof.r2.bucket' => 'private-archives', 'drive_proof.r2.access_key_id' => 'archive-only']);
        $this->assertTrue(BackupConfiguration::valid());
        config(['backup.r2.bucket' => 'private-archives']);
        $this->assertFalse(BackupConfiguration::valid());
        config(['backup.r2.bucket' => 'private-backups', 'backup.r2.access_key_id' => 'archive-only']);
        $this->assertFalse(BackupConfiguration::valid());
        config(['backup.r2.access_key_id' => 'backup-only', 'backup.recipient' => 'short-id']);
        $this->assertFalse(BackupConfiguration::valid());
        $this->artisan('snappik:backup')->expectsOutputToContain('Backup failed.')->assertFailed();
    }

    public function test_only_valid_owner_supplied_public_document_links_enter_all_shells(): void
    {
        config(['release.terms_url' => 'https://legal.example.test/terms', 'release.privacy_url' => 'https://legal.example.test/privacy',
            'release.support_url' => 'https://help.example.test/support']);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->where('publicDocuments.terms', 'https://legal.example.test/terms')
            ->where('publicDocuments.support', 'https://help.example.test/support'));
        config(['release.terms_url' => 'https://secret@example.test/terms', 'release.privacy_url' => 'https://example.test/privacy?token=synthetic', 'release.support_url' => 'javascript:alert(1)']);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('publicDocuments', []));
    }
}
