<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        // Quem já usa o sistema: a primeira conta criada vira administradora.
        $primeiro = DB::table('users')->min('id');
        if ($primeiro) {
            DB::table('users')->where('id', $primeiro)->update(['is_admin' => true]);
        }

        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 32)->unique();
            $table->string('email')->nullable();
            $table->foreignId('criado_por')->constrained('users')->cascadeOnDelete();
            $table->foreignId('usado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('usado_em')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
