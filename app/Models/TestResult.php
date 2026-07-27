<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TestResult extends Model
{
    protected $guarded = [];
    protected $casts = [
        'area_scores' => 'array', 'area_signals' => 'array',
        'recommendations' => 'array', 'engine_result' => 'array',
    ];
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'attempt_id'); }

    /**
     * PHP의 json_encode() 는 기본적으로 정수값을 갖는 float(예: 9.0)을 정수(9)로
     * 직렬화한다. OyMsiScoringEngine 은 요인 raw 점수를 float 로 채점하므로,
     * JSON_PRESERVE_ZERO_FRACTION 없이는 area_scores/engine_result 를 저장·재조회할
     * 때 9.0 이 9 로 바뀌어 타입이 달라진다 (기존 int 값에는 영향 없음).
     */
    protected function asJson($value, $flags = 0)
    {
        return parent::asJson($value, $flags | JSON_PRESERVE_ZERO_FRACTION);
    }
}
