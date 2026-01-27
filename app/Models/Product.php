<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{

    use HasFactory, SoftDeletes;

    protected $appends = ['formatted_name'];
    // fillable attributes
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'sku',
        'is_active',
        'image',
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }
    // is stock available
    public function inStock()
    {
        return $this->stock > 0;
    }

    protected static function booted()
    {
        static::addGlobalScope('active', function ($q) {
            $user = Auth::user();
            if (!$user || $user->type !== 'admin') {
                $q->where('is_active', true);
            }
        });
    }

    public function getFormattedNameAttribute()
    {
        return ucwords($this->name);
    }


    // searching by name
    public function scopeSearch($q, $term) {
        if ($term) {
            $q->where(function($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                ->orWhere('description', 'like', '%' . $term . '%')
                ->orWhere('sku', 'like', '%' . $term . '%');
            });
        }
    }

    // filtering by is_active
    public function scopeActive ($q, $status) {
        if (isset($status)) {
            $q->where('is_active', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }
    }

    // filtering by price
    public function scopePriceBetween ($q, $min, $max) {
        if ($min) $q->where('price', '>=', $min);
        if ($max) $q->where('price', '<=', $max);
    }

    //get image url
    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }
}
