<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials + ['status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match an active account.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($request->user()->isStaff() ? route('admin.index') : route('account.index'));
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        $user = User::query()->create($data + ['role' => 'customer', 'status' => 'active']);
        Auth::login($user);
        $request->session()->regenerate();
        event(new Registered($user));

        return redirect()->route('verification.notice');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function forgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function forgot(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::ResetLinkSent ? back()->with('success', __($status)) : back()->withErrors(['email' => __($status)]);
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->string('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset ? redirect()->route('login')->with('success', __($status)) : back()->withErrors(['email' => __($status)]);
    }

    public function verificationNotice(): View|RedirectResponse
    {
        return request()->user()->hasVerifiedEmail() ? redirect()->route('account.index') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('account.index')->with('success', 'Your email address has been verified.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', 'A new verification link has been sent.');
    }
}
