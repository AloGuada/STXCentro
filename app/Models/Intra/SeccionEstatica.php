<?php

namespace App\Models\Intra;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SeccionEstatica extends Model
{
    use HasFactory;

    protected $table = 'intra_seccion_estatica';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'titulo',
        'descripcion',
        'boton',
        'order',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
