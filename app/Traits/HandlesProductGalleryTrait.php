<?php

namespace App\Traits;

use App\Models\Product;
use Illuminate\Support\Str;

trait HandlesProductGalleryTrait
{
    use HandlesUploadsTrait;
    
    protected function addGalleryImages (
        Product $product,
        array $files,
        string $slug,
        int $startSort,
        array &$storedPaths
    ) : void {
        if (empty($files)) {
            return;
        }
        $sort = $startSort;
        foreach ($files as $file) {
            $ext = $file->getClientOriginalExtension();
            $filename = $slug . '_' . Str::random(8) . '.' . $ext;
            $path = $this->storePublicFile(
                $file,
                "products/{$product->id}/gallery",
                $filename,
                $storedPaths
            );

            $product->images()->create([
                'path' => $path,
                'sort' => $sort++,
            ]);
        }
    }
}
