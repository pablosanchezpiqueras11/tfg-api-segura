<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('security_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
        $table->string('tipo_evento'); // LOGIN_SUCCESS, LOGIN_FAILED, MFA_FAILED, etc.
        $table->string('ip_address', 45);
        $table->text('user_agent'); // Navegador/Dispositivo
        $table->text('descripcion')->nullable();
        $table->timestamp('created_at')->useCurrent(); 
        // Solo created_at porque los logs no se suelen "editar"
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
