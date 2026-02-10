<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tipo extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\TipoFactory> */
    use HasFactory;

    protected $table = 'prod_tipos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'orden',
        'desgloce',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'desgloce' => 'boolean',
        ];
    }

    public function pagosExtra(): HasMany
    {
        return $this->hasMany(PagoExtra::class, 'tipo_id');
    }
}
