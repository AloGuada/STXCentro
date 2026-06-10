<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<\Database\Factories\Cotiz\MermaFactory>
 */
class Merma extends Model
{
    use HasFactory;

    protected $table = 'cotiz_mermas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'formula',
    ];
}
