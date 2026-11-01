<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilidade', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->integer('capacidade')->default(1);
            $table->string('observacao', 255)->nullable();
            $table->integer('usuario_id')->nullable();

            $table->foreign('usuario_id', 'fk_disponibilidade_usuario')
                ->references('id')->on('usuario')
                ->nullOnDelete();

            $table->index('data', 'idx_disponibilidade_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidade');
    }
};
