<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pertence ao Dev A. Versão mínima (STUB). */
class Endereco extends Model
{
    protected $table = 'endereco';
    public $timestamps = false;
    protected $guarded = [];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
