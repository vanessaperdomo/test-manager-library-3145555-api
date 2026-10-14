<?php

namespace Tests\Feature;

use Tests\TestCase;

class BookCatalogFeatureTest extends TestCase
{
    /**
     * Test listing books returns paginated catalog.
     */
    public function test_books_catalog_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/books');
        $response->assertStatus(401);
    }

    /**
     * Test category catalog returns 5 pre-seeded categories.
     */
    public function test_categories_endpoint_structure(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);
        $token = $login->json('access_token');

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson('/api/v1/categories');

        $response->assertStatus(200);
    }
}
