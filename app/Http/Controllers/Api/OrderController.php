<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Midtrans\Config; // Untuk Payment Gateway
use Midtrans\CoreApi; // Untuk Payment Gateway

class OrderController extends Controller
{
    /**
     * Menampilkan daftar pesanan.
     * Admin melihat semua; Customer hanya melihat miliknya.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            // Jika admin, tampilkan semua pesanan dengan data user & produknya
            $orders = Order::with(['user', 'product'])->latest()->get();
        } else {
            // Jika customer, tampilkan hanya pesanan miliknya
            $orders = Order::where('user_id', $user->id)->with('product')->latest()->get();
        }

        return response()->json($orders);
    }

    /**
     * Menyimpan pesanan baru (langsung, tanpa keranjang).
     * Ini adalah method dari 'apiResource'.
     */
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
            'status' => 'pending', // Status default
        ]);

        // 6. Kembalikan response
        return response()->json([
            'message' => 'Pesanan berhasil dibuat!',
            'order' => $order,
        ], 201);
    }

    /**
     * Menampilkan detail satu pesanan.
     */
    public function show(Order $order)
    {
        // Pastikan customer hanya bisa melihat order miliknya, admin bisa lihat semua
        if (Auth::id() !== $order->user_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        return response()->json($order->load(['user', 'product']));
    }

    /**
     * Update pesanan (Hanya status oleh Admin).
     */
    public function update(Request $request, Order $order)
    {
        // Logika ini HANYA untuk admin (sesuai permintaan Anda sebelumnya)
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

    /**
     * Menghapus/Membatalkan pesanan.
     */
    public function destroy(Order $order)
    {
        // Pastikan hanya user yang bersangkutan atau admin yang bisa menghapus
        if (Auth::id() !== $order->user_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $order->delete();

        return response()->json(['message' => 'Pesanan berhasil dibatalkan'], 200);
    }

    // --- METHOD KUSTOM UNTUK RIWAYAT & PESANAN AKTIF ---

    /**
     * Menampilkan pesanan yang masih aktif (pending/paid).
     */
    public function getActiveOrders()
    {
        $user = Auth::user();
        $query = Order::where('status', '!=', 'completed');

        if ($user->role === 'admin') {
            $orders = $query->with(['user', 'product'])->latest()->get();
        } else {
            $orders = $query->where('user_id', $user->id)->with('product')->latest()->get();
        }

        return response()->json($orders);
    }

    /**
     * Menampilkan riwayat pesanan yang sudah selesai (completed).
     */
    public function getHistoryOrders()
    {
        $user = Auth::user();
        $query = Order::where('status', 'completed');

        if ($user->role === 'admin') {
            $orders = $query->with(['user', 'product'])->latest()->get();
        } else {
            $orders = $query->where('user_id', $user->id)->with('product')->latest()->get();
        }

        return response()->json($orders);
    }

    // --- METHOD KUSTOM UNTUK CHECKOUT & PEMBAYARAN ---

    /**
     * Memproses checkout dari keranjang ke pesanan.
     * Versi ini hanya me-checkout item yang dipilih.
     */
    public function checkout(Request $request)
    {
        // 1. Validasi input: wajib menerima array berisi ID keranjang
        $validatedData = $request->validate([
            'cart_ids' => 'required|array',
            'cart_ids.*' => 'exists:carts,id', // Memastikan setiap ID ada di tabel carts
        ]);

        $user = Auth::user();
        $cartIds = $validatedData['cart_ids'];

        // 2. Ambil HANYA item keranjang yang dipilih dan milik user
        $cartItems = Cart::where('user_id', $user->id)
                        ->whereIn('id', $cartIds) // 'whereIn' adalah kuncinya
                        ->with('product')
                        ->get();

        // 3. Cek jika item yang dipilih tidak ada
        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Item keranjang tidak ditemukan atau bukan milik Anda.'], 400);
        }

        // 4. Mulai Database Transaction
        DB::beginTransaction();
        try {
            $createdOrders = [];
            foreach ($cartItems as $item) {
                // 5. Buat entri pesanan untuk setiap item
                $totalPrice = $item->product->price * $item->quantity;
                $order = Order::create([
                    'user_id' => $user->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'total_price' => $totalPrice,
                    'status' => 'pending',
                ]);
                $createdOrders[] = $order;
            }

            // 6. Hapus HANYA item yang sudah di-checkout dari keranjang
            Cart::where('user_id', $user->id)->whereIn('id', $cartIds)->delete();

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

    /**
     * Membuat request pembayaran QRIS ke Payment Gateway.
     */
    public function createPayment(Request $request, Order $order)
    {
        // 1. Otorisasi: Pastikan user hanya membayar order miliknya
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        // 2. Cek jika order sudah dibayar
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Pesanan ini sudah diproses.'], 422);
        }

        // 3. Konfigurasi Midtrans (Ambil dari .env via config/services.php)
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = true;
        Config::$is3ds = false;

        // 4. Siapkan parameter untuk Midtrans
        $transactionParams = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $order->id . '-' . time(), // Buat ID unik
                'gross_amount' => $order->total_price,
            ],
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ],
        ];

        try {
            // 5. Kirim request ke Midtrans
            $payment = CoreApi::charge($transactionParams);

            // 6. Simpan ID transaksi & QR code URL ke database Anda
            $order->update([
                'pg_transaction_id' => $payment->transaction_id,
                'qris_data_url' => $payment->actions[0]->url, // URL untuk gambar QRIS
            ]);

            // 7. Kirim URL QRIS ke frontend
            return response()->json([
                'message' => 'QRIS berhasil dibuat. Silakan lakukan pembayaran.',
                'qris_url' => $payment->actions[0]->url,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat pembayaran.', 'error' => $e->getMessage()], 500);
        }
    }
}
