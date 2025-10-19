<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Menampilkan semua item di keranjang pengguna.
     */
    public function index()
    {
        $cartItems = Cart::where('user_id', Auth::id())
            ->with('product') // Mengambil info produk terkait
            ->get();

        return response()->json($cartItems);
    }

    /**
     * Menambahkan produk ke keranjang.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        // Cek apakah produk sudah ada di keranjang user
        $cartItem = Cart::where('user_id', Auth::id())
                        ->where('product_id', $request->product_id)
                        ->first();

        if ($cartItem) {
            // Jika sudah ada, tambahkan quantity-nya
            $cartItem->quantity += $request->quantity ?? 1;
            $cartItem->save();
        } else {
            // Jika belum ada, buat item keranjang baru
            $cartItem = Cart::create([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity ?? 1,
            ]);
        }

        return response()->json(['message' => 'Produk ditambahkan ke keranjang!', 'cart' => $cartItem], 201);
    }

    /**
     * Mengubah jumlah (quantity) item di keranjang.
     */
    public function update(Request $request, Cart $cart)
    {
        // Otorisasi: pastikan user hanya bisa update keranjang miliknya
        if ($cart->user_id !== Auth::id()) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate(['quantity' => 'required|integer|min:1']);

        $cart->update(['quantity' => $request->quantity]);

        return response()->json(['message' => 'Jumlah produk diperbarui!', 'cart' => $cart]);
    }

    /**
     * Menghapus item dari keranjang.
     */
    public function destroy(Cart $cart)
    {
        // Otorisasi: pastikan user hanya bisa hapus keranjang miliknya
        if ($cart->user_id !== Auth::id()) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $cart->delete();

        return response()->json(['message' => 'Produk dihapus dari keranjang.'], 200);
    }
}
