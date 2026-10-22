<?php

namespace Tests\Integration;

use App\Actions\Billing\Checkout;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class CheckoutConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        F::configure();
    }

    private function race(array $inputs): array
    {
        $db = config('database.connections.pgsql');
        $workers = [];
        DB::beginTransaction();
        try {
            User::whereIn('id', array_column($inputs, 'user_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($inputs as $input) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/checkout-race-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $db['database'],
                    'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '',
                    'DB_URL' => '', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array',
                ], json_encode($input), 30);
                $worker->start();
                $workers[] = $worker;
                $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'ready:')));
                preg_match('/ready:(\d+)/', $worker->getOutput(), $match);
                $waiting = false;
                for ($attempt = 0; $attempt < 200; $attempt++) {
                    DB::select('select pg_stat_clear_snapshot()');
                    if (DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$match[1]])?->wait_event_type === 'Lock') {
                        $waiting = true;
                        break;
                    }
                    usleep(10000);
                }
                $this->assertTrue($waiting, 'Checkout worker must contend on an actual PostgreSQL lock. '.$worker->getOutput());
            }
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), 'Worker must finish without raw diagnostics.');
                $results[] = trim(preg_replace('/ready:\d+\s*/', '', $worker->getOutput()));
            }
            sort($results);

            return $results;
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop();
            }
        }
    }

    private function input(User $user, ?string $voucher, ?string $key = null): array
    {
        return ['mode' => 'reserve', 'user_id' => $user->id, 'key' => $key ?? (string) Str::uuid(),
            'quote' => app(Checkout::class)->quote($user, $voucher)['quote_token']];
    }

    public function test_two_buyers_cannot_both_hold_last_total_slot(): void
    {
        $voucher = V::create(V::admin(), ['total_limit' => 1]);
        $results = $this->race([$this->input(F::buyer(), $voucher->code), $this->input(F::buyer(), $voucher->code)]);
        $this->assertSame('rejected:voucher', $results[0]);
        $this->assertStringStartsWith('reserved:', $results[1]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('voucher_reservations', 1);
    }

    public function test_two_pending_orders_cannot_bypass_one_users_limit(): void
    {
        $voucher = V::create(V::admin(), ['per_user_limit' => 1]);
        $buyer = F::buyer();
        $results = $this->race([$this->input($buyer, $voucher->code), $this->input($buyer, $voucher->code)]);
        $this->assertSame('rejected:voucher', $results[0]);
        $this->assertStringStartsWith('reserved:', $results[1]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('voucher_reservations', 1);
    }

    public function test_concurrent_same_key_has_one_order_and_one_hold(): void
    {
        $voucher = V::create(V::admin(), ['total_limit' => 1]);
        $buyer = F::buyer();
        $input = $this->input($buyer, $voucher->code);
        $results = $this->race([$input, $input]);
        $this->assertSame($results[0], $results[1]);
        $this->assertStringStartsWith('reserved:', $results[0]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('voucher_reservations', 1);
    }

    public function test_two_resumers_cannot_overlap_provider_creation(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $input = ['mode' => 'resume', 'user_id' => $buyer->id, 'order_id' => $order->id];
        $this->assertSame(['resumed:0', 'resumed:1'], $this->race([$input, $input]));
        $this->assertSame('pending', $order->fresh()->state);
        $this->assertDatabaseCount('orders', 1);
    }
}
