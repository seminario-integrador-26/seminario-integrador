<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos de guardia. Un turno está abierto mientras hora_fin es null.
 * La apertura/cierre desde la UI corresponde a CU01 (pendiente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
