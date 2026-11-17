<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            LoginLog::create([
                'user_id' => null,
                'email' => Str::lower($this->input('email')),
                'ip_address' => $this->ip(),
                'user_agent' => substr((string) $this->userAgent(), 0, 255),
                'success' => false,
                'created_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::guard('web')->logout();

            LoginLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $this->ip(),
                'user_agent' => substr((string) $this->userAgent(), 0, 255),
                'success' => false,
                'created_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact your network administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user->forceFill(['last_login_at' => now()])->save();

        LoginLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $this->ip(),
            'user_agent' => substr((string) $this->userAgent(), 0, 255),
            'success' => true,
            'created_at' => now(),
        ]);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts. Please try again in '.$seconds.' seconds.',
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
