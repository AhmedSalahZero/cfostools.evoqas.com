<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     * Supports both /reset-password?token=...&email=... and /reset-password/{token}?email=...
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $token = $request->route('token') ?? $request->query('token');
        $email = $request->query('email') ?? $request->input('email');

        if (empty($token) || empty($email)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => __('passwords.token')]);
        }

        return Inertia::render('Auth/ResetPassword', [
            'email' => $email,
            'token' => $token,
        ]);
    }

    /**
     * Handle an incoming new password request.
     * Token and expiration are validated by Password::reset(); token is invalidated after use.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(NewPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        // Generic error to avoid leaking token/email validity details
        throw ValidationException::withMessages([
            'email' => [__('passwords.token')],
        ]);
    }
}
