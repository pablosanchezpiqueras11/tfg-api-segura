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
        Schema::create('informes_medicos', function (Blueprint $table) {
        $table->id();
        $table->string('titulo'); // Título del informe
        $table->text('diagnostico'); // Diagnóstico detallado
        $table->string('ruta_archivo')->nullable(); // Para el PDF
        
        // Relación con el paciente 
        $table->foreignId('paciente_id')->constrained('users')->onDelete('cascade');
        
        // Relación con el médico (quien lo crea)
        $table->foreignId('medico_id')->constrained('users')->onDelete('cascade');

        $table->timestamps(); // created_at y updated_at
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informes_medicos');
    }
};
