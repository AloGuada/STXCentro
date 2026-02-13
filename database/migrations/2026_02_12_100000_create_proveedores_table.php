<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('rfc')->unique();
            $table->text('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->boolean('tiene_acceso_portal')->default(false);
            $table->boolean('maneja_credito')->default(false);
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->integer('dias_credito_default')->default(0);
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->string('tipo_proveedor')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
