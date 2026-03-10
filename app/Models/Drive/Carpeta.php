<?php

namespace App\Models\Drive;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carpeta extends Model
{
    use HasFactory;

    protected $table = 'drive_carpetas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'descripcion',
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function externos(): BelongsToMany
    {
        return $this->belongsToMany(Externo::class, 'drive_carpeta_accesos', 'carpeta_id', 'externo_id')
            ->withTimestamps();
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class, 'carpeta_id');
    }
}
