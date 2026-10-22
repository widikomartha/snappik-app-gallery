<?php

use App\Actions\Billing\ManageVoucher;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    echo 'ready:'.DB::selectOne('select pg_backend_pid() as pid')->pid."\n";
    flush();
    $app->make(ManageVoucher::class)->save(User::findOrFail($input['admin_id']), $input['data'], $input['voucher_id'] ?? null);
    echo "saved\n";
} catch (ValidationException $e) {
    echo 'rejected:'.array_key_first($e->errors())."\n";
} catch (Throwable) {
    echo "failed\n";
    exit(1);
}
