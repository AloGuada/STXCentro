<?php

namespace App\Models\Sti;

use App\Models\Departamento;
use App\Models\Media;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class AsignacionActivo extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\AsignacionActivoFactory> */
    use HasFactory;

    protected $table = 'sti_asignacion_activos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'departamento_id',
        'equipo_id',
        'no_empleado',
        'empleado',
        'firma_empleado',
        'firma_token',
        'no_ti',
        'nombre_ti',
        'firma_ti',
        'fecha_inicial',
        'fecha_termino',
        'estado',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'firma_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicial' => 'date',
            'fecha_termino' => 'date',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Token aleatorio que identifica a la asignacion en el enlace publico de firma.
     */
    public static function generarFirmaToken(): string
    {
        return Str::random(48);
    }

    /**
     * Enlace publico que se comparte con el empleado para firmar sin iniciar sesion.
     *
     * @return Attribute<string|null, never>
     */
    protected function firmaUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->firma_token) {
                return null;
            }

            $parametros = ['asignacion_activo' => $this->firma_token];
            $base = config('sti.firma_url_base');

            return $base
                ? rtrim($base, '/').route('sti.firmas.create', $parametros, absolute: false)
                : route('sti.firmas.create', $parametros);
        });
    }

    public function estaFirmadaPorEmpleado(): bool
    {
        return filled($this->firma_empleado);
    }
}
