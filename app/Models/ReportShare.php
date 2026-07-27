<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReportShare extends Model
{
    protected $guarded = [];
    protected $casts = [
        'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'viewed_at' => 'datetime',
    ];
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'attempt_id'); }
    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
