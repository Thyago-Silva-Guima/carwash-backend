<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foto', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('agendamento_id');
            $table->string('url', 255);
            $table->string('tipo', 10);

            $table->foreign('agendamento_id', 'fk_foto_agendamento')
                ->references('id')->on('agendamento')
                ->cascadeOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE foto ADD CONSTRAINT chk_foto_tipo CHECK (tipo IN ('antes','depois'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('foto');
    }
};
