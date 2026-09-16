<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * US-001: el login es por nombre de usuario. Esta es la ÚNICA migración que
 * declara `username`; create_users_table no lo hace.
 *
 * En su momento la columna se agregó editando create_users_table, que ya había
 * corrido en varios entornos. Eso dejó tres estados posibles de base:
 *
 *   1. Creada antes de aquella edición  -> no tiene la columna: se agrega acá.
 *   2. Creada con create_users_table ya editado -> la tiene, pero sin registro
 *      de esta migración: el guard hasColumn evita el error al correrla.
 *   3. Nueva, con el código actual -> create_users_table ya no la declara y la
 *      agrega esta migración.
 *
 * El guard cubre el caso 2 y hay que conservarlo hasta que no queden bases en
 * ese estado.
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
