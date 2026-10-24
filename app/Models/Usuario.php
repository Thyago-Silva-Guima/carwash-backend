<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Pertence ao Dev A. Versão mínima (STUB). É a equipe da empresa. */
class Usuario extends Authenticatable
{
    protected $table = 'usuario';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['senha_hash'];

    public function getAuthPassword()
    {
        return $this->senha_hash;
    }

    public function ehAdmin(): bool
    {
        return $this->perfil === 'admin';
    }
}
