<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'pending_token' => 'required|string',
            'code' => 'required|string',
        ]);

        $userId = Cache::get("2fa_pending:{$request->pending_token}");

        if (! $userId) {
            throw ValidationException::withMessages([
                'pending_token' => ['This session has expired. Please log in again.'],
            ]);
        }

        $user = User::find($userId);

        if (! $user) {
            Cache::forget("2fa_pending:{$request->pending_token}");
            throw ValidationException::withMessages([
                'code' => ['User not found.'],
            ]);
        }

        if (! $user->validateTwoFactorCode($request->code, true)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two-factor authentication code is incorrect.'],
            ]);
        }

        Cache::forget("2fa_pending:{$request->pending_token}");

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message' => 'Logged in',
            'data' => $token,
        ]);
    }
}
