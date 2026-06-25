<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TestAttempt extends Model
{
    protected $guarded = [];
    protected $casts = ['started_at' => 'datetime', 'submitted_at' => 'datetime'];
    public function test() { return $this->belongsTo(Test::class); }
    public function answers() { return $this->hasMany(AttemptAnswer::class, 'attempt_id'); }
    public function result() { return $this->hasOne(TestResult::class, 'attempt_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
