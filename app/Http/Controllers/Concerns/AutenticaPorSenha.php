<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait AutenticaPorSenha
{
    /**
     * @param  class-string<Model>  $modelo
     */
    protected function autenticar(Request $request, string $modelo): Model
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $conta = $modelo::where('email', $credenciais['email'])->first();

        $hashFalso = Cache::rememberForever('auth:hash-falso', fn () => Hash::make(Str::random(32)));

        $senhaConfere = Hash::check($credenciais['password'], $conta?->senha_hash ?? $hashFalso);

        if (! $conta || ! $senhaConfere) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos',
            ]);
        }

        return $conta;
    }
}
