<?php

namespace App\Providers;

use App\Contracts\ArchiveStorage;
use App\Contracts\DeliveryArchiveStorage;
use App\Contracts\DrivePreviewSource;
use App\Contracts\DriveRangeSource;
use App\Contracts\DriveSource;
use App\Contracts\PaymentGateway;
use App\Integrations\ArchiveStorage\R2ArchiveStorage;
use App\Integrations\GoogleDrive\PublicDriveSource;
use App\Integrations\Midtrans\SandboxGateway;
use App\Logging\SafeLogManager;
use App\Models\DriveProofRun;
use App\Models\Project;
use App\Models\User;
use App\Policies\TenantResourcePolicy;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use App\Services\Drive\DriveRequestPacer;
use App\Services\ProviderFailure;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('log', fn ($app) => new SafeLogManager($app));
        $this->app->singleton(SandboxGateway::class, fn () => new SandboxGateway(new Client, (string) config('billing.server_key'), (string) config('billing.merchant_id')));
        $this->app->singleton(PaymentGateway::class, fn ($app) => $app->make(SandboxGateway::class));
        $this->app->singleton(DriveRequestPacer::class, fn () => new DriveRequestPacer((string) config('drive_proof.api_key'), (int) config('drive_proof.request_interval_ms')));
        $this->app->singleton(DriveSource::class, fn ($app) => new PublicDriveSource(new Client, (string) config('drive_proof.api_key'),
            beforeRequest: fn () => $app->make(DriveRequestPacer::class)->wait()));
        $this->app->singleton(DrivePreviewSource::class, fn ($app) => $app->make(DriveSource::class));
        $this->app->singleton(DriveRangeSource::class, fn ($app) => $app->make(DriveSource::class));
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
        $this->app->singleton(DeliveryArchiveStorage::class, fn ($app) => $app->make(ArchiveStorage::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Verification/recovery links use the configured origin, never the request Host.
        URL::forceRootUrl(config('app.url'));
        Gate::define('access-admin', fn (User $user): bool => app(AccountPolicy::class)->allows($user, AccountAction::Admin));
        Gate::policy(DriveProofRun::class, TenantResourcePolicy::class);
        Gate::policy(Project::class, TenantResourcePolicy::class);
        RateLimiter::for('gallery-password', fn (Request $request) => [
            Limit::perMinute(10)->by('gallery-ip:'.$request->ip()),
            Limit::perMinute(5)->by('gallery:'.hash('sha256', (string) $request->route('capability')).'|'.$request->route('type').'|'.$request->ip()),
        ]);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.hash('sha256', mb_strtolower(trim(is_string($request->input('email')) ? $request->input('email') : '')).'|'.$request->ip())),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('account-mail', fn (Request $request) => [
            Limit::perMinute(5)->by('mail-ip:'.$request->ip()),
            Limit::perMinute(3)->by('mail:'.hash('sha256', $request->user()?->email ?? mb_strtolower(trim(is_string($request->input('email')) ? $request->input('email') : '')))),
        ]);

        if ($this->app->isProduction()) {
            config(['session.secure' => true, 'app.debug' => false]);
        }
    }
}
