<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * فقط برای توسعه محلی؛ روی سرور هیچ کاربر یا داده نمونه‌ای ساخته نمی‌شود.
     * کاربر نمونه نقش ندارد و به پنل دسترسی ندارد.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Seeding skipped: sample data is only created in local/testing environments.');

            return;
        }

        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
