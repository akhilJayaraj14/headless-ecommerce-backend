<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    use ApiResponse;

    public function products(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $categorySlug = $request->get('category');
        $search = $request->get('search');
        $cacheKey = "catalog:products:page_{$page}_cat_{$categorySlug}_q_{$search}";

        $products = Cache::remember($cacheKey, 60, function () use ($categorySlug, $search) {
            $query = Product::with(['category', 'brand', 'variants.inventories'])
                ->where('status', 'active');

            if ($categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            }

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            return $query->latest()->paginate(15);
        });

        return $this->successResponse($products);
    }

    public function product(string $slug): JsonResponse
    {
        $cacheKey = "catalog:product:{$slug}";

        $product = Cache::remember($cacheKey, 300, function () use ($slug) {
            return Product::with(['category', 'brand', 'variants.inventories'])
                ->where('slug', $slug)
                ->where('status', 'active')
                ->first();
        });

        if (! $product) {
            return $this->errorResponse('Product not found', 404);
        }

        return $this->successResponse($product);
    }

    public function categories(): JsonResponse
    {
        $categories = Cache::remember('catalog:categories', 600, function () {
            return Category::where('is_active', true)->with('children')->get();
        });

        return $this->successResponse($categories);
    }

    public function brands(): JsonResponse
    {
        $brands = Cache::remember('catalog:brands', 600, function () {
            return Brand::all();
        });

        return $this->successResponse($brands);
    }
}
