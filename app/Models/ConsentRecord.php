<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ConsentRecord extends Model
{
    protected $guarded = [];
    protected $casts = ['granted' => 'boolean', 'granted_at' => 'datetime', 'meta' => 'array'];
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'attempt_id'); }
}
