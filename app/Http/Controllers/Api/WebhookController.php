<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Midtrans\Config;
use Midtrans\Notification;

class WebhookController extends Controller
{
    /**
     * Menangani webhook notifikasi dari Midtrans.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request)
    {
        // 1. Konfigurasi Midtrans (harus cocok dengan saat membuat pembayaran)
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');

        try {
            // 2. Buat objek notifikasi dari Midtrans SDK
            // SDK akan otomatis memvalidasi signature key
            $notification = new Notification();

            // 3. Ambil data penting dari notifikasi
            $transactionStatus = $notification->transaction_status;
            $pgTransactionId = $notification->transaction_id; // ID Transaksi dari Midtrans
            $fraudStatus = $notification->fraud_status;

            // 4. Cari SEMUA pesanan di database kita yang cocok dengan ID Transaksi Midtrans
            //    (Karena 1 pembayaran Midtrans bisa untuk banyak order item)
            $orders = Order::where('pg_transaction_id', $pgTransactionId)->get();

            // 5. Jika tidak ada order, kirim 404
            if ($orders->isEmpty()) {
                return response()->json(['message' => 'Orders not found for this transaction.'], 404);
            }

            // 6. Update status pesanan berdasarkan status transaksi
            if ($transactionStatus == 'settlement') {
                // Jika pembayaran sukses
                if ($fraudStatus == 'accept') {
                    // Update SEMUA order terkait menjadi 'paid'
                    $orders->each->update(['status' => 'paid']);
                }
            } else if ($transactionStatus == 'expire') {
                // Jika pembayaran kadaluarsa
                $orders->each->update(['status' => 'pending']); // atau 'expired'
            } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny') {
                // Jika pembayaran dibatalkan atau ditolak
                $orders->each->update(['status' => 'pending']); // atau 'cancelled'
            }

            // 7. Kirim respons 200 OK ke Midtrans untuk mengonfirmasi penerimaan
            return response()->json(['message' => 'Webhook received successfully.'], 200);

        } catch (\Exception $e) {
            // Tangani jika ada error (misal: signature tidak valid)
            return response()->json(['message' => 'Webhook error: ' . $e->getMessage()], 400);
        }
    }
}
