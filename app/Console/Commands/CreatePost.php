<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;

class CreatePost extends Command
{
    protected $signature = 'app:create-post {body} {--user= : The username of the user}';

    protected $description = 'Create a new post for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $body = $this->argument('body');
        $username = $this->option('user') ?? 'testuser';

        $user = User::where('username', $username)->first();

        if (!$user) {
            $this->error("User with username '{$username}' not found.");
            return;
        }

        $post = $user->posts()->create([
            'body' => $body,
        ]);

        $this->info("Post created with ID: {$post->id}");
    }
}
