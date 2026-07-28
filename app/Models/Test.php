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

    /** 이 연령이 법정대리인 동의 대상인가 (PIPA §22-2) */
    public function needsGuardianConsentFor(?int $age): bool
    {
        if ($this->guardian_consent_below_age === null || $age === null) return false;
        return $age < $this->guardian_consent_below_age;
    }
}
