<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AttemptAnswer extends Model
{
    protected $guarded = [];
    public function item() { return $this->belongsTo(TestItem::class, 'test_item_id'); }
}
