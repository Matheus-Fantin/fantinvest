<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('atualizacao_automatica')->default(true);
            $table->decimal('variacao_dia', 9, 4)->nullable();
            $table->timestamp('cotacao_atualizada_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['atualizacao_automatica', 'variacao_dia', 'cotacao_atualizada_em']);
        });
    }
};
