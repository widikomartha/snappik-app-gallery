<?php

namespace Tests\Support;

final class PrivacyScan
{
    /** Reports locations/categories only; never echo matched secrets or source lines. */
    public static function inspect(string $content, array $knownSecrets = []): array
    {
        $patterns = [
            'google_key' => '/\bAIza[0-9A-Za-z_-]{35}\b/',
            'aws_access_key' => '/\b(?:AKIA|ASIA)[A-Z0-9]{16}\b/',
            'payment_key' => '/\b(?:SB-)?Mid-server-[A-Za-z0-9_-]{16,}\b/',
            'private_key' => '/-----BEGIN (?:(?:RSA |EC |OPENSSH )?PRIVATE KEY|PGP PRIVATE KEY BLOCK)-----/',
            'gallery_capability' => '~(?:/g/|%2Fg%2F)[a-f0-9]{64}(?![a-f0-9])~i',
        ];
        $findings = [];
        foreach (explode("\n", $content) as $line => $text) {
            foreach ($patterns as $category => $pattern) {
                if (preg_match($pattern, $text)) {
                    $findings[] = ['line' => $line + 1, 'category' => $category];
                }
            }
            foreach ($knownSecrets as $secret) {
                if (is_string($secret) && strlen($secret) >= 12 && str_contains($text, $secret)) {
                    $findings[] = ['line' => $line + 1, 'category' => 'configured_secret'];
                    break;
                }
            }
        }

        return $findings;
    }
}
