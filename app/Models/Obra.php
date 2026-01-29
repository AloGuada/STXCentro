<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Obra extends Model
{
    use HasFactory;

    protected $table = 'obras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'no',
        'descripcion',
    ];
}
