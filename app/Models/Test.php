<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Test extends Model
{
    protected $guarded = [];
    protected $casts = [
        'areas' => 'array', 'requires_guardian_consent' => 'boolean',
        'min_age' => 'integer', 'max_age' => 'integer',
        'guardian_consent_below_age' => 'integer',
        'consent_required' => 'boolean',
    ];
    public function items() { return $this->hasMany(TestItem::class)->orderBy('no'); }
    public function scoringRule() { return $this->hasOne(ScoringRule::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function activeProduct(): ?Product {
        return $this->products()->where('status','active')->orderBy('price')->first();
    }
    public function isPaid(): bool { return $this->activeProduct() !== null; }

    /**
     * 응시 전 만 나이 확인이 필요한 검사인가.
     * 연령에 따라 법정대리인 동의 필요 여부가 갈리는 검사 = 나이를 모르면 판단 자체가 불가능하다.
     */
    public function requiresAgeVerification(): bool
    {
        return $this->guardian_consent_below_age !== null;
    }

    /** 이 연령이 법정대리인 동의 대상인가 (PIPA §22-2) */
    public function needsGuardianConsentFor(?int $age): bool
    {
        if ($this->guardian_consent_below_age === null || $age === null) return false;
        return $age < $this->guardian_consent_below_age;
    }
}
