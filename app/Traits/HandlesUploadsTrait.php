<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesUploadsTrait
{
    protected function storePublicFile (
        UploadedFile $file,
        string $dir,
        string $filename,
        array &$storedPaths
    ) : string {
        $path = $file->storeAs($dir, $filename, 'public');
        $storedPaths[] = $path;
        return $path;
    }

    protected function cleanStoredFiles(array $storedPaths) : void {
        foreach ($storedPaths as $path) {
            if (!empty($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
