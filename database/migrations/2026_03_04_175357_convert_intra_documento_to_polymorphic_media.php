<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set mediable_type and mediable_id on existing Media records linked via FK
        $documentos = DB::table('intra_documentos')
            ->whereNotNull('media_id')
            ->get(['id', 'media_id']);

        foreach ($documentos as $doc) {
            DB::table('media')
                ->where('id', $doc->media_id)
                ->update([
                    'mediable_type' => 'App\\Models\\Intra\\Documento',
                    'mediable_id' => $doc->id,
                ]);
        }

        Schema::table('intra_documentos', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
            $table->dropColumn('media_id');
        });
    }

    public function down(): void
    {
        Schema::table('intra_documentos', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->constrained('media');
        });

        $mediaRecords = DB::table('media')
            ->where('mediable_type', 'App\\Models\\Intra\\Documento')
            ->get(['id', 'mediable_id']);

        foreach ($mediaRecords as $media) {
            DB::table('intra_documentos')
                ->where('id', $media->mediable_id)
                ->update(['media_id' => $media->id]);

            DB::table('media')
                ->where('id', $media->id)
                ->update([
                    'mediable_type' => null,
                    'mediable_id' => null,
                ]);
        }
    }
};
