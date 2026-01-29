<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'departamentos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'manager',
        'manager_usuario_id',
    ];

    public function managerUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'manager_usuario_id');
    }
}
