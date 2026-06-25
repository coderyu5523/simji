<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TestItem extends Model
{
    protected $guarded = [];
    protected $casts = ['options' => 'array', 'reverse' => 'boolean'];
    public function test() { return $this->belongsTo(Test::class); }
}
