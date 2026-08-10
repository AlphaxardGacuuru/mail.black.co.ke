<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            $user->createTwoFactorAuth();
        }

        return response()->json(['message' => __('Two-factor authentication is ready to configure.')]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if (! $user->confirmTwoFactorAuth($request->input('code'))) {
            throw ValidationException::withMessages(['code' => [__('The provided two factor authentication code is incorrect.')]]);
        }

        return response()->json(['message' => __('Two-factor authentication enabled successfully.')]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->user()->disableTwoFactorAuth();

        return response()->json(['message' => __('Two-factor authentication disabled.')]);
    }

    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            $user->createTwoFactorAuth();
        }

        return response()->json(['svg' => $user->twoFactorAuth->toQr()]);
    }

    public function secretKey(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            $user->createTwoFactorAuth();
        }

        return response()->json(['secretKey' => $user->twoFactorAuth->toGroupedString()]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['two_factor' => [__('Two-factor authentication is not enabled.')]]);
        }

        if ($user->getRecoveryCodes()->isEmpty()) {
            $user->generateRecoveryCodes();
        }

        return response()->json($user->getRecoveryCodes()->values()->all());
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->user()->generateRecoveryCodes();

        return response()->json(['message' => __('Recovery codes regenerated.')]);
    }
}
