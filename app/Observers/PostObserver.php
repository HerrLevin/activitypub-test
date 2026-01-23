<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\User;
use App\Services\ActivityPubService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\error;

class PostObserver
{
    private ActivityPubService $activityPub;

    public function __construct(ActivityPubService $activityPub)
    {
        $this->activityPub = $activityPub;
    }

    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void
    {
        $this->sendCreateActivity($post);
    }


    private function sendCreateActivity(Post $post): void
    {
        $user = $post->user;

        // Get all followers
        $followers = $user->followers;

        if ($followers->isEmpty()) {
            Log::info('No followers to send activity to for user: ' . $user->username);
            return;
        }

        // Create the Create activity
        $createActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => url('/posts/' . $post->id),
            'type' => 'Create',
            'actor' => url('/users/' . $user->username),
            'published' => $post->created_at->toISOString(),
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'object' => [
                'id' => url('/posts/' . $post->id . '/object'),
                'type' => 'Note',
                'published' => $post->created_at->toISOString(),
                'attributedTo' => url('/users/' . $user->username),
                'content' => $post->body,
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            ],
        ];

        // Send to each follower's inbox
        foreach ($followers as $follow) {
            $this->activityPub->deliverActivity($user, $follow->follower_actor_id, $createActivity);
        }
    }

}
