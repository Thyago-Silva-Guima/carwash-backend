<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidade', function (Blueprint $table) {
            // INT com sinal, igual às outras tabelas do banco (a chave estrangeira exige o mesmo tipo)
            $table->integer('id', true);
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->integer('capacidade')->default(1);
            $table->string('observacao', 255)->nullable();
            $table->integer('usuario_id')->nullable();

            $table->foreign('usuario_id')->references('id')->on('usuario')->nullOnDelete();
            $table->index('data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidade');
    }
};
