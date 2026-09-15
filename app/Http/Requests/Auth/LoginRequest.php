<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auth\BloqueoCuentaService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * US-001: dos capas de defensa.
     *  - RateLimiter (email+IP, en caché): freno grueso contra fuerza bruta,
     *    también cubre emails inexistentes.
     *  - BloqueoCuentaService (persistido en la cuenta): bloqueo temporal a los
     *    3 intentos fallidos, independiente de la IP desde la que se intente.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $bloqueo = app(BloqueoCuentaService::class);
        $user = User::where('username', (string) $this->string('username'))->first();

        if ($user !== null && $bloqueo->estaBloqueada($user)) {
            throw $this->cuentaBloqueada($bloqueo->segundosRestantes($user));
        }

        if (! Auth::attempt($this->only('username', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            if ($user !== null && $bloqueo->registrarFallo($user)) {
                event(new Lockout($this));

                throw $this->cuentaBloqueada($bloqueo->segundosRestantes($user));
            }

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $bloqueo->limpiar(Auth::user());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }

    /**
     * Mensaje de cuenta bloqueada temporalmente.
     */
    private function cuentaBloqueada(int $segundos): ValidationException
    {
        $minutos = max(1, (int) ceil($segundos / 60));

        return ValidationException::withMessages([
            'username' => trans('auth.bloqueada', [
                'intentos' => BloqueoCuentaService::MAX_INTENTOS,
                'minutos' => $minutos,
            ]),
        ]);
    }
}
