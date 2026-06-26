<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model
{
    protected $guarded = [];
    protected $casts = ['unit_price'=>'integer','quantity'=>'integer','credit_qty'=>'integer','valid_days'=>'integer'];
    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
}
