<?php

namespace App\Providers;

use App\Contracts\ArchiveStorage;
use App\Contracts\DriveSource;
use App\Integrations\ArchiveStorage\R2ArchiveStorage;
use App\Integrations\GoogleDrive\PublicDriveSource;
use App\Models\User;
use App\Services\Drive\DriveRequestPacer;
use App\Services\ProviderFailure;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DriveRequestPacer::class, fn () => new DriveRequestPacer((string) config('drive_proof.api_key'), (int) config('drive_proof.request_interval_ms')));
        $this->app->singleton(DriveSource::class, fn ($app) => new PublicDriveSource(new Client, (string) config('drive_proof.api_key'),
            beforeRequest: fn () => $app->make(DriveRequestPacer::class)->wait()));
        $this->app->singleton(S3Client::class, function () {
            $options = config('drive_proof.r2');
            foreach ($options as $value) {
                if (! is_string($value) || $value === '') {
                    throw new ProviderFailure('r2_credentials_missing');
                }
            }
            if (! preg_match('/\A[a-f0-9]{32}\z/', $options['account_id'])) {
                throw new ProviderFailure('r2_invalid_account_id');
            }

            return new S3Client([
                'version' => '2006-03-01', 'region' => 'auto',
                'endpoint' => 'https://'.$options['account_id'].'.r2.cloudflarestorage.com',
                'credentials' => ['key' => $options['access_key_id'], 'secret' => $options['secret_access_key']],
                'use_path_style_endpoint' => true, 'retries' => 2,
                'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
                'http' => ['connect_timeout' => 10, 'timeout' => 300, 'allow_redirects' => false],
            ]);
        });
        $this->app->singleton(ArchiveStorage::class, fn ($app) => new R2ArchiveStorage($app->make(S3Client::class), (string) config('drive_proof.r2.bucket')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->role === 'admin');

        if ($this->app->isProduction()) {
            config(['session.secure' => true, 'app.debug' => false]);
        }
    }
}
