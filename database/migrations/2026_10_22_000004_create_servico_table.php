<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servico', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->string('categoria', 50);
            $table->integer('duracao_minutos');
            $table->decimal('preco_base', 10, 2);
            $table->boolean('ativo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servico');
    }
};
