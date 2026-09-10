<?php

namespace Database\Seeders;

use App\Models\Cal\PiezaPlano;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CalMoveFilesToPublicSeeder extends Seeder
{
    /**
     * Move cal files from private to public disk and fix DB paths.
     *
     * Original (swapi): disco public, paths: planos/x.pdf, plano_normal/x.pdf, dwg/x.dwg
     * Broken (mono):    disco local (private), paths: cal/planos/{id}/x.pdf
     */
    public function run(): void
    {
        $planos = PiezaPlano::all();
        $moved = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($planos as $plano) {
            $this->moveFile($plano, 'pdf_path', 'planos', $moved, $skipped, $notFound);
            $this->moveFile($plano, 'plano_normal', 'plano_normal', $moved, $skipped, $notFound);
            $this->moveFile($plano, 'dwg_path', 'dwg', $moved, $skipped, $notFound);

            if ($plano->isDirty()) {
                $plano->save();
            }
        }

        $this->command->info("Moved: {$moved} | Skipped: {$skipped} | Not found: {$notFound}");
    }

    private function moveFile(PiezaPlano $plano, string $field, string $targetDir, int &$moved, int &$skipped, int &$notFound): void
    {
        $currentPath = $plano->{$field};

        if (! $currentPath) {
            return;
        }

        // Already correct: no cal/ prefix and exists in public
        if (! str_contains($currentPath, 'cal/') && Storage::disk('public')->exists($currentPath)) {
            $skipped++;

            return;
        }

        $filename = basename($currentPath);
        $newPath = $targetDir.'/'.$filename;

        // Try private disk first (Storage facade)
        if (Storage::disk('local')->exists($currentPath)) {
            Storage::disk('public')->makeDirectory($targetDir);
            Storage::disk('public')->put($newPath, Storage::disk('local')->get($currentPath));
            Storage::disk('local')->delete($currentPath);
            $plano->{$field} = $newPath;
            $moved++;
            $this->command->line("Moved (storage): {$currentPath} -> {$newPath}");

            return;
        }

        // Fallback: try direct filesystem path in case Storage facade has issues
        $privatePath = storage_path('app/private/'.$currentPath);
        $publicPath = storage_path('app/public/'.$newPath);

        if (File::exists($privatePath)) {
            File::ensureDirectoryExists(dirname($publicPath));
            File::move($privatePath, $publicPath);
            $plano->{$field} = $newPath;
            $moved++;
            $this->command->line("Moved (file): {$currentPath} -> {$newPath}");

            return;
        }

        // File in public with cal/ prefix
        if (Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->makeDirectory($targetDir);
            Storage::disk('public')->move($currentPath, $newPath);
            $plano->{$field} = $newPath;
            $moved++;
            $this->command->line("Moved (public): {$currentPath} -> {$newPath}");

            return;
        }

        $this->command->warn("File not found: {$currentPath} (record #{$plano->id})");
        $notFound++;
    }
}
