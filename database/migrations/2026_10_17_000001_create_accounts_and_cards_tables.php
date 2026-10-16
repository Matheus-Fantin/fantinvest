<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 40);
            $table->string('tipo', 20); // corrente | poupanca | corretora | cripto | dinheiro
            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->boolean('arquivada')->default(false);
            $table->timestamps();
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 40);
            $table->decimal('limite', 15, 2)->default(0);
            $table->unsignedTinyInteger('dia_fechamento');
            $table->unsignedTinyInteger('dia_vencimento');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $table->char('fatura', 7)->nullable(); // "2026-11" = fatura que vence em novembro
            $table->string('parcelamento_id', 36)->nullable()->index();
            $table->unsignedSmallInteger('parcela')->nullable();
            $table->unsignedSmallInteger('parcelas_total')->nullable();
            $table->string('external_id', 120)->nullable(); // FITID do OFX, evita importar duas vezes
        });

        // Movimentos que não são receita nem gasto: transferência entre contas e pagamento de fatura.
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('de_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('para_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('fatura', 7)->nullable();
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->string('descricao', 80)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropConstrainedForeignId('card_id');
            $table->dropColumn(['fatura', 'parcelamento_id', 'parcela', 'parcelas_total', 'external_id']);
        });
        Schema::dropIfExists('cards');
        Schema::dropIfExists('accounts');
    }
};
