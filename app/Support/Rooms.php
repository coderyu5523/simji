<?php
namespace App\Support;

class Rooms
{
    public static function all(): array
    {
        return [
            ['code' => 'univ',   'name' => '대학생',     'desc' => '진로와 관계 사이에서 나를 찾는 시기', 'tags' => ['우울','불안','스트레스','진로','대인관계','자기조절']],
            ['code' => 'worker', 'name' => '직장인·성인', 'desc' => '회복과 성과 사이, 단단한 마음 만들기', 'tags' => ['번아웃','직무스트레스','분노','회복탄력성','마음상태']],
            ['code' => 'silver', 'name' => '실버',       'desc' => '존엄과 활력의 시기, 마음을 돌보기',   'tags' => ['우울','고독감','인지건강','삶의만족도']],
        ];
    }

    public static function find(string $code): ?array
    {
        foreach (self::all() as $r) { if ($r['code'] === $code) return $r; }
        return null;
    }
}
