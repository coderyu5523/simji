<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    protected $guarded = [];
    protected $casts = ['amount'=>'integer','paid_at'=>'datetime','raw_response'=>'array'];
    public function order() { return $this->belongsTo(Order::class); }
}
