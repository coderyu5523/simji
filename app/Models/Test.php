<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Test extends Model
{
    protected $guarded = [];
    protected $casts = ['areas' => 'array'];
    public function items() { return $this->hasMany(TestItem::class)->orderBy('no'); }
    public function scoringRule() { return $this->hasOne(ScoringRule::class); }
}
