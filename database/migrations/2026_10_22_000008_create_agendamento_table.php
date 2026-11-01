<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamento', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('cliente_id');
            $table->integer('endereco_id');
            $table->dateTime('data_hora');
            $table->dateTime('data_hora_fim');
            $table->string('status', 20)->default('pendente');
            $table->decimal('valor_total', 10, 2)->nullable();
            $table->integer('usuario_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->string('google_event_id', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('cliente_id', 'fk_agendamento_cliente')
                ->references('id')->on('cliente')
                ->restrictOnDelete();
            $table->foreign('endereco_id', 'fk_agendamento_endereco')
                ->references('id')->on('endereco')
                ->restrictOnDelete();

            $table->foreign('usuario_id', 'fk_agendamento_usuario')
                ->references('id')->on('usuario')
                ->nullOnDelete();

            $table->index('data_hora', 'idx_agendamento_data_hora');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE agendamento ADD CONSTRAINT chk_agendamento_status "
                . "CHECK (status IN ('pendente','confirmado','concluido','cancelado'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamento');
    }
};
