<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // OPCIONAL: códigos de un solo uso enviados al correo institucional para el login
    // por cédula o correo. Si decides no usarlos, borra este archivo.
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('graduate_id')->constrained()->cascadeOnDelete();
            $t->string('code_hash');
            $t->timestamp('expires_at');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('consumed_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->index(['graduate_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
