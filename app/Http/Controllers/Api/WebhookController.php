<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Notification;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');

        try {
            // 2. Buat objek notifikasi
            $notification = new Notification();

            $transactionStatus = $notification->transaction_status;
            $paymentType = $notification->payment_type;
            $orderId = explode('-', $notification->order_id)[0]; // Ambil ID order asli
            $fraudStatus = $notification->fraud_status;

            // 3. Cari order di database Anda
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json(['message' => 'Order not found.'], 404);
            }

            // 4. Update status order berdasarkan notifikasi
            if ($transactionStatus == 'settlement') {
                // TODO: Cek jika status 'capture' (jika pakai kartu kredit)
                if ($fraudStatus == 'accept') {
                    $order->update(['status' => 'paid']);
                }
            } else if ($transactionStatus == 'expire') {
                $order->update(['status' => 'pending']); // atau 'expired'
            } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny') {
                $order->update(['status' => 'pending']); // atau 'cancelled'
            }

            return response()->json(['message' => 'Webhook received.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Webhook error: ' . $e->getMessage()], 400);
        }
    }
}
