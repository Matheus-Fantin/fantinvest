<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('banco', 40)->default('Caixa');
            $table->decimal('valor_imovel', 15, 2)->default(0);
            $table->decimal('valor_financiado', 15, 2);
            $table->decimal('recursos_proprios', 15, 2)->default(0);
            $table->decimal('taxa_juros_anual', 8, 4);
            $table->string('sistema', 10)->default('sac'); // sac | price
            $table->unsignedSmallInteger('prazo_meses');
            $table->date('data_contrato')->nullable();
            $table->date('primeira_parcela');
            $table->decimal('seguro_mensal', 10, 2)->default(0);
            $table->decimal('taxa_administracao', 10, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('financing_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_id')->constrained()->cascadeOnDelete();
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->string('descricao', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('financing_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_id')->constrained()->cascadeOnDelete();
            $table->date('data');
            $table->string('descricao', 80);
            $table->string('categoria', 30);
            $table->string('fornecedor', 60)->nullable();
            $table->decimal('valor', 15, 2);
            $table->string('origem', 15)->default('financiamento'); // financiamento | proprio
            $table->timestamps();
        });

        Schema::create('financing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 15); // parcela | amortizacao
            $table->unsignedSmallInteger('numero')->nullable();
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->string('reduzir', 10)->nullable(); // amortização extra: prazo | parcela
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_payments');
        Schema::dropIfExists('financing_expenses');
        Schema::dropIfExists('financing_releases');
        Schema::dropIfExists('financings');
    }
};
