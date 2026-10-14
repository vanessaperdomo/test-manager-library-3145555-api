<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Complies with data-model.md §9 and technical-debt.md §2.
     */
    public function run(): void
    {
        // 1. Sembrar las cinco categorías fijas con identificadores oficiales y nombres en inglés
        $categories = [
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novel'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Science'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Technology'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'History'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Children'],
        ];

        foreach ($categories as $cat) {
            DB::statement('
                INSERT INTO library.category (id, name)
                VALUES (?, ?)
                ON CONFLICT (id) DO NOTHING;
            ', [$cat['id'], $cat['name']]);
        }

        // 2. Provisionar el usuario administrador inicial desde variables de entorno (spec.md §1)
        $adminUsername = strtolower(trim(env('ADMIN_USERNAME', 'admin')));
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');
        $adminId = Str::uuid()->toString();

        DB::statement('
            INSERT INTO library."user" (id, username, password_hash, role)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (username) DO NOTHING;
        ', [
            $adminId,
            $adminUsername,
            Hash::make($adminPassword),
            'admin'
        ]);
    }
}
