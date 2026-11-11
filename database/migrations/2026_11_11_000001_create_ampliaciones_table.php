<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ampliación de una novedad: agrega información posterior (qué pasó después,
 * datos que llegaron más tarde) sin tocar el evento original, como la fe de
 * errata. Inmutable: solo created_at. Tabla nueva: no frena el servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ampliaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos');
            $table->foreignId('usuario_id')->constrained('users');
            $table->text('detalle');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ampliaciones');
    }
};
