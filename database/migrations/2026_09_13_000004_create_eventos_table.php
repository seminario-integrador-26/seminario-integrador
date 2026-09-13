<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-003: eventos detectados por videovigilancia.
 * INMUTABLE: solo created_at; las correcciones van por fe de errata.
 * FKs con restrict: un evento nunca pierde su tipo, PM, turno ni autor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_evento_id')->constrained('tipos_evento')->restrictOnDelete();
            $table->foreignId('punto_monitoreo_id')->nullable()->constrained('puntos_monitoreo')->restrictOnDelete();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_hora')->index();
            // Hora exacta del video (hh:mm:ss). Obligatoria cuando hay PM.
            $table->time('timestamp_video')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
