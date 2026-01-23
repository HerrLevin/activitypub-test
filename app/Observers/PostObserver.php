<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\User;
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
            $this->deliverActivity($user, $follow->follower_actor_id, $createActivity);
        }
    }

    private function deliverActivity(User $user, string $followerActorId, array $activity): void
    {
        Log::info('Deliver Activity for actor: ' . $followerActorId);
        // Fetch the follower's actor to get their inbox
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/activity+json',
            ])->get($followerActorId);
            if ($response->successful()) {
                $followerActor = $response->json();
                $inbox = $followerActor['inbox'] ?? null;
                if (!$inbox) {
                    Log::info('No inbox found for actor: ' . $followerActorId);
                    Log::debug($followerActor);
                    Log::debug($response);
                    return; // No inbox, can't send
                }
            } else {
                Log::info('No inbox found for actor: ' . $followerActorId);
                Log::debug($response->body());
                return; // Can't fetch actor
            }
        } catch (\Exception $e) {
            Log::error('Error fetching actor: ' . $followerActorId . ' Error: ' . $e->getMessage());
            return; // Error fetching
        }

        // Prepare the request
        $body = json_encode($activity);
        $date = now()->toRfc7231String();
        $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
        $host = parse_url($inbox, PHP_URL_HOST);

        // Create signature
        $signature = $this->createSignature($user, 'POST', parse_url($inbox, PHP_URL_PATH), $host, $date, $digest);

        // Send to inbox
        try {
            $data = Http::withHeaders([
                'Content-Type' => 'application/activity+json',
                'Date' => $date,
                'Digest' => $digest,
                'Signature' => $signature,
            ])->post($inbox, $activity);
            Log::info('Delivered Activity to inbox: ' . $inbox . ' Response status: ' . $data->status());
            Log::debug($data->body());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            // Log error or handle
        }
    }

    private function createSignature(User $user, string $method, string $path, string $host, string $date, string $digest): string
    {
        $keyId = url('/users/' . $user->username . '#main-key');
        $headers = "(request-target) host date digest";
        $stringToSign = "(request-target): " . strtolower($method) . " {$path}\nhost: {$host}\ndate: {$date}\ndigest: {$digest}";

        $signature = '';
        openssl_sign($stringToSign, $signature, $user->private_key, OPENSSL_ALGO_SHA256);

        return 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="' . $headers . '",signature="' . base64_encode($signature) . '"';
    }
}
