<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        // Create posts for the user
        $user->posts()->createMany([
            ['body' => 'This is the first test post.'],
            ['body' => 'This is the second test post.'],
        ]);

        Post::factory()->count(10)->create(['user_id' => $user->id]);
    }
}
