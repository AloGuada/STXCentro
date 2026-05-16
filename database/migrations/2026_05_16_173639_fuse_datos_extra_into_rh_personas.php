<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const STRING_COLUMNS = [
        'imss', 'curp', 'rfc', 'numero_ine',
        'estado_civil', 'domicilio', 'cp', 'localidad',
        'nombre_padre', 'nombre_madre',
        'cuenta_banco', 'banco_op',
        'c_infonavit', 'c_fonacot',
    ];

    public function up(): void
    {
        Schema::table('rh_personas', function (Blueprint $table) {
            foreach (self::STRING_COLUMNS as $col) {
                $table->string($col)->nullable();
            }
            $table->integer('hijos')->nullable();
            $table->boolean('tramite_banco')->default(false);
            $table->text('texto_cv')->nullable();
        });

        DB::table('rh_datos_extras')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('rh_personas')->where('id', $row->persona_id)->update([
                    'imss' => $row->imss,
                    'curp' => $row->curp,
                    'rfc' => $row->rfc,
                    'numero_ine' => $row->numero_ine,
                    'estado_civil' => $row->estado_civil,
                    'hijos' => $row->hijos,
                    'domicilio' => $row->domicilio,
                    'cp' => $row->cp,
                    'localidad' => $row->localidad,
                    'nombre_padre' => $row->nombre_padre,
                    'nombre_madre' => $row->nombre_madre,
                    'cuenta_banco' => $row->cuenta_banco,
                    'banco_op' => $row->banco_op,
                    'c_infonavit' => $row->c_infonavit,
                    'c_fonacot' => $row->c_fonacot,
                    'tramite_banco' => (bool) $row->tramite_banco,
                    'texto_cv' => $row->texto_cv,
                ]);
            }
        });

        Schema::dropIfExists('rh_datos_extras');
    }

    public function down(): void
    {
        Schema::create('rh_datos_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('rh_personas')->cascadeOnDelete();
            $table->string('estado_civil')->nullable();
            $table->integer('hijos')->nullable();
            $table->string('localidad')->nullable();
            $table->string('domicilio')->nullable();
            $table->string('cp')->nullable();
            $table->string('nombre_padre')->nullable();
            $table->string('nombre_madre')->nullable();
            $table->string('cuenta_banco')->nullable();
            $table->string('c_infonavit')->nullable();
            $table->string('c_fonacot')->nullable();
            $table->string('imss')->nullable();
            $table->string('curp')->nullable();
            $table->string('rfc')->nullable();
            $table->string('numero_ine')->nullable();
            $table->string('banco_op')->nullable();
            $table->boolean('tramite_banco')->default(false);
            $table->text('texto_cv')->nullable();
            $table->timestamps();
        });

        DB::table('rh_personas')
            ->where(function ($q) {
                $q->whereNotNull('curp')
                    ->orWhereNotNull('imss')
                    ->orWhereNotNull('rfc')
                    ->orWhereNotNull('domicilio');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('rh_datos_extras')->insert([
                        'persona_id' => $row->id,
                        'imss' => $row->imss,
                        'curp' => $row->curp,
                        'rfc' => $row->rfc,
                        'numero_ine' => $row->numero_ine,
                        'estado_civil' => $row->estado_civil,
                        'hijos' => $row->hijos,
                        'domicilio' => $row->domicilio,
                        'cp' => $row->cp,
                        'localidad' => $row->localidad,
                        'nombre_padre' => $row->nombre_padre,
                        'nombre_madre' => $row->nombre_madre,
                        'cuenta_banco' => $row->cuenta_banco,
                        'banco_op' => $row->banco_op,
                        'c_infonavit' => $row->c_infonavit,
                        'c_fonacot' => $row->c_fonacot,
                        'tramite_banco' => $row->tramite_banco,
                        'texto_cv' => $row->texto_cv,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->dropColumn([
                'imss', 'curp', 'rfc', 'numero_ine',
                'estado_civil', 'hijos', 'domicilio', 'cp', 'localidad',
                'nombre_padre', 'nombre_madre',
                'cuenta_banco', 'banco_op',
                'c_infonavit', 'c_fonacot',
                'tramite_banco', 'texto_cv',
            ]);
        });
    }
};
