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
        // La ruta ya está gateada por middleware permission:usuarios.gestionar (Administrador de sistema).
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
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($usuarioId)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuarioId)],
            // Password opcional: solo se cambia si se completa.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            // Un usuario puede tener varios roles (unión de permisos).
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleSeeder::ROLES)],
        ];
    }
}
