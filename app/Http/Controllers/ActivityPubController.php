<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Models\Follow;
use App\Services\ActivityPubService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActivityPubController extends Controller
{
    private ActivityPubService $activityPubService;

    public function __construct(ActivityPubService $activityPubService)
    {
        $this->activityPubService = $activityPubService;
    }

    public function actor(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $actor = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => url('/users/' . $user->username),
            'type' => 'Person',
            'preferredUsername' => $user->username,
            'name' => $user->name,
            'inbox' => url('/users/' . $user->username . '/inbox'),
            'outbox' => url('/users/' . $user->username . '/outbox'),
            'publicKey' => [
                'id' => url('/users/' . $user->username . '#main-key'),
                'owner' => url('/users/' . $user->username),
                'publicKeyPem' => $user->public_key,
            ],
        ];

        return response()->json($actor)->header('Content-Type', 'application/activity+json');
    }

    public function outbox(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (!$request->has('page') && !$request->has('cursor') ) {
            // First page request
            return response()->json([
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => url('/users/' . $user->username . '/outbox'),
                'type' => 'OrderedCollection',
                'totalItems' => $user->posts()->count(),
                'first' => url('/users/' . $user->username . '/outbox?page=true'),
            ])->header('Content-Type', 'application/activity+json');
        }


        $posts = $user->posts()->orderBy('created_at', 'desc')->orderBy('id', 'desc')->cursorPaginate(5);

        $items = [];
        foreach ($posts as $post) {
            $items[] = [
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
        }

        $outbox = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => url('/users/' . $user->username . '/outbox'),
            'type' => 'OrderedCollection',
            'next' => $posts->nextPageUrl(),
            'prev' => $posts->previousPageUrl(),
            'partOf' => url('/users/' . $user->username . '/outbox'),
            'orderedItems' => $items,
        ];

        return response()->json($outbox)->header('Content-Type', 'application/activity+json');
    }

    public function inbox(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $activity = $request->json()->all();

        if ($activity['type'] === 'Follow') {
            // Handle follow
            $followerActorId = $activity['actor'];
            $followedActorId = $activity['object'];

            // Verify it's following this user
            if ($followedActorId !== url('/users/' . $user->username)) {
                return response()->json(['error' => 'Invalid follow object'], 400);
            }

            // Create follow record
            Follow::firstOrCreate([
                'follower_actor_id' => $followerActorId,
                'followed_user_id' => $user->id,
            ]);

            // Send Accept activity
            $this->sendAccept($user, $activity);

            return response()->json('', 202);
        }

        // For other activities, just accept
        return response()->json('', 202);
    }

    public function postObject(Request $request, int $id): JsonResponse
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json(['error' => 'Post not found'], 404);
        }

        $object = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => url('/posts/' . $post->id . '/object'),
            'type' => 'Note',
            'published' => $post->created_at->toISOString(),
            'attributedTo' => url('/users/' . $post->user->username),
            'content' => $post->body,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ];

        return response()->json($object)->header('Content-Type', 'application/activity+json');
    }

    private function sendAccept(User $user, array $followActivity): void
    {
        $followerActorId = $followActivity['actor'];

        // Create Accept activity
        $acceptActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => url('/activities/' . uniqid()),
            'type' => 'Accept',
            'actor' => url('/users/' . $user->username),
            'object' => $followActivity,
        ];
        $this->activityPubService->deliverActivity($user, $followerActorId, $acceptActivity);
    }
}
