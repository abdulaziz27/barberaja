<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->listServices();

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $this->productService->createService($request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Layanan berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        if ($product->type !== Product::TYPE_SERVICE) {
            abort(404);
        }

        return view('products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->updateService($product, $request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Layanan berhasil diubah.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return redirect()->route('products.index')
            ->with('success', 'Layanan berhasil dihapus.');
    }
}
