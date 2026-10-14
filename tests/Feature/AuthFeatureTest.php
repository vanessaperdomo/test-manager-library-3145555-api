<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    /**
     * Test successful authentication returns JWT token and user info.
     */
    public function test_login_returns_token_and_role(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'access_token',
                     'token_type',
                     'expires_in',
                     'role',
                 ]);
    }

    /**
     * Test rejected login on invalid credentials.
     */
    public function test_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
    }
}
