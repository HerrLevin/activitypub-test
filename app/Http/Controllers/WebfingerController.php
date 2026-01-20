<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebfingerController extends Controller
{
    public function webfinger(Request $request): JsonResponse
    {
        $resource = $request->query('resource');
        if (!$resource || !str_starts_with($resource, 'acct:')) {
            return response()->json(['error' => 'Invalid resource'], 400);
        }

        return $this->acctUrl($resource);
    }

    private function acctUrl(string $resource)
    {
        $username = str_replace('acct:', '', $resource);
        $user = User::where('username', $username)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'subject' => $resource,
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => url('/users/' . $user->username),
                ],
            ],
        ])->header('Content-Type', 'application/jrd+json');
    }

}
