<?php

namespace App\Observers;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\error;

class PostObserver
{
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
            $this->deliverActivity($follow->follower_actor_id, $createActivity);
        }
    }

    private function deliverActivity(string $followerActorId, array $activity): void
    {
        Log::info('Deliver Activity for actor: ' . $followerActorId);
        // Fetch the follower's actor to get their inbox
        try {
            $response = Http::get($followerActorId);
            if ($response->successful()) {
                $followerActor = $response->json();
                $inbox = $followerActor['inbox'] ?? null;
                if (!$inbox) {
                    return; // No inbox, can't send
                }
            } else {
                return; // Can't fetch actor
            }
        } catch (\Exception $e) {
            return; // Error fetching
        }

        // Send to inbox
        try {
            Http::withHeaders([
                'Content-Type' => 'application/activity+json',
            ])->post($inbox, $activity);
            Log::info('Deliver Activity for actor: ' . $followerActorId);
        } catch (\Exception $e) {
            Log:error($e->getMessage());
            // Log error or handle
        }
    }
}
