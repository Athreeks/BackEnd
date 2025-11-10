<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // 1. Diperlukan untuk mengelola file (gambar)

class ProductController extends Controller
{
    /**
     * Menampilkan semua produk.
     * (Dipanggil oleh: GET /api/products)
     */
    public function index()
    {
        // Ambil semua produk, 'image_url' akan otomatis ditambahkan oleh Model
        $products = Product::all();
        return response()->json($products);
    }

    /**
     * Menyimpan produk baru (beserta gambar).
     * (Dipanggil oleh: POST /api/products)
     * * CATATAN: Frontend harus mengirim ini sebagai 'form-data'
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|integer',
            'category' => 'required|in:salad,aksesoris',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Validasi gambar
        ]);

        $productData = $validatedData;

        if ($request->hasFile('image')) {
            // 2. ❗️ Simpan gambar ke 'storage/app/public/products'
            //    dan simpan path-nya (mis: 'products/namafile.jpg')
            $path = $request->file('image')->store('products', 'public');
            $productData['image_path'] = $path;
        }

        $product = Product::create($productData);
        return response()->json($product, 201);
    }

    /**
     * Menampilkan satu produk.
     * (Dipanggil oleh: GET /api/products/{id})
     */
    public function show(Product $product)
    {
        // Model-Route Binding akan otomatis menemukan produk berdasarkan ID
        return response()->json($product);
    }

    /**
     * Memperbarui produk (termasuk mengganti gambar).
     * (Dipanggil oleh: POST /api/products/{id} dengan _method=PUT)
     * * CATATAN: Frontend harus mengirim ini sebagai 'form-data'
     */
    public function update(Request $request, Product $product)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|integer',
            'category' => 'sometimes|in:salad,aksesoris',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Gambar opsional
        ]);

        $productData = $validatedData;

        if ($request->hasFile('image')) {
            // 1. Hapus gambar lama jika ada
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            // 2. ❗️ Upload gambar baru
            $path = $request->file('image')->store('products', 'public');
            $productData['image_path'] = $path;
        }

        $product->update($productData);
        return response()->json($product);
    }

    /**
     * Menghapus produk (dan gambarnya).
     * (Dipanggil oleh: DELETE /api/products/{id})
     */
    public function destroy(Product $product)
    {
        // 1. Hapus gambar dari storage sebelum menghapus produk
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        // 2. Hapus produk dari database
        $product->delete();

        return response()->json(null, 204); // 204 No Content
    }
}
