<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Voucher extends Model
{
    protected $guarded = [];
    protected $casts = ['issued_at'=>'datetime','expires_at'=>'datetime','used_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function test() { return $this->belongsTo(Test::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'used_attempt_id'); }
}
