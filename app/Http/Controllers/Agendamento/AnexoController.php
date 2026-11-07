<?php

namespace App\Http\Controllers\Agendamento;

use App\Http\Controllers\Controller;
use App\Models\AgendamentoAnexo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnexoController extends Controller
{
    public function show(AgendamentoAnexo $anexo): StreamedResponse
    {
        $disco = Storage::disk('local');

        abort_unless($disco->exists($anexo->url), 404);

        return $disco->response(
            $anexo->url,
            $anexo->nome_original,
            array_filter(['Content-Type' => $anexo->mime_type]),
            'inline'
        );
    }
}
