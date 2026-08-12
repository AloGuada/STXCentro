<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La obra como la ve Calidad, ligada a la obra del portal.
     *
     * Se conserva como entidad propia en vez de sustituirla por `obras`:
     * Calidad necesita dar de alta lo suyo sin esperar a que la obra esté
     * completa en el core, y las etapas cuelgan de aquí.
     *
     * Con `obra_id` puesto, el cliente, el contrato y el lugar se leen de la
     * obra del portal. No se copian: duplicarlos es garantizar que dentro de
     * seis meses no coincidan.
     *
     * `responsable_calidad` y `pnd_nota` sí son propios. La nota deja escrito de
     * dónde sale el número de ensayos comprometidos —«10% de las juntas de
     * penetración completa, cláusula 7.3»— y evita la discusión de dentro de
     * seis meses sobre si eran 60 u 80.
     */
    public function up(): void
    {
        Schema::create('qal_obras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->string('no');
            $table->string('descripcion')->nullable();
            $table->string('responsable_calidad')->nullable();
            $table->text('pnd_nota')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_obras');
    }
};
