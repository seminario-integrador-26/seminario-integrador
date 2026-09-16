<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * US-001: el login es por nombre de usuario.
 *
 * `username` ya figura en create_users_table, pero se agregó editando esa
 * migración después de que varias bases la habían corrido: esos entornos nunca
 * recibieron la columna. Esta migración los pone al día sin recrear la base y
 * es inocua donde la columna ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            return;
        }

        // Se agrega nullable para poder rellenar los usuarios ya existentes
        // antes de exigir unicidad y NOT NULL.
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
        });

        $this->rellenarDesdeEmail();

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Deriva el usuario de la parte local del email ("juan.perez@…" -> "juanperez"),
     * agregando un sufijo numérico si dos emails colisionan.
     */
    private function rellenarDesdeEmail(): void
    {
        $usados = [];

        foreach (DB::table('users')->select('id', 'email')->orderBy('id')->get() as $fila) {
            $base = (string) Str::of($fila->email)
                ->before('@')
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9]/', '')
                ->limit(50, '');

            $username = $base !== '' ? $base : "usuario{$fila->id}";

            while (isset($usados[$username])) {
                $username = Str::limit($base, 46, '')."{$fila->id}";
            }

            $usados[$username] = true;

            DB::table('users')->where('id', $fila->id)->update(['username' => $username]);
        }
    }
};
