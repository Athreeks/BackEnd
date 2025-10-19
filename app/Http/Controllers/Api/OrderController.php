<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            // Jika admin, tampilkan semua pesanan dengan data user & produknya
            $orders = Order::with(['user', 'product'])->get();
        } else {
            // Jika customer, tampilkan hanya pesanan miliknya
            $orders = Order::where('user_id', $user->id)->with('product')->get();
        }

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        // 2. Ambil produk dari database
        $product = Product::find($request->product_id);

        // 3. Ambil user yang sedang login
        $user = Auth::user();

        // 4. Hitung total harga
        $totalPrice = $product->price * $request->quantity;

        // 5. Buat pesanan baru
        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $request->quantity,
            'total_price' => $totalPrice,
            // Status default adalah 'pending' sesuai migrasi
        ]);

        // 6. Kembalikan response
        return response()->json([
            'message' => 'Pesanan berhasil dibuat!',
            'order' => $order,
        ], 201);
    }

    public function show(Order $order)
    {
        // Pastikan customer hanya bisa melihat order miliknya, admin bisa lihat semua
        if (Auth::id() !== $order->user_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        return response()->json($order->load(['user', 'product']));
    }

    public function update(Request $request, Order $order)
    {
        // Logika ini biasanya hanya untuk admin
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak. Hanya untuk Admin.'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,paid,completed',
        ]);

        $order->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui!',
            'order' => $order,
        ]);
    }

    public function destroy(Order $order)
    {
        // Pastikan hanya user yang bersangkutan atau admin yang bisa menghapus
        if (Auth::id() !== $order->user_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $order->delete();

        return response()->json(['message' => 'Pesanan berhasil dibatalkan'], 200);
    }

    public function getActiveOrders()
    {
        $user = Auth::user();
        $query = Order::where('status', '!=', 'completed');

        if ($user->role === 'admin') {
            // Admin melihat semua pesanan aktif
            $orders = $query->with(['user', 'product'])->get();
        } else {
            // Customer hanya melihat pesanan aktif miliknya
            $orders = $query->where('user_id', $user->id)->with('product')->get();
        }

        return response()->json($orders);
    }

    public function getHistoryOrders()
    {
        $user = Auth::user();
        $query = Order::where('status', 'completed');

        if ($user->role === 'admin') {
            // Admin melihat semua riwayat pesanan
            $orders = $query->with(['user', 'product'])->get();
        } else {
            // Customer hanya melihat riwayat pesanan miliknya
            $orders = $query->where('user_id', $user->id)->with('product')->get();
        }

        return response()->json($orders);
    }

    public function checkout(Request $request)
    {
        $user = Auth::user();

        // 1. Ambil semua item keranjang milik user
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

        // 2. Cek jika keranjang kosong
        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Keranjang Anda kosong.'], 400);
        }

        // 3. Gunakan Database Transaction
        // Ini memastikan jika ada satu order gagal, semua order akan dibatalkan
        DB::beginTransaction();
        try {
            $createdOrders = [];
            foreach ($cartItems as $item) {
                // 4. Hitung total harga per item
                $totalPrice = $item->product->price * $item->quantity;

                // 5. Buat entri pesanan baru untuk setiap item
                $order = Order::create([
                    'user_id' => $user->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'total_price' => $totalPrice,
                    'status' => 'pending', // Status default saat checkout
                ]);
                $createdOrders[] = $order;
            }

            // 6. Jika berhasil, hapus semua item dari keranjang
            Cart::where('user_id', $user->id)->delete();

            // 7. Konfirmasi transaksi
            DB::commit();

            return response()->json([
                'message' => 'Checkout berhasil!',
                'orders' => $createdOrders
            ], 201);

        } catch (\Exception $e) {
            // 8. Jika ada error, batalkan semua
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan saat checkout.', 'error' => $e->getMessage()], 500);
        }
    }

}
