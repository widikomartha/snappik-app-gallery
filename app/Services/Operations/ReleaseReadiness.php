<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReleaseReadiness
{
    public const EVIDENCE = [
        'scenario_01_onboarding_trial', 'scenario_02_shared_selection', 'scenario_03_complete_delivery',
        'scenario_04_zip_recovery_expiry', 'scenario_05_checkout_renewal', 'scenario_06_subscription_expiry',
        'scenario_07_paid_unlimited', 'scenario_08_admin_boundaries', 'scenario_09_environment_recovery',
        'scenario_10_appearance', 'scenario_11_motion', 'scenario_12_release_review',
        'real_drive_resource_key', 'sandbox_qris_lifecycle', 'private_r2_range_download',
        'https_domain', 'smtp_delivery', 'off_host_backup_restore', 'backup_retention_key_custody',
        'redacted_monitoring_alert', 'merchant_channel_approval', 'legal_support_approved',
        'selected_service_costs', 'final_document_approval', 'publication_authorization',
    ];

    public function checks(?string $path): array
    {
        $checks = [
            'production_environment' => app()->isProduction(),
            'debug_disabled' => config('app.debug') === false,
            'https_origin' => $this->origin(),
            'application_key' => is_string(config('app.key')) && config('app.key') !== '',
            'database_sessions_cache' => config('database.default') === 'pgsql'
                && config('session.driver') === 'database' && config('session.encrypt') === true
                && config('session.secure') === true && config('cache.default') === 'database',
            'no_development_assets' => ! is_file(public_path('hot')) && is_file(public_path('build/manifest.json')),
            'drive_configured' => is_string(config('drive_proof.api_key')) && config('drive_proof.api_key') !== '',
            'archive_configured' => $this->archive(),
            'smtp_configured' => config('auth.mailer') === 'smtp' && config('mail.mailers.smtp.transport') === 'smtp'
                && (bool) config('mail.mailers.smtp.host') && config('mail.mailers.smtp.host') !== '127.0.0.1'
                && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false
                && ! str_ends_with((string) config('mail.from.address'), '.test'),
            'backup_configured' => config('backup.enabled') && BackupConfiguration::valid(),
            'legal_support_links' => count(PublicDocuments::links()) === 3,
            'sandbox_merchant_configured' => config('billing.merchant_id') !== '' && config('billing.server_key') !== '',
            // No env switch or attestation can turn the implemented sandbox adapter into live payments.
            'production_payment_adapter' => false,
        ];
        foreach (['catalogues', 'archives', 'payments'] as $queue) {
            $checks['transactional_queue_'.$queue] = config("queue.connections.{$queue}.driver") === 'database'
                && config("queue.connections.{$queue}.connection") === 'pgsql'
                && config("queue.connections.{$queue}.table") === 'jobs';
        }
        try {
            $repository = app('migration.repository');
            $pending = array_diff(array_keys(app('migrator')->getMigrationFiles(database_path('migrations'))), $repository->getRan());
            $checks['migrations_current'] = $pending === [];
            $checks['owner_provisioned'] = DB::table('users')->where('role', 'admin')->whereNotNull('email_verified_at')->whereNull('suspended_at')->exists();
            $checks['pro_price_configured'] = Schema::hasTable('plan_versions') && DB::table('plan_versions')->exists();
        } catch (\Throwable) {
            $checks['migrations_current'] = false;
            $checks['owner_provisioned'] = false;
            $checks['pro_price_configured'] = false;
        }
        foreach (app(OperationsHealth::class)->checks() as $name => $passed) {
            $checks['operations_'.$name] = $passed;
        }
        $evidence = $this->evidence($path);
        $checks['evidence_bound_to_release'] = $evidence !== null;
        foreach (self::EVIDENCE as $name) {
            $item = $evidence['checks'][$name] ?? null;
            $checks['evidence_'.$name] = is_array($item) && ($item['passed'] ?? null) === true
                && is_string($item['record'] ?? null) && trim($item['record']) !== '';
        }

        return $checks;
    }

    private function origin(): bool
    {
        $url = config('app.url');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
            && parse_url($url, PHP_URL_QUERY) === null && parse_url($url, PHP_URL_FRAGMENT) === null
            && in_array(parse_url($url, PHP_URL_PATH), [null, '', '/'], true);
    }

    private function archive(): bool
    {
        $values = config('drive_proof.r2');

        return is_array($values) && count(array_filter($values, fn ($value) => is_string($value) && $value !== '')) === 4
            && preg_match('/\A[a-f0-9]{32}\z/', $values['account_id']) === 1;
    }

    private function evidence(?string $path): ?array
    {
        try {
            if ($path === null || ! is_file($path) || filesize($path) > 65536) {
                return null;
            }
            $data = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            $at = $data['checked_at'] ?? null;
            $build = config('app.release_commit');
            if (! is_string($build) || ! preg_match('/\A[0-9a-f]{40}\z/', $build)
                || ($data['commit'] ?? null) !== $build || ($data['origin'] ?? null) !== config('app.url')
                || ! is_int($at) || $at > now()->timestamp || now()->timestamp - $at >= 86400) {
                return null;
            }

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }
}
