<?php

namespace App\Models;

use Database\Factories\EnderecoFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Endereco extends Model
{
    /** @use HasFactory<EnderecoFactory> */
    use HasFactory;

    protected $table = 'endereco';

    public $timestamps = false;

    protected $fillable = [
        'rua', 'numero', 'bairro', 'complemento', 'cidade', 'cep',
    ];

    protected function cep(): Attribute
    {
        return Attribute::set(
            fn (?string $valor) => $valor === null ? null : preg_replace('/\D/', '', $valor)
        );
    }

    protected function enderecoCompleto(): Attribute
    {
        return Attribute::get(function () {
            $linha = "{$this->rua}, {$this->numero}";

            if (filled($this->complemento)) {
                $linha .= ", {$this->complemento}";
            }

            $linha .= " - {$this->bairro}, {$this->cidade}";

            $cep = (string) $this->cep;
            if (strlen($cep) === 8) {
                $cep = substr($cep, 0, 5).'-'.substr($cep, 5);
            }

            return $cep === '' ? $linha : "{$linha} - CEP {$cep}";
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }
}
