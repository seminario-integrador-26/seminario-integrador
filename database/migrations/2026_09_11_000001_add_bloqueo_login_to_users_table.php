<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-001: bloqueo temporal de la cuenta tras N intentos fallidos de login.
 * El contador vive en la cuenta (no en caché por IP) para que el bloqueo
 * aplique al usuario venga de donde venga el intento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('intentos_fallidos')->default(0);
            $table->timestamp('bloqueado_hasta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['intentos_fallidos', 'bloqueado_hasta']);
        });
    }
};
