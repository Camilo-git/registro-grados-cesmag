<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduates', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();               // llave del sistema (no editable)
            $t->string('full_name_list');                // nombre tal como viene en el listado
            $t->string('first_names')->nullable();       // los completa el estudiante
            $t->string('last_names')->nullable();
            $t->string('cedula', 10)->nullable()->unique(); // varios NULL permitidos
            $t->date('birth_date')->nullable();
            $t->string('phone', 15)->nullable();
            $t->string('address')->nullable();
            $t->foreignId('program_id')->constrained();
            $t->foreignId('ceremony_id')->nullable()->constrained();
            $t->unsignedTinyInteger('guest_quota_override')->nullable(); // excepción individual (2 o 3)
            $t->timestamp('profile_confirmed_at')->nullable();
            $t->timestamp('attended_at')->nullable();
            $t->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('last_import_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $t->timestamp('removed_from_list_at')->nullable(); // ya no aparece en el listado: revisar
            $t->timestamps();

            $t->index(['ceremony_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduates');
    }
};
