<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TestResult extends Model
{
    protected $guarded = [];
    protected $casts = ['area_scores' => 'array', 'area_signals' => 'array', 'recommendations' => 'array'];
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'attempt_id'); }
}
