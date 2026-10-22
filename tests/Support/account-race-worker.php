<?php

use App\Actions\Auth\ResetAccountPassword;
use App\Actions\Auth\VerifyAccount;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Password;

// Invoked only by the PostgreSQL concurrency test. Secrets arrive on stdin,
// never process arguments or stdout. This does not expose an application route.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    echo "ready\n";
    flush();
    if ($input['action'] === 'verify') {
        $user = User::findOrFail($input['user_id']);
        $app->make(VerifyAccount::class)->handle($user);
        echo "verified\n";
    } else {
        $result = $app->make(ResetAccountPassword::class)->handle($input['credentials']);
        echo $result === Password::PASSWORD_RESET ? "reset\n" : "rejected\n";
    }
} catch (Throwable) {
    echo "failed\n";
    exit(1);
}
