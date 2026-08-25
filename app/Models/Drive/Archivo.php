<?php

namespace App\Models\Drive;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Archivo extends Model
{
    use HasFactory;

    protected $table = 'drive_archivos';

    /** @var list<string> */
    protected $appends = ['link_publico'];

    /** @var list<string> */
    protected $fillable = [
        'carpeta_id',
        'nombre_original',
        'path',
        'mime',
        'size',
        'descripcion',
        'subido_por_type',
        'subido_por_id',
        'link_token',
        'link_expira_en',
        'auto_eliminar_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'subido_por_id' => 'string',
            'link_expira_en' => 'datetime',
            'auto_eliminar_en' => 'datetime',
        ];
    }

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class, 'carpeta_id');
    }

    public function generarLink(): string
    {
        $this->link_token = Str::uuid()->toString();
        $this->save();

        return $this->link_token;
    }

    public function linkActivo(): bool
    {
        if (! $this->link_token) {
            return false;
        }

        if ($this->link_expira_en && $this->link_expira_en->isPast()) {
            return false;
        }

        return true;
    }

    public function getLinkPublicoAttribute(): ?string
    {
        if (! $this->link_token) {
            return null;
        }

        return route('drive.compartido', $this->link_token);
    }
}
