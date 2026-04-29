<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformeMedico extends Model
{
   // Le decimos a Laravel que use el nombre de tabla en español
    protected $table = 'informes_medicos';

    // Campos que permitimos rellenar
    protected $fillable = ['titulo', 'diagnostico', 'ruta_archivo', 'paciente_id', 'medico_id'];
}
