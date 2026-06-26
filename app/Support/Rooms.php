<?php
namespace App\Support;

class Rooms
{
    public static function all(): array
    {
        return [
            [
                'code' => 'elem',
                'name' => '초등학생',
                'desc' => '안전하고 밝은 방, 마음을 살피는 첫걸음',
                'tags' => ['불안','우울','주의집중','또래관계','스마트폰'],
                'planned_tests' => [
                    ['name'=>'마음안전선별검사', 'target'=>'부모·교사용', 'guardian'=>true],
                    ['name'=>'정서행동검사',     'target'=>'부모·교사용', 'guardian'=>true],
                    ['name'=>'사회성검사',       'target'=>'부모·교사용', 'guardian'=>true],
                    ['name'=>'주의집중검사',     'target'=>'부모·교사용', 'guardian'=>true],
                    ['name'=>'스마트폰 사용검사','target'=>'부모·교사용', 'guardian'=>true],
                ],
            ],
            [
                'code' => 'middle',
                'name' => '중고등학생',
                'desc' => '진로와 감정 사이, 흔들리는 마음 다잡기',
                'tags' => ['스트레스','시험불안','진로','대인관계','자기조절'],
                'planned_tests' => [
                    ['name'=>'청소년 마음상태검사','target'=>'학생 본인','guardian'=>false],
                    ['name'=>'학업스트레스검사',  'target'=>'학생 본인','guardian'=>false],
                    ['name'=>'시험불안검사',      'target'=>'학생 본인','guardian'=>false],
                    ['name'=>'진로성향검사',      'target'=>'학생 본인','guardian'=>false],
                    ['name'=>'대인관계검사',      'target'=>'학생 본인','guardian'=>false],
                ],
            ],
            [
                'code' => 'univ',
                'name' => '대학생',
                'desc' => '진로와 관계 사이에서 나를 찾는 시기',
                'tags' => ['우울','불안','스트레스','진로','대인관계','자기조절'],
                'planned_tests' => [
                    ['name'=>'우울·불안·스트레스검사','target'=>'본인','guardian'=>false],
                    ['name'=>'진로정체감검사',       'target'=>'본인','guardian'=>false],
                    ['name'=>'대인관계검사',         'target'=>'본인','guardian'=>false],
                    ['name'=>'자기조절검사',         'target'=>'본인','guardian'=>false],
                ],
            ],
            [
                'code' => 'worker',
                'name' => '직장인·성인',
                'desc' => '회복과 성과 사이, 단단한 마음 만들기',
                'tags' => ['번아웃','직무스트레스','분노','회복탄력성','마음상태'],
                'planned_tests' => [
                    ['name'=>'번아웃검사',     'target'=>'본인','guardian'=>false],
                    ['name'=>'직무스트레스검사','target'=>'본인','guardian'=>false],
                    ['name'=>'분노조절검사',   'target'=>'본인','guardian'=>false],
                    ['name'=>'회복탄력성검사', 'target'=>'본인','guardian'=>false],
                ],
            ],
            [
                'code' => 'silver',
                'name' => '실버',
                'desc' => '존엄과 활력의 시기, 마음을 돌보기',
                'tags' => ['우울','고독감','인지건강','삶의만족도'],
                'planned_tests' => [
                    ['name'=>'실버 마음상태검사', 'target'=>'본인·가족','guardian'=>false],
                    ['name'=>'우울·고독감검사',   'target'=>'본인·가족','guardian'=>false],
                    ['name'=>'인지건강 선별검사', 'target'=>'본인·가족','guardian'=>false],
                    ['name'=>'삶의만족도검사',    'target'=>'본인·가족','guardian'=>false],
                ],
            ],
        ];
    }

    public static function find(string $code): ?array
    {
        foreach (self::all() as $r) { if ($r['code'] === $code) return $r; }
        return null;
    }
}
