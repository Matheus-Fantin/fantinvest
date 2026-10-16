<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 10); // compra | venda
            $table->date('data');
            $table->decimal('quantidade', 20, 8);
            $table->decimal('preco', 18, 6);
            $table->decimal('taxas', 12, 2)->default(0);
            $table->decimal('resultado', 15, 2)->nullable(); // lucro/prejuízo realizado na venda
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'data']);
        });

        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticker', 40);
            $table->string('tipo', 20); // dividendo | jcp | rendimento
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'data']);
        });

        Schema::create('net_worth_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('mes', 7);
            $table->decimal('ativos', 15, 2);
            $table->decimal('dividas', 15, 2);
            $table->timestamps();

            $table->unique(['user_id', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('net_worth_snapshots');
        Schema::dropIfExists('dividends');
        Schema::dropIfExists('operations');
    }
};
