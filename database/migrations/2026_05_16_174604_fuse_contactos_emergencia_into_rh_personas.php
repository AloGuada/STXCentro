<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_personas', function (Blueprint $table) {
            $table->string('contacto_emergencia_1_nombre')->nullable();
            $table->string('contacto_emergencia_1_telefono')->nullable();
            $table->string('contacto_emergencia_2_nombre')->nullable();
            $table->string('contacto_emergencia_2_telefono')->nullable();
        });

        $personaIds = DB::table('rh_contactos_emergencia')
            ->select('persona_id')
            ->distinct()
            ->pluck('persona_id');

        foreach ($personaIds as $personaId) {
            $contactos = DB::table('rh_contactos_emergencia')
                ->where('persona_id', $personaId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(2)
                ->get();

            $update = [];
            if (isset($contactos[0])) {
                $update['contacto_emergencia_1_nombre'] = $contactos[0]->nombre;
                $update['contacto_emergencia_1_telefono'] = $contactos[0]->telefono;
            }
            if (isset($contactos[1])) {
                $update['contacto_emergencia_2_nombre'] = $contactos[1]->nombre;
                $update['contacto_emergencia_2_telefono'] = $contactos[1]->telefono;
            }

            if ($update !== []) {
                DB::table('rh_personas')->where('id', $personaId)->update($update);
            }
        }

        Schema::dropIfExists('rh_contactos_emergencia');
    }

    public function down(): void
    {
        Schema::create('rh_contactos_emergencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('rh_personas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('telefono');
            $table->timestamps();
        });

        DB::table('rh_personas')
            ->where(function ($q) {
                $q->whereNotNull('contacto_emergencia_1_nombre')
                    ->orWhereNotNull('contacto_emergencia_2_nombre');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->contacto_emergencia_1_nombre) {
                        DB::table('rh_contactos_emergencia')->insert([
                            'persona_id' => $row->id,
                            'nombre' => $row->contacto_emergencia_1_nombre,
                            'telefono' => $row->contacto_emergencia_1_telefono ?? '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    if ($row->contacto_emergencia_2_nombre) {
                        DB::table('rh_contactos_emergencia')->insert([
                            'persona_id' => $row->id,
                            'nombre' => $row->contacto_emergencia_2_nombre,
                            'telefono' => $row->contacto_emergencia_2_telefono ?? '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->dropColumn([
                'contacto_emergencia_1_nombre',
                'contacto_emergencia_1_telefono',
                'contacto_emergencia_2_nombre',
                'contacto_emergencia_2_telefono',
            ]);
        });
    }
};
