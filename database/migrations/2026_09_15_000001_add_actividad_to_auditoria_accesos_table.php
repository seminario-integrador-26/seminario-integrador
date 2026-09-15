<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generaliza la auditoría de accesos (US-001) a una BITÁCORA DE ACCIONES:
 * además de login/logout registra acciones de dominio (registrar evento,
 * alta/baja/modificación de usuarios). El Administrador de sistema la consulta
 * desde la sección Usuarios (usuario, rol, IP, acción y detalle).
 *
 * - rol:         snapshot del rol del actor al momento de la acción.
 * - descripcion: detalle legible ("Evento #12 registrado", etc.).
 *
 * Sigue siendo inmutable (solo created_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria_accesos', function (Blueprint $table) {
            $table->string('rol', 60)->nullable()->after('email');
            $table->string('descripcion', 500)->nullable()->after('evento');
        });
    }

    public function down(): void
    {
        Schema::table('auditoria_accesos', function (Blueprint $table) {
            $table->dropColumn(['rol', 'descripcion']);
        });
    }
};
