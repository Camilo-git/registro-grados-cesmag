<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ceremonies', function (Blueprint $t) {
            $t->id();
            $t->string('label');                 // como viene en el listado, p. ej. "10:00 a. m."
            $t->string('key')->unique();         // label normalizado
            $t->timestamp('starts_at')->nullable();   // el admin completa la fecha real
            $t->unsignedInteger('capacity')->nullable();
            $t->unsignedTinyInteger('max_guests')->default(2);
            $t->timestamp('registration_opens_at')->nullable();
            $t->timestamp('registration_closes_at')->nullable();
            $t->timestamps();
        });

        DB::statement('ALTER TABLE ceremonies ADD CONSTRAINT ceremonies_max_guests_check CHECK (max_guests BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ceremonies');
    }
};
