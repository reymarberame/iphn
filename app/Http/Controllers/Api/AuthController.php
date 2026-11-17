<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\HospitalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly HospitalAccessService $access) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $throttleKey = 'api-login|'.Str::lower($validated['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return $this->error('Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.', 429);
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password) || ! $user->isActive()) {
            RateLimiter::hit($throttleKey, 60);

            LoginLog::create([
                'user_id' => $user?->id,
                'email' => Str::lower($validated['email']),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'success' => false,
                'created_at' => now(),
            ]);

            return $this->error('Invalid credentials or inactive account.', 401);
        }

        RateLimiter::clear($throttleKey);
        $user->forceFill(['last_login_at' => now()])->save();

        LoginLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'success' => true,
            'created_at' => now(),
        ]);

        $token = $user->createToken($validated['device_name'] ?? 'api-token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->access->contextFor($user),
        ], 'Authenticated.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->success(null, 'Session revoked.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->access->contextFor($request->user()));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $validated['email'])->where('status', 'active')->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return $this->success(null, 'If an active account matches that email, a password reset link has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);

        $status = Password::reset($validated, function (User $user, string $password) {
            if (! $user->isActive()) {
                return;
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return $this->success(null, 'Password reset successfully.');
    }
}
