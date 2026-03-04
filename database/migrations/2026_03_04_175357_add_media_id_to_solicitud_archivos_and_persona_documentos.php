<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 3a: SolicitudArchivo - add media_id, migrate data, drop old columns
        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->after('solicitud_id')->constrained('media');
        });

        $archivos = DB::table('costos_solicitud_archivos')
            ->whereNotNull('ruta_archivo')
            ->get(['id', 'ruta_archivo', 'nombre_original']);

        foreach ($archivos as $archivo) {
            $mediaId = DB::table('media')->insertGetId([
                'descripcion' => 'solicitud_archivo',
                'nombre_original' => $archivo->nombre_original,
                'path' => $archivo->ruta_archivo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('costos_solicitud_archivos')
                ->where('id', $archivo->id)
                ->update(['media_id' => $mediaId]);
        }

        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->dropColumn(['ruta_archivo', 'nombre_original']);
        });

        // Phase 3b: PersonaDocumento - add media_id, migrate data, drop old columns
        Schema::table('rh_persona_documentos', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->after('persona_id')->constrained('media');
        });

        $documentos = DB::table('rh_persona_documentos')
            ->whereNotNull('ruta_archivo')
            ->get(['id', 'ruta_archivo', 'nombre_archivo', 'extension', 'tamano']);

        foreach ($documentos as $doc) {
            $mime = match ($doc->extension) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                default => null,
            };

            $mediaId = DB::table('media')->insertGetId([
                'descripcion' => 'persona_documento',
                'nombre_original' => $doc->nombre_archivo,
                'path' => $doc->ruta_archivo,
                'mime' => $mime,
                'size' => $doc->tamano,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rh_persona_documentos')
                ->where('id', $doc->id)
                ->update(['media_id' => $mediaId]);
        }

        Schema::table('rh_persona_documentos', function (Blueprint $table) {
            $table->dropColumn(['ruta_archivo', 'nombre_archivo', 'extension', 'tamano']);
        });
    }

    public function down(): void
    {
        // Restore SolicitudArchivo columns
        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->string('ruta_archivo')->nullable();
            $table->string('nombre_original')->nullable();
        });

        $archivos = DB::table('costos_solicitud_archivos')
            ->whereNotNull('media_id')
            ->get(['id', 'media_id']);

        foreach ($archivos as $archivo) {
            $media = DB::table('media')->find($archivo->media_id);
            if ($media) {
                DB::table('costos_solicitud_archivos')
                    ->where('id', $archivo->id)
                    ->update([
                        'ruta_archivo' => $media->path,
                        'nombre_original' => $media->nombre_original,
                    ]);
                DB::table('media')->where('id', $media->id)->delete();
            }
        }

        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
            $table->dropColumn('media_id');
        });

        // Restore PersonaDocumento columns
        Schema::table('rh_persona_documentos', function (Blueprint $table) {
            $table->string('ruta_archivo')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
        });

        $documentos = DB::table('rh_persona_documentos')
            ->whereNotNull('media_id')
            ->get(['id', 'media_id']);

        foreach ($documentos as $doc) {
            $media = DB::table('media')->find($doc->media_id);
            if ($media) {
                $extension = pathinfo($media->path, PATHINFO_EXTENSION);
                DB::table('rh_persona_documentos')
                    ->where('id', $doc->id)
                    ->update([
                        'ruta_archivo' => $media->path,
                        'nombre_archivo' => $media->nombre_original,
                        'extension' => $extension,
                        'tamano' => $media->size,
                    ]);
                DB::table('media')->where('id', $media->id)->delete();
            }
        }

        Schema::table('rh_persona_documentos', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
            $table->dropColumn('media_id');
        });
    }
};
