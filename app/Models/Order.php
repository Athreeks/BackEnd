<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // ❗️ PERBARUI FILLABLE
    protected $fillable = [
        'user_id',
        'grand_total', // 👈 Diubah
        'status',
        'alamat_pengiriman',
        'pg_transaction_id',
        'qris_data_url',
    ];

    // Relasi ke User (Tetap)
    public function user() {
        return $this->belongsTo(User::class);
    }

    // ❗️ HAPUS relasi product()

    // ❗️ TAMBAHKAN relasi items()
    public function items() {
        return $this->hasMany(OrderItem::class);
    }
}
