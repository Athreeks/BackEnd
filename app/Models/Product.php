<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    
    protected $fillable = ['name', 'description', 'price', 'category'];
}
