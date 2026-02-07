<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sti_ticket_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('sti_tickets')->cascadeOnDelete();
            $table->text('comentario');
            $table->string('autor');
            $table->enum('tipo', ['usuario', 'tecnico'])->default('tecnico');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_ticket_comentarios');
    }
};
