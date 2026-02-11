<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bomba extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\BombaFactory> */
    use HasFactory;

    protected $table = 'infra_bombas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'bomba_posos_1',
        'bomba_posos_2',
        'bomba_planta_1',
        'bomba_planta_2',
        'bomba_planta_3',
        'nivel_salmuera',
        'nivel_tinaco',
        'nivel_sisterna',
        'presion_tuberia',
        'nivel_hipoclorito',
        'nivel_anticongelante',
        'aceite_del_motor',
        'tanque_diesel',
        'voltaje_bateria',
        'bomba_jockey',
        'bomba_electrica',
        'bomba_diesel',
        'presion_tuberia_incendio',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bomba_posos_1' => 'boolean',
            'bomba_posos_2' => 'boolean',
            'bomba_planta_1' => 'boolean',
            'bomba_planta_2' => 'boolean',
            'bomba_planta_3' => 'boolean',
            'bomba_jockey' => 'boolean',
            'bomba_electrica' => 'boolean',
            'bomba_diesel' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
