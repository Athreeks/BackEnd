<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // 1. Diperlukan untuk membuat URL

class Product extends Model
{
    use HasFactory;

    /**
     * Atribut yang boleh diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'image_path', // 2. Path internal ke file gambar
    ];

    /**
     * Atribut tambahan yang akan ditambahkan ke JSON response.
     *
     * @var array
     */
    protected $appends = [
        'image_url', // 3. Otomatis tambahkan 'image_url' ke JSON
    ];

    /**
     * 4. Accessor untuk membuat atribut 'image_url' secara otomatis.
     * Ini adalah perbaikan untuk masalah gambar Anda.
     */
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            // Gunakan disk 'public' untuk membuat URL yang benar
            return Storage::disk('public')->url($this->image_path);
        }

        // (Opsional) Anda bisa ganti null dengan URL gambar default
        return null;
    }

    /**
     * 5. Relasi ke OrderItem (Struktur database baru).
     * Satu Produk bisa ada di banyak OrderItem.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
