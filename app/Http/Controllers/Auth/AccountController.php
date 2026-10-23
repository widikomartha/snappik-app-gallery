<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetAccountPassword;
use App\Actions\Auth\VerifyAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function register(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(confirmed: true),
        ]);
        try {
            $user = User::create($data); // role and entitlement fields cannot be assigned.
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email sudah terdaftar. Silakan masuk atau pulihkan kata sandi.']);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return $this->sendVerification($request);
    }

    public function login(Request $request)
    {
        $this->normalizeEmail($request);
        $credentials = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => $this->passwordRules()]);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak cocok.']);
        }
        $request->session()->regenerate();

        // A verification link opened while logged out resumes after login. Only
        // the configured origin and verification path can be an auth return URL.
        $intended = $request->session()->pull('url.intended');
        if (is_string($intended)) {
            $target = parse_url($intended);
            $origin = parse_url(config('app.url'));
            if (is_array($target) && is_array($origin)
                && ($target['scheme'] ?? null) === ($origin['scheme'] ?? null)
                && ($target['host'] ?? null) === ($origin['host'] ?? null)
                && ($target['port'] ?? null) === ($origin['port'] ?? null)
                && ! isset($target['user']) && ! isset($target['pass'])
                && str_starts_with($target['path'] ?? '', '/email/verify/')) {
                return redirect($intended);
            }
        }

        return redirect($request->user()->role === 'admin' ? '/admin' : '/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function sendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/dashboard');
        }
        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable) {
            // Do not log mail exceptions: transport diagnostics may contain raw links.
            return redirect('/email/verify')->with('status', 'Email belum dapat dikirim. Silakan coba kirim ulang.');
        }

        return redirect('/email/verify')->with('status', 'Tautan verifikasi telah dikirim. Periksa email Anda.');
    }

    public function verify(Request $request, string $id, string $hash, VerifyAccount $verify)
    {
        abort_unless((string) $request->user()->id === $id && hash_equals(sha1($request->user()->getEmailForVerification()), $hash), 403);
        $verify->handle($request->user());

        return redirect($request->user()->role === 'admin' ? '/admin' : '/dashboard');
    }

    public function forgot(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        try {
            Password::sendResetLink($data);
        } catch (\Throwable) {
            // Same response for absent accounts, throttled links and mail failure.
            // The broker's one-minute retry window still applies after mail failure.
        }

        return redirect('/forgot-password')->with('status', 'Jika akun tersedia, tautan pemulihan akan dikirim. Anda dapat mencoba lagi setelah satu menit.');
    }

    public function reset(Request $request, ResetAccountPassword $reset)
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(confirmed: true),
        ]);
        if ($reset->handle($data) !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan pemulihan tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Kata sandi diperbarui. Silakan masuk kembali.');
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    private function passwordRules(bool $confirmed = false): array
    {
        // Bcrypt must not silently truncate multi-byte passwords or receive NUL.
        return [
            'required', 'string', 'max:72',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('Kata sandi terlalu panjang atau berisi karakter yang tidak didukung.');
                }
            },
            ...($confirmed ? ['confirmed', PasswordRule::min(12)->letters()->numbers()] : []),
        ];
    }
}
