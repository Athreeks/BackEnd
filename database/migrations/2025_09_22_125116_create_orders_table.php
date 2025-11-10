<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // database/migrations/..._create_orders_table.php
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // ❗️ HAPUS product_id, HAPUS quantity

            $table->integer('grand_total'); // ❗️ GANTI total_price menjadi grand_total
            $table->enum('status', ['pending', 'paid', 'completed'])->default('pending');
            $table->text('alamat_pengiriman')->nullable();

            // Info dari Midtrans
            $table->string('pg_transaction_id')->nullable();
            $table->text('qris_data_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
