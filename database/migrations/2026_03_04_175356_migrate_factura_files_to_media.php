<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        // Move files from local to public disk
        $facturas = DB::table('costos_facturas')
            ->where(function ($q) {
                $q->whereNotNull('ruta_xml')->orWhereNotNull('ruta_pdf');
            })
            ->get(['id', 'ruta_xml', 'ruta_pdf']);

        foreach ($facturas as $factura) {
            foreach (['ruta_xml', 'ruta_pdf'] as $column) {
                if ($factura->{$column} && Storage::disk('local')->exists($factura->{$column})) {
                    $content = Storage::disk('local')->get($factura->{$column});
                    Storage::disk('public')->put($factura->{$column}, $content);
                }
            }
        }

        // Create media records
        foreach ($facturas as $factura) {
            if ($factura->ruta_xml) {
                DB::table('media')->insert([
                    'descripcion' => 'xml',
                    'path' => $factura->ruta_xml,
                    'mime' => 'text/xml',
                    'mediable_type' => 'App\\Models\\Costos\\Factura',
                    'mediable_id' => $factura->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($factura->ruta_pdf) {
                DB::table('media')->insert([
                    'descripcion' => 'pdf',
                    'path' => $factura->ruta_pdf,
                    'mime' => 'application/pdf',
                    'mediable_type' => 'App\\Models\\Costos\\Factura',
                    'mediable_id' => $factura->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropColumn(['ruta_xml', 'ruta_pdf']);
        });
    }

    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->string('ruta_xml')->nullable();
            $table->string('ruta_pdf')->nullable();
        });

        $mediaRecords = DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Factura')
            ->get();

        foreach ($mediaRecords as $media) {
            $column = $media->descripcion === 'xml' ? 'ruta_xml' : 'ruta_pdf';
            DB::table('costos_facturas')
                ->where('id', $media->mediable_id)
                ->update([$column => $media->path]);
        }

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Factura')
            ->delete();
    }
};
