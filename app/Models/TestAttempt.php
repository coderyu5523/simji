<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
class TestAttempt extends Model
{
    protected $guarded = [];
    protected $casts = ['started_at' => 'datetime', 'submitted_at' => 'datetime'];
    public function test() { return $this->belongsTo(Test::class); }
    public function answers() { return $this->hasMany(AttemptAnswer::class, 'attempt_id'); }
    public function result() { return $this->hasOne(TestResult::class, 'attempt_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function isOwnedBy(Request $request): bool
    {
        return $this->user_id
            ? $this->user_id === auth()->id()
            : $this->guest_token === $request->session()->get('guest_token');
    }
    public function voucher() { return $this->belongsTo(Voucher::class); }
    public function consents() { return $this->hasMany(ConsentRecord::class, 'attempt_id'); }
    public function shares() { return $this->hasMany(ReportShare::class, 'attempt_id'); }
}
