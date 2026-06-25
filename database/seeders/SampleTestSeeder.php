<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Test;

class SampleTestSeeder extends Seeder
{
    public function run(): void
    {
        if (Test::where('code', 'KMSIA-SAMPLE')->exists()) return;

        $t = Test::create([
            'code' => 'KMSIA-SAMPLE', 'room' => 'worker',
            'title_easy' => '직장인 마음상태 검사(샘플)', 'title_pro' => 'KMSIA Sample',
            'target' => '만 19세 이상 성인', 'duration_min' => 5, 'item_count' => 10,
            'areas' => ['스트레스','우울','불안','회복탄력성'],
            'result_type' => 'signal',
            'description' => '최근 2주간 나의 마음상태를 신호등으로 확인하는 샘플 검사입니다.',
            'status' => 'active', 'thumbnail' => 'images/tests/kmsia-sample.png',
        ]);

        $items = [
            ['스트레스', '요즘 사소한 일에도 쉽게 짜증이 난다', false],
            ['스트레스', '해야 할 일이 너무 많아 압도되는 느낌이다', false],
            ['우울', '최근 일상에서 즐거움을 느끼기 어렵다', false],
            ['우울', '이유 없이 기운이 없고 무기력하다', false],
            ['우울', '나 자신이 쓸모없다고 느껴질 때가 있다', false],
            ['불안', '특별한 이유 없이 긴장되거나 초조하다', false],
            ['불안', '걱정이 꼬리를 물어 잠들기 어렵다', false],
            ['회복탄력성', '힘든 일이 있어도 곧 회복하는 편이다', true],
            ['회복탄력성', '어려움을 성장의 기회로 받아들인다', true],
            ['회복탄력성', '주변에 기댈 사람이 있다고 느낀다', true],
        ];
        foreach ($items as $i => [$area, $text, $rev]) {
            $t->items()->create(['no' => $i + 1, 'text' => $text, 'type' => 'likert5', 'reverse' => $rev, 'area' => $area]);
        }

        $t->scoringRule()->create(['rules' => [
            'areas' => [
                '스트레스' => ['yellow' => 6, 'red' => 8],
                '우울' => ['yellow' => 9, 'red' => 12],
                '불안' => ['yellow' => 6, 'red' => 8],
                '회복탄력성' => ['yellow' => 10, 'red' => 13], // 역채점 합산이 높을수록(=원응답 낮을수록) 위험
            ],
            'interpretation' => [
                'green' => '전반적으로 안정적인 마음상태입니다. 지금의 균형을 유지해 보세요.',
                'yellow' => '관심과 조기지원이 필요한 단계입니다. 가벼운 자기돌봄을 시작해 보세요.',
                'red' => '적극적 지원이 필요한 단계입니다. 전문가 상담을 권장합니다.',
            ],
            'recommendations' => [
                'green' => ['마음건강 유지 루틴 만들기'],
                'yellow' => ['스트레스 관리 4주 프로그램', '수면·휴식 점검'],
                'red' => ['전문가 1:1 상담 연결', '번아웃 리셋 코칭'],
            ],
        ]]);
    }
}
