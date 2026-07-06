<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::latest()->get();
        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        // Only admin, manager, super-admin may create products
        if (!Auth::user()?->hasAnyRole(['admin', 'manager', 'super-admin'])) {
            abort(403, 'Unauthorized: insufficient role to create products');
        }

        $data = $request->validate([
            'name' => 'required|string|unique:products',
            'sku' => 'required|string|unique:products',
            'price_eur' => 'required|numeric',
            'price_usd' => 'required|numeric',
            'stock_level' => 'integer',
            'spec_processor' => 'nullable|string',
            'spec_ram' => 'nullable|string',
            'spec_storage' => 'nullable|string',
        ]);

        $product = Product::create($data);

        ProductAuditLog::create([
            'product_sku' => $product->sku,
            'user_name' => Auth::user()?->name ?? 'System',
            'action' => 'Product created',
            'old_value' => 'None',
            'new_value' => "Price: EUR {$product->price_eur} / USD {$product->price_usd}",
        ]);

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        // Only admin, manager, super-admin may update products
        if (!Auth::user()?->hasAnyRole(['admin', 'manager', 'super-admin'])) {
            abort(403, 'Unauthorized: insufficient role to update products');
        }

        $data = $request->validate([
            'price_eur' => 'numeric',
            'price_usd' => 'numeric',
            'stock_level' => 'integer',
            'spec_processor' => 'nullable|string',
            'spec_ram' => 'nullable|string',
            'spec_storage' => 'nullable|string',
        ]);

        $originalPriceEur = $product->price_eur;
        $originalPriceUsd = $product->price_usd;
        $originalStock = $product->stock_level;

        $product->update($data);

        $userName = Auth::user()?->name ?? 'System';

        // Check if price or stock changed and log audit events
        if (isset($data['price_eur']) && floatval($data['price_eur']) !== floatval($originalPriceEur)) {
            ProductAuditLog::create([
                'product_sku' => $product->sku,
                'user_name' => $userName,
                'action' => 'Price updated (EUR)',
                'old_value' => '€' . number_format($originalPriceEur, 2),
                'new_value' => '€' . number_format($product->price_eur, 2),
            ]);
        }

        if (isset($data['price_usd']) && floatval($data['price_usd']) !== floatval($originalPriceUsd)) {
            ProductAuditLog::create([
                'product_sku' => $product->sku,
                'user_name' => $userName,
                'action' => 'Price updated (USD)',
                'old_value' => '$' . number_format($originalPriceUsd, 2),
                'new_value' => '$' . number_format($product->price_usd, 2),
            ]);
        }

        if (isset($data['stock_level']) && intval($data['stock_level']) !== intval($originalStock)) {
            ProductAuditLog::create([
                'product_sku' => $product->sku,
                'user_name' => $userName,
                'action' => 'Stock level adjusted',
                'old_value' => (string) $originalStock,
                'new_value' => (string) $product->stock_level,
            ]);
        }

        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        // Only admin and super-admin may delete products
        if (!Auth::user()?->hasAnyRole(['admin', 'super-admin'])) {
            abort(403, 'Unauthorized: insufficient role to delete products');
        }

        $sku = $product->sku;
        $product->delete();

        ProductAuditLog::create([
            'product_sku' => $sku,
            'user_name' => Auth::user()?->name ?? 'System',
            'action' => 'Product deleted',
            'old_value' => $sku,
            'new_value' => 'Deleted',
        ]);

        return response()->json(['success' => true]);
    }

    public function auditLogs(): JsonResponse
    {
        $logs = ProductAuditLog::latest()->take(50)->get();
        return response()->json($logs);
    }
}
