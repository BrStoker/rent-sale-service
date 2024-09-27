<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        // Проверяем, есть ли транзакции, связанные с продуктом
        static::deleting(function ($product) {
            if ($product->transactions()->exists()) {
                throw new \Exception('Невозможно удалить товар, поскольку с ним связаны транзакции.');
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
