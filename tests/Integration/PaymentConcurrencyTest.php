<?php

namespace Tests\Integration;

use App\Models\Order;
use App\Models\User;
use App\Services\Billing\PaymentProcessing;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        F::configure();
    }

    private function input(Order $order, string $state = 'settlement'): array
    {
        return ['event_id' => app(PaymentProcessing::class)->record($order, 'operator', queue: false)->id,
            'user_id' => $order->user_id, 'state' => $state];
    }

    private function race(array $inputs): void
    {
        $db = config('database.connections.pgsql');
        $workers = [];
        DB::beginTransaction();
        try {
            User::whereIn('id', array_column($inputs, 'user_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($inputs as $input) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/payment-race-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $db['database'],
                    'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '',
                    'DB_URL' => '', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array',
                ], json_encode($input), 30);
                $worker->start();
                $workers[] = $worker;
                $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'ready:')), $worker->getOutput());
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
                $this->assertTrue($waiting, 'Payment writer must contend on a real PostgreSQL lock.');
            }
            DB::commit();
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getOutput());
                $this->assertStringEndsWith('applied', trim($worker->getOutput()));
            }
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop();
            }
        }
    }

    public function test_same_receipt_simultaneously_applies_once(): void
    {
        $buyer = F::buyer();
        $end = $buyer->accountAccess->access_ends_at;
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $input = $this->input($order);
        $this->race([$input, $input]);
        $this->assertDatabaseCount('entitlement_grants', 1);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($end->addHours(720)));
    }

    public function test_distinct_receipts_for_same_order_cannot_double_grant_or_redeem(): void
    {
        $buyer = F::buyer();
        $end = $buyer->accountAccess->access_ends_at;
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $this->race([$this->input($order), $this->input($order)]);
        $this->assertDatabaseCount('entitlement_grants', 1);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($end->addHours(720)));
    }

    public function test_two_distinct_simultaneous_settlements_extend_twice_without_losing_days(): void
    {
        $buyer = F::buyer();
        $end = $buyer->accountAccess->access_ends_at;
        $voucher = V::create(V::admin());
        $first = F::reserve($buyer, $voucher->code);
        $second = F::reserve($buyer, $voucher->code);
        $this->race([$this->input($first), $this->input($second)]);
        $this->assertDatabaseCount('entitlement_grants', 2);
        $this->assertDatabaseCount('voucher_redemptions', 2);
        $this->assertSame(2, Order::where('state', 'paid')->count());
        $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($end->addHours(1440)));
    }

    public function test_simultaneous_terminal_and_settlement_preserve_paid_contract_and_one_redemption(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $this->race([$this->input($order, 'expire'), $this->input($order)]);
        $this->assertSame('paid', $order->fresh()->state);
        $this->assertDatabaseCount('entitlement_grants', 1);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'consumed']);
    }
}
