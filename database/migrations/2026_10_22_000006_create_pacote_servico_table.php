<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacote_servico', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('pacote_id');
            $table->integer('servico_id');

            $table->primary(['pacote_id', 'servico_id']);

            $table->foreign('pacote_id', 'fk_pacote_servico_pacote')
                ->references('id')->on('pacote')
                ->cascadeOnDelete();
            $table->foreign('servico_id', 'fk_pacote_servico_servico')
                ->references('id')->on('servico')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacote_servico');
    }
};
