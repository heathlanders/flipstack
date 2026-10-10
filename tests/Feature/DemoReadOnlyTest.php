<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DemoReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        Route::middleware('web')->group(function () {
            Route::get('/demo-probe', fn () => response('ok'))->name('demo-probe.show');
            Route::post('/demo-probe', fn () => response('mutated'))->name('demo-probe.store');
        });
    }

    public function test_mutations_pass_through_when_demo_mode_is_off(): void
    {
        config(['app.demo' => false]);

        $this->post('/demo-probe')->assertOk()->assertSee('mutated');
    }

    public function test_demo_mode_blocks_mutating_requests(): void
    {
        config(['app.demo' => true]);

        $this->post('/demo-probe')->assertForbidden();
    }

    public function test_demo_mode_allows_reads(): void
    {
        config(['app.demo' => true]);

        $this->get('/demo-probe')->assertOk()->assertSee('ok');
    }

    public function test_demo_mode_returns_json_message_for_api_clients(): void
    {
        config(['app.demo' => true]);

        $this->postJson('/demo-probe')
            ->assertForbidden()
            ->assertJsonPath('message', 'This demo is read-only — changes are disabled.');
    }

    public function test_demo_mode_still_allows_login_and_logout(): void
    {
        config(['app.demo' => true]);

        // Wrong credentials on purpose: anything but a 403 proves the demo
        // middleware exempted the auth routes.
        $this->from('/login')->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertRedirect('/login');

        $this->post('/logout')->assertRedirect();
    }
}
