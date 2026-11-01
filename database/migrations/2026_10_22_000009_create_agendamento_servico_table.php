<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamento_servico', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('agendamento_id');
            $table->integer('servico_id');
            $table->integer('quantidade')->default(1);
            $table->decimal('preco_unitario', 10, 2);

            $table->primary(['agendamento_id', 'servico_id']);

            $table->foreign('agendamento_id', 'fk_agendamento_servico_agendamento')
                ->references('id')->on('agendamento')
                ->cascadeOnDelete();
            $table->foreign('servico_id', 'fk_agendamento_servico_servico')
                ->references('id')->on('servico')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamento_servico');
    }
};
