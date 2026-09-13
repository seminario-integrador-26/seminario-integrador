<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-003: Puntos de Monitoreo (394 = 235 municipales + 159 provinciales).
 * latitud/longitud alimentan Leaflet + OpenStreetMap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puntos_monitoreo', function (Blueprint $table) {
            $table->id();
            // TODO: confirmar con el equipo el formato del código oficial del PM.
            $table->string('codigo', 30)->unique();
            $table->string('nombre');
            $table->string('jurisdiccion', 20)->index();
            // Nullable hasta contar con el relevamiento de coordenadas de todos los PM.
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos_monitoreo');
    }
};
