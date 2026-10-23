<?php

use Symfony\Component\Process\Process;
use Tests\Support\PrivacyScan;

require __DIR__.'/../../vendor/autoload.php';
chdir(dirname(__DIR__, 2));
try {
    $process = new Process(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z']);
    if ($process->run() !== 0) {
        throw new RuntimeException;
    }
    $files = array_filter(explode("\0", $process->getOutput()));
    $secrets = [];
    $names = ['APP_KEY', 'GOOGLE_DRIVE_API_KEY', 'DRIVE_PROOF_FOLDER_URL', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'MIDTRANS_SANDBOX_SERVER_KEY'];
    foreach (['.env', '.env.testing'] as $path) {
        if (is_file($path)) {
            $values = Dotenv\Dotenv::parse(file_get_contents($path));
            foreach ($names as $name) {
                $secrets[] = $values[$name] ?? '';
            }
        }
    }
    foreach ($names as $name) {
        $secrets[] = getenv($name) ?: '';
    }
    foreach (array_slice($argv, 1) as $log) {
        if (! is_file($log) || ! str_ends_with($log, '.log')) {
            throw new RuntimeException;
        }
        $files[] = $log;
    }
    $count = 0;
    $findings = 0;
    foreach (array_unique($files) as $file) {
        if (! is_file($file)) {
            continue;
        }
        $bytes = file_get_contents($file);
        if (str_contains($bytes, "\0")) {
            continue; // Binary assets are outside this text-source scan.
        }
        $count++;
        foreach (PrivacyScan::inspect($bytes, $secrets) as $finding) {
            fwrite(STDERR, $file.':'.$finding['line'].' '.$finding['category']."\n");
            $findings++;
        }
    }
    echo json_encode(['text_files' => $count, 'findings' => $findings, 'scope' => 'nonignored source and explicitly supplied logs'])."\n";
    exit($findings === 0 ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "Privacy scan could not complete; check local Git, environment syntax and log paths.\n");
    exit(2);
}
