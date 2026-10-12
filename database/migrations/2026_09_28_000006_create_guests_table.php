<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('graduate_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('slot');              // 1..3
            $t->string('first_names');
            $t->string('last_names');
            $t->string('cedula', 10)->unique();           // una persona = invitado de un solo graduado
            $t->date('birth_date');                       // mayoría de edad se valida en la app
            $t->string('phone', 15);
            $t->string('address')->nullable();
            $t->boolean('has_disability')->default(false);
            $t->string('disability_description')->nullable();
            $t->boolean('added_at_door')->default(false); // agregado en la fila de ingreso
            $t->string('door_note')->nullable();
            $t->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('attended_at')->nullable();
            $t->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['graduate_id', 'slot']);
        });

        DB::statement('ALTER TABLE guests ADD CONSTRAINT guests_slot_check CHECK (slot BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
