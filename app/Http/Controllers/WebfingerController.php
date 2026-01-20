<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebfingerController extends Controller
{
    public function webfinger(Request $request): JsonResponse
    {
        // remove url from resource
        $url = config('app.url');
        // get base url without http(s)://
        $baseUrl = preg_replace('#^https?://#', '', $url);
        $resource = $request->query('resource');

        if (!$resource || !str_starts_with($resource, 'acct:')) {
            return response()->json(['error' => 'Invalid resource'], 400);
        }

        return $this->acctUrl($resource, $baseUrl);
    }

    private function acctUrl(string $resource, string $baseUrl)
    {
        $username = str_replace('acct:', '', $resource);
        $elements = explode('@', $username);
        if (count($elements) != 2) {
            return response()->json(['error' => 'Invalid acct format'], 400);
        }
        $username = $elements[0];
        $domain = $elements[1];
        if ($domain !== $baseUrl) {
            return response()->json(['error' => 'Domain mismatch'], 400);
        }
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
