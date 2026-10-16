<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticker', 40);
            $table->string('classe', 20);
            $table->decimal('quantidade', 20, 8)->default(0);
            $table->decimal('preco_medio', 18, 6)->default(0);
            $table->decimal('cotacao', 18, 6)->default(0);
            $table->decimal('nota', 3, 1)->default(0);
            $table->json('criterios')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'classe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
