<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Pertence ao Dev A. Versão mínima (STUB) para o módulo de agendamento. */
class Cliente extends Authenticatable
{
    protected $table = 'cliente';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['senha_hash'];

    public function getAuthPassword()
    {
        return $this->senha_hash;
    }

    public function enderecos(): HasMany
    {
        return $this->hasMany(Endereco::class, 'cliente_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'cliente_id');
    }
}
