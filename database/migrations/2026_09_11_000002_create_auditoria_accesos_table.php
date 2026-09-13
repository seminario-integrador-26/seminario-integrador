<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-001: log de auditoría de accesos (trazabilidad).
 * Registro inmutable: solo created_at, nunca se edita ni se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('evento', 30)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_accesos');
    }
};
