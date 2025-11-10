<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem; // 👈 TAMBAHKAN
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\CoreApi;

class OrderController extends Controller
{
    /**
     * ❗️ LOGIKA DIAMBIL SEMUA PESANAN (INDEX)
     * Sekarang mengambil relasi 'items' dan 'items.product'
     */
    public function index()
    {
        $user = Auth::user();
        $query = Order::with(['user', 'items', 'items.product']); // 👈 DIUBAH

        if ($user->role === 'admin') {
            $orders = $query->latest()->get();
        } else {
            $orders = $query->where('user_id', $user->id)->latest()->get();
        }

        return response()->json($orders);
    }

    /**
     * ❗️ LOGIKA GET ACTIVE ORDERS
     */
    public function getActiveOrders()
    {
        $user = Auth::user();
        // Ambil 'pending' atau 'paid'
        $query = Order::whereIn('status', ['pending', 'paid'])
                        ->with(['user', 'items', 'items.product']); // 👈 DIUBAH

        if ($user->role === 'admin') {
            $orders = $query->latest()->get();
        } else {
            $orders = $query->where('user_id', $user->id)->latest()->get();
        }
        return response()->json($orders);
    }

    /**
     * ❗️ LOGIKA GET HISTORY ORDERS
     */
    public function getHistoryOrders()
    {
        $user = Auth::user();
        $query = Order::where('status', 'completed')
                        ->with(['user', 'items', 'items.product']); // 👈 DIUBAH

        if ($user->role === 'admin') {
            $orders = $query->latest()->get();
        } else {
            $orders = $query->where('user_id', $user->id)->latest()->get();
        }
        return response()->json($orders);
    }


    /**
     * ❗️ LOGIKA CHECKOUT DITULIS ULANG TOTAL
     * Sekarang membuat 1 Order dan banyak OrderItem
     */
    public function checkout(Request $request)
    {
        // 1. Validasi
        $validatedData = $request->validate([
            'cart_ids' => 'required|array',
            'cart_ids.*' => 'exists:carts,id',
            'alamat_pengiriman' => 'nullable|string',
        ]);

        $user = Auth::user();
        $cartIds = $validatedData['cart_ids'];
        $alamat = $validatedData['alamat_pengiriman'] ?? null;

        // 2. Ambil item keranjang & hitung grand total
        $cartItems = Cart::where('user_id', $user->id)
                        ->whereIn('id', $cartIds)
                        ->with('product')
                        ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Item keranjang tidak ditemukan.'], 400);
        }

        $grandTotal = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        // 3. Konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = true;
        Config::$is3ds = false;

        // 4. Buat 1 ID Transaksi unik
        $transactionId = 'ORDER-' . $user->id . '-' . time();
        $transactionParams = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $transactionId,
                'gross_amount' => $grandTotal,
            ],
            'customer_details' => [ 'first_name' => $user->name, 'email' => $user->email, ],
        ];

        try {
            // 5. Kirim request ke Midtrans
            $payment = CoreApi::charge($transactionParams);
            $qrisUrl = $payment->actions[0]->url;
            $pgTransactionId = $payment->transaction_id;

            // 6. Mulai Database Transaction
            DB::beginTransaction();

            // 7. ❗️ BUAT SATU ORDER TUNGGAL
            $order = Order::create([
                'user_id' => $user->id,
                'grand_total' => $grandTotal,
                'status' => 'pending',
                'alamat_pengiriman' => $alamat,
                'pg_transaction_id' => $pgTransactionId,
                'qris_data_url' => $qrisUrl,
            ]);

            // 8. ❗️ MASUKKAN SEMUA BARANG KE ORDER_ITEMS
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id, // 👈 ID dari order yg baru dibuat
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price, // Simpan harga saat itu
                ]);
            }

            // 9. Hapus item dari keranjang
            Cart::where('user_id', $user->id)->whereIn('id', $cartIds)->delete();

            // 10. Konfirmasi transaksi DB
            DB::commit();

            // 11. Kembalikan URL QRIS dan total ke frontend
            return response()->json([
                'message' => 'Checkout berhasil!',
                'order_id' => $order->id, // Kirim ID order baru
                'qris_url' => $qrisUrl,
                'total' => $grandTotal,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat pembayaran.', 'error' => $e->getMessage()], 500);
        }
    }


    // --- METHOD LAIN-LAIN (TETAP SAMA) ---

    /**
     * Update pesanan (Hanya status oleh Admin).
     */
    public function update(Request $request, Order $order)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak. Hanya untuk Admin.'], 403);
        }
        $request->validate(['status' => 'required|in:pending,paid,completed']);
        $order->update(['status' => $request->status]);
        return response()->json(['message' => 'Status pesanan berhasil diperbarui!', 'order' => $order]);
    }

    /**
     * Menghapus/Membatalkan pesanan.
     */
    public function destroy(Order $order)
    {
        if (Auth::id() !== $order->user_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
        $order->delete();
        return response()->json(['message' => 'Pesanan berhasil dibatalkan'], 200);
    }

    /**
     * Menampilkan detail satu pesanan.
     */
    public function show(Order $order)
    {
        if (Auth::id() !== $order->user_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
        // ❗️ Perbarui relasi yang diambil
        return response()->json($order->load(['user', 'items', 'items.product']));
    }

    // ❗️ HAPUS method store() LAMA (jika masih ada)
}
