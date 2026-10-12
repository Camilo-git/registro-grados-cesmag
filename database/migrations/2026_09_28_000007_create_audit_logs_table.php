<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('graduate_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event'); // phone_changed, cedula_changed, list_reassigned, door_guest_added, attendance_marked...
            $t->nullableMorphs('auditable');
            $t->jsonb('old_values')->nullable();
            $t->jsonb('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->index(['graduate_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
