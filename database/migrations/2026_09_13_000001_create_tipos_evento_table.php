<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos de evento. categoria ∈ TipoEvento::CATEGORIAS.
 * requiere_ubicacion y es_cuantificable son flags independientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_evento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('categoria', 30)->index();
            $table->boolean('requiere_ubicacion')->default(false);
            $table->boolean('es_cuantificable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_evento');
    }
};
