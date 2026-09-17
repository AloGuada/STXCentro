<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El .glb de la estructura entera, sin cordones, que el servicio escribe al
     * terminar. Es lo que enseña la pantalla del modelo de un vistazo; los
     * cordones se ven marca por marca.
     */
    public function up(): void
    {
        Schema::table('qal_modelos', function (Blueprint $table) {
            $table->string('archivo_modelo')->nullable()->after('archivo_ifc');
        });
    }

    public function down(): void
    {
        Schema::table('qal_modelos', function (Blueprint $table) {
            $table->dropColumn('archivo_modelo');
        });
    }
};
