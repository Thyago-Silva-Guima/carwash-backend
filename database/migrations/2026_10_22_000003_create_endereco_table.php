<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endereco', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('cliente_id');
            $table->string('rua', 150);
            $table->string('numero', 20);
            $table->string('bairro', 100);
            $table->string('complemento', 100)->nullable();
            $table->string('cidade', 100);
            $table->string('cep', 9);

            $table->foreign('cliente_id', 'fk_endereco_cliente')
                ->references('id')->on('cliente')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endereco');
    }
};
