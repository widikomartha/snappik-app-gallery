<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/** Disposable synthetic GPG keys; production hosts retain only the public key. */
final class BackupTools
{
    public string $root;

    public string $gpg;

    public function __construct()
    {
        // Keep Unix-domain agent socket names below platform path-length limits.
        $this->root = str_replace('\\', '/', storage_path('app/private/bp-'.bin2hex(random_bytes(4))));
        $this->gpg = getenv('SNAPPIK_GPG') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/usr/bin/gpg.exe' : '/usr/bin/gpg');
        mkdir($this->root.'/private', 0700, true);
        mkdir($this->root.'/public', 0700);
        try {
            $this->mustRun(['--homedir', $this->root.'/private', '--batch', '--pinentry-mode', 'loopback', '--passphrase', '',
                '--quick-generate-key', 'Synthetic backup proof <backup@example.test>', 'rsa2048', 'encr', '1d']);
            $keys = $this->mustRun(['--homedir', $this->root.'/private', '--with-colons', '--list-keys']);
            preg_match('/^fpr:+([A-F0-9]{40}):/m', $keys, $matches);
            if (! isset($matches[1])) {
                throw new \RuntimeException('Synthetic encryption fingerprint unavailable.');
            }
            $public = $this->mustRun(['--homedir', $this->root.'/private', '--export', $matches[1]]);
            $this->mustRun(['--homedir', $this->root.'/public', '--batch', '--import'], $public);
            config(['backup.enabled' => true,
                'backup.bash' => getenv('SNAPPIK_BACKUP_BASH') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : '/bin/bash'),
                'backup.pg_dump' => str_replace('\\', '/', rtrim((string) getenv('SNAPPIK_PG_BIN'), '/\\').'/pg_dump'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '')),
                'backup.gpg' => $this->gpg, 'backup.gpg_home' => self::posix($this->root.'/public'), 'backup.recipient' => $matches[1],
                'backup.r2' => ['account_id' => str_repeat('a', 32), 'access_key_id' => 'synthetic-backup-only', 'secret_access_key' => 'synthetic', 'bucket' => 'synthetic-backups'],
                'drive_proof.r2.bucket' => 'synthetic-archives', 'drive_proof.r2.access_key_id' => 'synthetic-archive-only']);
        } catch (\Throwable $exception) {
            $this->cleanup();
            throw $exception;
        }
    }

    public function decrypt(string $input, string $output, bool $privateKey = true): bool
    {
        $process = new Process([$this->gpg, '--homedir', self::posix($this->root.($privateKey ? '/private' : '/public')),
            '--batch', '--yes', '--output', self::posix($output), '--decrypt', self::posix($input)], timeout: 60);
        $process->disableOutput();

        return $process->run() === 0;
    }

    public function cleanup(): void
    {
        foreach (['public', 'private'] as $home) {
            $gpgconf = dirname($this->gpg).'/gpgconf'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
            $process = new Process([$gpgconf, '--homedir', self::posix($this->root.'/'.$home), '--kill', 'all'], timeout: 15);
            $process->disableOutput();
            $process->run();
        }
        $resolved = realpath($this->root);
        $parent = realpath(storage_path('app/private'));
        if ($resolved !== false && $parent !== false && dirname($resolved) === $parent && preg_match('/\Abp-[a-f0-9]{8}\z/', basename($resolved))) {
            File::deleteDirectory($resolved);
        }
    }

    private function mustRun(array $args, ?string $input = null): string
    {
        $process = new Process([$this->gpg, ...array_map(self::posix(...), $args)], timeout: 60);
        $process->setInput($input);
        if ($process->run() !== 0) {
            throw new \RuntimeException('Synthetic GPG setup failed: '.$process->getErrorOutput());
        }

        return $process->getOutput();
    }

    public static function posix(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_replace_callback('/\A([a-zA-Z]):\//', fn ($matches) => '/'.strtolower($matches[1]).'/', $path);
    }
}
