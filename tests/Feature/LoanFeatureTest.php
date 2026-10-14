<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoanFeatureTest extends TestCase
{
    /**
     * Test overdue endpoint returns calculated overdue loans.
     */
    public function test_overdue_loans_endpoint_returns_success(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);
        $token = $login->json('access_token');

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson('/api/v1/loans/overdue');

        $response->assertStatus(200);
    }
}
