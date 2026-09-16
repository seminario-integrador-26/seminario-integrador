<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos que un usuario puede cambiarse a sí mismo desde su perfil.
 *
 * El correo y el nombre de usuario son datos de identidad: los administra el
 * Administrador de sistema desde la gestión de usuarios (permiso
 * usuarios.gestionar), no se autogestionan. Quien no tiene ese permiso solo
 * puede cambiar su nombre para mostrar (y su contraseña, que va por otro form).
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($this->gestionaIdentidad()) {
            $usuarioId = $this->user()->id;

            // Mismas reglas que UpdateUsuarioRequest, para que el usuario y el
            // correo se validen igual acá que en la gestión de usuarios.
            $reglas['username'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique(User::class, 'username')->ignore($usuarioId),
            ];

            $reglas['email'] = [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($usuarioId),
            ];
        }

        return $reglas;
    }

    /**
     * Sin este permiso, un `email` enviado a mano queda fuera de validated()
     * y nunca llega al modelo.
     */
    public function gestionaIdentidad(): bool
    {
        return $this->user()->can('usuarios.gestionar');
    }
}
