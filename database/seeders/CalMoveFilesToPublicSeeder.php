<?php

namespace Database\Seeders;

use App\Models\Cal\PiezaPlano;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CalMoveFilesToPublicSeeder extends Seeder
{
    /**
     * Move cal files from private to public disk and fix DB paths.
     *
     * Original (swapi): disco public, paths: planos/x.pdf, plano_normal/x.pdf, dwg/x.dwg
     * Broken (mono):    disco local,  paths: cal/planos/{id}/x.pdf
     */
    public function run(): void
    {
        $planos = PiezaPlano::all();
        $moved = 0;
        $skipped = 0;

        foreach ($planos as $plano) {
            $this->moveFile($plano, 'pdf_path', 'planos', $moved, $skipped);
            $this->moveFile($plano, 'plano_normal', 'plano_normal', $moved, $skipped);
            $this->moveFile($plano, 'dwg_path', 'dwg', $moved, $skipped);

            $plano->save();
        }

        $this->command->info("Moved: {$moved} | Skipped: {$skipped}");
    }

    private function moveFile(PiezaPlano $plano, string $field, string $targetDir, int &$moved, int &$skipped): void
    {
        $currentPath = $plano->{$field};

        if (! $currentPath) {
            return;
        }

        // Already in public with correct path (no cal/ prefix)
        if (! str_contains($currentPath, 'cal/') && Storage::disk('public')->exists($currentPath)) {
            $skipped++;

            return;
        }

        $filename = basename($currentPath);
        $newPath = $targetDir.'/'.$filename;

        // Try to find the file in private disk (local)
        if (Storage::disk('local')->exists($currentPath)) {
            Storage::disk('public')->put($newPath, Storage::disk('local')->get($currentPath));
            Storage::disk('local')->delete($currentPath);
            $plano->{$field} = $newPath;
            $moved++;

            return;
        }

        // File might already be in public with old cal/ path
        if (Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->move($currentPath, $newPath);
            $plano->{$field} = $newPath;
            $moved++;

            return;
        }

        $this->command->warn("File not found: {$currentPath} (record #{$plano->id})");
        $skipped++;
    }
}
