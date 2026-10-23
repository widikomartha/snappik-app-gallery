<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DeliveryFixture;
use Tests\Support\VoucherFixture;
use Tests\TestCase;

class ProductPresentationTest extends TestCase
{
    use DeliveryFixture, RefreshDatabase;

    public function test_public_offer_uses_the_current_persisted_price(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/welcome')
            ->where('plan.price', 29000)->where('plan.days', 30));
        $version = DB::table('plans')->where('code', 'pro')->value('current_version_id');
        $this->actingAs(VoucherFixture::admin())->put('/admin/plan', [
            'expected_version' => $version, 'price_idr' => 49000, 'reason' => 'Synthetic public price update',
        ])->assertRedirect('/admin/plan');
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('plan.price', 49000));
    }

    public function test_error_pages_expose_only_safe_status_and_keep_json_and_media_boundaries(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            Route::middleware('web')->get('/_presentation/'.$status, fn () => abort($status, 'synthetic-private-diagnostic'));
            $this->get('/_presentation/'.$status)->assertStatus($status)
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertInertia(fn (Assert $page) => $page->component('public/error')->where('statusCode', $status))
                ->assertDontSee('synthetic-private-diagnostic');
            $this->getJson('/_presentation/'.$status)->assertStatus($status)->assertJsonMissingPath('component');
        }
        $this->get('/g/invalid')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertInertia(fn (Assert $page) => $page->component('public/error')->where('clientGallery', true));
        $this->get('/g/invalid/delivery/media/1')->assertNotFound()->assertDontSee('data-page');
        $this->get('/g/invalid/delivery/archive')->assertNotFound()->assertDontSee('data-page');
    }

    public function test_named_validation_errors_use_indonesian_copy(): void
    {
        app()->setLocale('id');
        $this->post('/login', ['email' => 'invalid', 'password' => ''])->assertSessionHasErrors([
            'email' => 'Email harus berupa alamat email yang valid.', 'password' => 'Kata sandi wajib diisi.',
        ]);
    }

    public function test_gallery_password_returns_to_its_gate_after_media_or_data_requests_without_referrer(): void
    {
        Queue::fake();
        $project = $this->deliveryProject();
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        $module->update(['password_hash' => Hash::make('SyntheticDelivery123')]);
        $base = '/g/'.$project->share_token;
        foreach ([[], ['gallery_password' => 'wrong']] as $input) {
            $this->withSession(['_previous' => ['url' => url($base.'/selection/data')]])
                ->post($base.'/delivery/password', $input)->assertRedirect($base)->assertSessionHasErrors('gallery_password');
        }
        $this->withSession(['_previous' => ['url' => url($base.'/selection/preview/1')]])
            ->post($base.'/delivery/password', ['gallery_password' => 'SyntheticDelivery123'])
            ->assertRedirect($base)->assertSessionHas('gallery.'.$module->id, $module->gate_version);
        $this->get($base)->assertInertia(fn (Assert $page) => $page->where('modules.1.locked', false));
    }

    public function test_account_and_project_validation_returns_to_the_current_form_after_inertia_navigation(): void
    {
        foreach (['login', 'register', 'forgot-password'] as $form) {
            $this->withSession(['_previous' => ['url' => url('/')]])->post('/'.$form, ['email' => 'invalid'])
                ->assertRedirect('/'.$form)->assertSessionHasErrors('email');
        }
        $this->withSession(['_previous' => ['url' => url('/')]])
            ->post('/forgot-password', ['email' => 'absent-presentation@example.test'])->assertRedirect('/forgot-password')->assertSessionHas('status');
        Queue::fake();
        $project = $this->deliveryProject();
        $this->actingAs($project->photographer)->withSession(['_previous' => ['url' => url('/dashboard/data')]])
            ->post('/projects', [])->assertRedirect('/projects/create')->assertSessionHasErrors('name');
        $this->withSession(['_previous' => ['url' => url('/projects/'.$project->id.'/results/data')]])
            ->put('/projects/'.$project->id, [])->assertRedirect('/projects/'.$project->id)->assertSessionHasErrors('name');
    }
}
