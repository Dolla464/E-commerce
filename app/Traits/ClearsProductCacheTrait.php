<?php

namespace App\Traits;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

trait ClearsProductCacheTrait
{
    protected function clearProductCache(Product $product): void
    {
        Cache::forget('product_' . $product->id);
        Cache::forget('product_gallery_' . $product->id);
        Cache::add('products_index_version', 1);
        Cache::increment('products_index_version');
    }
}
