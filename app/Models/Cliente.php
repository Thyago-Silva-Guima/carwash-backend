<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Cliente extends Authenticatable
{
    /** @use HasFactory<ClienteFactory> */
    use HasApiTokens, HasFactory;

    protected $table = 'cliente';

    public $timestamps = false;

    protected $fillable = [
        'nome', 'telefone', 'email', 'senha_hash', 'tipo', 'aceite_termos_em',
    ];

    protected $hidden = ['senha_hash'];

    protected function casts(): array
    {
        return [
            'senha_hash' => 'hashed',
            'aceite_termos_em' => 'datetime',
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

    protected function telefone(): Attribute
    {
        return Attribute::set(
            fn (?string $valor) => $valor === null ? null : preg_replace('/\D/', '', $valor)
        );
    }

    public function enderecos(): HasMany
    {
        return $this->hasMany(Endereco::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }
}
