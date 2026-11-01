<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    protected $table = 'usuario';

    public $timestamps = false;

    protected $fillable = ['nome', 'email', 'senha_hash', 'perfil'];

    protected $hidden = ['senha_hash'];

    protected function casts(): array
    {
        return [
            'senha_hash' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function ehAdmin(): bool
    {
        return $this->perfil === 'admin';
    }

    /** Equipe da empresa: administrador ou funcionário. */
    public function ehEquipe(): bool
    {
        return in_array($this->perfil, ['admin', 'funcionario'], true);
    }

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(Disponibilidade::class, 'usuario_id');
    }
}
