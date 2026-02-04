<?php

namespace App\Models\Intra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;

    protected $table = 'intra_area';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'parent_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'area_id')->orderBy('order');
    }

    /**
     * Get all ancestors of this area.
     *
     * @return \Illuminate\Support\Collection<int, Area>
     */
    public function ancestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }
}
