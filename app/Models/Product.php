<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model
{
    protected $guarded = [];
    protected $casts = ['price'=>'integer','credit_qty'=>'integer','valid_days'=>'integer'];
    public function test() { return $this->belongsTo(Test::class); }
}
