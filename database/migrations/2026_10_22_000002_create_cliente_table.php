<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('nome', 100);
            $table->string('telefone', 20);
            $table->string('email', 255)->unique();
            $table->string('senha_hash', 255);
            $table->string('tipo', 20)->default('residencial');
            $table->dateTime('aceite_termos_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
