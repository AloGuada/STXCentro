<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_generadoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->string('titulo');
            $table->integer('orden')->default(0);
            // Lock de edición estricto (App\Models\Concerns\HasEditLock).
            $table->foreignUuid('locked_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_generadoras');
    }
};
