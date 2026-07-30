<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both columns are nullable at the DB level so existing pieces and the
     * bulk CSV import keep working; they are enforced as required on the
     * create/edit form via ConceptoStoreRequest/ConceptoUpdateRequest.
     */
    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->integer('longitud')->nullable()->after('peso_unitario');
            $table->foreignId('categoria_id')->nullable()->after('longitud')->constrained('prod_categorias')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_id');
            $table->dropColumn('longitud');
        });
    }
};
