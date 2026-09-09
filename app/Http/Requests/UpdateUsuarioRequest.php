<?php

namespace App\Http\Requests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está gateada por middleware role:Administrativo.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuarioId = $this->route('usuario')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuarioId)],
            // Password opcional: solo se cambia si se completa.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'rol' => ['required', 'string', Rule::in(RoleSeeder::ROLES)],
        ];
    }
}
