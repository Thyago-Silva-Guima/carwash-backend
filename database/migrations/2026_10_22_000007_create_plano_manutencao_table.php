<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plano_manutencao', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('cliente_id');
            $table->integer('pacote_id');
            $table->integer('frequencia_dias');
            $table->date('data_inicio');
            $table->boolean('ativo')->default(true);

            $table->foreign('cliente_id', 'fk_plano_manutencao_cliente')
                ->references('id')->on('cliente')
                ->cascadeOnDelete();
            $table->foreign('pacote_id', 'fk_plano_manutencao_pacote')
                ->references('id')->on('pacote')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plano_manutencao');
    }
};
