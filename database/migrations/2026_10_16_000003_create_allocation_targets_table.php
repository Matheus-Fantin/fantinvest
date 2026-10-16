<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('classe', 20);
            $table->decimal('percentual', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'classe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_targets');
    }
};
