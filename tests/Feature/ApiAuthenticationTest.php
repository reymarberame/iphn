<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_active_user_can_login_and_receive_a_sanctum_token(): void
    {
        $hospital = $this->hospital();
        $user = $this->user('hospital_admin', $hospital);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password@123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'phpunit',
        ]);
    }

    public function test_invalid_credentials_return_a_json_envelope(): void
    {
        $user = $this->user('hospital_admin', $this->hospital());

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials or inactive account.');
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'status' => 'inactive',
            'password' => 'Password@123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password@123',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_protected_endpoint_requires_a_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_token_can_read_profile_and_is_revoked_on_logout(): void
    {
        $hospital = $this->hospital();
        $user = $this->user('hospital_admin', $hospital);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password@123',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.primary_hospital_id', $hospital->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Clear cached guard state so the revoked token is actually re-checked.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
