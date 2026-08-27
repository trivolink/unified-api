<?php

namespace TrivoLink\UnifiedApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LogicException;
use TrivoLink\UnifiedApi\Envelope;

class TokenController
{
    /**
     * Exchange email + password for a Sanctum bearer token. Stateless:
     * no session is started and CSRF never applies.
     */
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::createUserProvider(config('auth.guards.web.provider'))
            ?->retrieveByCredentials(['email' => $credentials['email']]);

        if (! $user || ! Hash::check($credentials['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! method_exists($user, 'createToken')) {
            throw new LogicException(
                'The user model must use the Laravel\Sanctum\HasApiTokens trait to issue tokens.'
            );
        }

        $token = $user->createToken($credentials['device_name'] ?? 'mobile')->plainTextToken;

        return Envelope::success(['token' => $token])->toResponse($request);
    }
}
