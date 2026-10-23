<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Tests\TestCase;

class ProductionHeadersTest extends TestCase
{
    public function test_development_marker_cannot_disable_csp_outside_local_environment(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'snappik-hot-');
        file_put_contents($path, 'http://127.0.0.1:5173');
        ViteFacade::swap((new Vite)->useHotFile($path));
        try {
            foreach (['testing', 'production', 'local'] as $environment) {
                $this->app->instance('env', $environment);
                $response = app(SecurityHeaders::class)->handle(Request::create('/'), fn () => response('ok'));
                $policy = $response->headers->get('Content-Security-Policy');
                if ($environment === 'local') {
                    $this->assertNull($policy);
                } else {
                    $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
                    $this->assertStringContainsString("frame-ancestors 'none'", $policy);
                }
            }
        } finally {
            unlink($path);
        }
    }
}
