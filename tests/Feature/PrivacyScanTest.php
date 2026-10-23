<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\PrivacyScan;

class PrivacyScanTest extends TestCase
{
    public function test_scan_detects_secret_shapes_and_configured_values_without_reporting_contents(): void
    {
        $secrets = ['AIza'.str_repeat('a', 35), 'AKIA'.str_repeat('A', 16), 'SB-Mid-server-'.str_repeat('a', 24),
            '-----BEGIN '.'PRIVATE KEY-----', '/g/'.str_repeat('a', 64), 'synthetic-configured-value', '-----BEGIN '.'PGP PRIVATE KEY BLOCK-----'];
        $findings = PrivacyScan::inspect(implode("\n", $secrets), [$secrets[5]]);
        $this->assertCount(7, $findings);
        $this->assertSame(['google_key', 'aws_access_key', 'payment_key', 'private_key', 'gallery_capability', 'configured_secret', 'private_key'], array_column($findings, 'category'));
        $this->assertSame(range(1, 7), array_column($findings, 'line'));
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($findings));
        }
        $this->assertSame([], PrivacyScan::inspect('GOOGLE_DRIVE_API_KEY=; /g/{capability}; synthetic-test-secret'));
    }
}
