<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Test extends Model
{
    protected $guarded = [];
    protected $casts = ['areas' => 'array', 'requires_guardian_consent' => 'boolean'];
    public function items() { return $this->hasMany(TestItem::class)->orderBy('no'); }
    public function scoringRule() { return $this->hasOne(ScoringRule::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function activeProduct(): ?Product {
        return $this->products()->where('status','active')->orderBy('price')->first();
    }
    public function isPaid(): bool { return $this->activeProduct() !== null; }
}
