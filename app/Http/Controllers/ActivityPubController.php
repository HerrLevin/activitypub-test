<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityPubController extends Controller
{
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
        // For basic implementation, just accept and ignore for now
        return response()->json('', 202);
    }
}
