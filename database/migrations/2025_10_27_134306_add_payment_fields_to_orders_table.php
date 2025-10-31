<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // ID unik dari payment gateway (cth: Midtrans)
            $table->string('pg_transaction_id')->nullable()->after('status');
            // URL atau data string untuk QRIS
            $table->text('qris_data_url')->nullable()->after('pg_transaction_id');
        });
    }

    public function down(): void // Jangan lupa isi fungsi down
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pg_transaction_id', 'qris_data_url']);
        });
    }
};
