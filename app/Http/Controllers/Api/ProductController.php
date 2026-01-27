<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ClearsProductCacheTrait;
use App\Traits\HandlesProductGalleryTrait;
use App\Traits\HandlesUploadsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ClearsProductCacheTrait, HandlesProductGalleryTrait, HandlesUploadsTrait;
    /**
     * Display a listing of the resource with search and pagination.
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->get('per_page', 10), 1), 100);
        $page    = (int) $request->get('page', 1);

        //all parameters can change the results
        $filters = [
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'per_page' => $perPage,
            'page' => $page,
        ];

        ksort($filters);

        $version = Cache::get('products_index_version', 1);
        $cacheKey = 'products_index_v' . $version . '_' . md5(json_encode($filters));

        // create a query builder for products
        $products = Cache::remember($cacheKey, 300, function () use ($request, $perPage) {
            return Product::query()->with('categories')
                ->search($request->search)
                ->active($request->is_active)
                ->priceBetween($request->min_price, $request->max_price)
                ->latest()->paginate($perPage);
        });

        return response()->json([
            'status' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products,
            'links' => [
                'next' => $products->nextPageUrl(),
                'prev' => $products->previousPageUrl(),
            ]
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // store a product
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
            'sku' => 'required|string|max:255|unique:products',
            'is_active' => 'boolean',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data['slug'] = filled($data['slug'] ?? null)
            ? $data['slug']
            : Str::slug($data['name'], '-');
        $storedPaths = [];
        DB::beginTransaction();

        try {

            //check if image uploaded
            if ($request->hasFile('image')) {
                $ext = $request->file('image')->getClientOriginalExtension();
                $filename = $data['slug'] . '.' . $ext;
                $data['image'] = $this->storePublicFile(
                    $request->file('image'),
                    'products',
                    $filename,
                    $storedPaths
                );
            }

            // create a product
            $product = Product::create($data);

            if (!empty($data['categories'])) {
                $product->categories()->sync($data['categories']);
            }
            if ($request->hasFile('gallery')) {
                $this->addGalleryImages(
                    $product,
                    $request->file('gallery'),
                    $data['slug'],
                    0,
                    $storedPaths
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->cleanStoredFiles($storedPaths);
            throw $e;
        }

        $this->clearProductCache($product);

        return response()->json([
            'status' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('categories', 'images'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
        $productCached = Cache::remember("product_{$product->id}", 300, function () use ($product) {
            return $product->load('categories');
        });

        $galleryCached = Cache::remember("product_gallery_{$product->id}", 300, function () use ($product) {
            return $product->images()->orderBy('sort')->get();
        });

        return response()->json([
            'status' => true,
            'message' => 'Product retrieved successfully',
            'data' => $productCached,
            'gallery' => $galleryCached,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        // validate data for update and merge with the existing product
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'is_active' => 'sometimes|boolean',
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:categories,id',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gallery' => 'sometimes|array',
            'gallery.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $storedPaths = [];
        $oldMainImage = $product->image;

        DB::beginTransaction();
        try {
            if ($request->has('name')) {
                $product->name = $request->name;
                $product->slug = Str::slug($request->name, '-');
            }
            if ($request->has('description')) $product->description = $request->description;
            if ($request->has('price')) $product->price = $request->price;
            if ($request->has('stock')) $product->stock = $request->stock;
            if ($request->has('sku')) $product->sku = $request->sku;
            if ($request->has('is_active')) $product->is_active = $request->is_active;

            // check if image uploaded
            if ($request->hasFile('image')) {
                $ext = $request->file('image')->getClientOriginalExtension();
                $filename = $product->slug . '.' . $ext;
                $product->image = $this->storePublicFile(
                    $request->file('image'),
                    'products',
                    $filename,
                    $storedPaths
                );
            }
            // update the product
            $product->save();

            if ($request->hasFile('gallery')) {
                $sort = (int) ($product->images()->max('sort') ?? -1) + 1;

                $this->addGalleryImages(
                    $product,
                    $request->file('gallery'),
                    $product->slug,
                    $sort,
                    $storedPaths
                );
            }

            if (array_key_exists('categories', $data)) {
                $product->categories()->sync($data['categories'] ?? []);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->cleanStoredFiles($storedPaths);
            throw $e;
        }

        // delete old image if exists
        if ($request->hasFile('image') && $oldMainImage && $oldMainImage !== $product->image) {
            \Storage::disk('public')->delete($oldMainImage);
        }

        $this->clearProductCache($product);

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load('categories', 'images'),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        // soft delete
        $product->delete();

        $this->clearProductCache($product);

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully',
            'data' => $product,
        ], 200);
    }

    public function undoDelete(Request $request, $id)
    {
        // undo delete only when admin
        if ($request->user()->hasRole('admin')) {
            $product = Product::withTrashed()->findOrFail($id);
            $product->restore();

            $this->clearProductCache($product);

            return response()->json([
                'success' => true,
                'message' => 'Product restored successfully',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to perform this action',
        ], 403);
    }

    public function permenantDelete(Request $request, $id)
    {
        // Permenant delete
        if ($request->user()->hasRole('admin')) {
            $product = Product::withTrashed()->findOrFail($id);

            if ($product->image) {
                \Storage::disk('public')->delete($product->image);
            }

            // delete gallery images from storage
            $product->images()->select('path')->orderBy('id')->chunk(200, function ($imgs) {
                foreach ($imgs as $img) {
                    \Storage::disk('public')->delete($img->path);
                }
            });

            $product->images()->delete();

            $this->clearProductCache($product);

            $product->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Product permanently deleted successfully'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to perform this action',
        ], 403);
    }

    public function adminIndex(Request $request)
    {
        // for admins only to get trashed products
        if ($request->user()->hasRole('admin')) {
            $products = Product::withTrashed()->paginate();
            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products,
            ], 200);
        }
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to perform this action',
        ], 403);
    }
}
