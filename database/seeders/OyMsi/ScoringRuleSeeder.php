<?php
namespace Database\Seeders\OyMsi;

use App\Models\Test;
use Illuminate\Database\Seeder;

class ScoringRuleSeeder extends Seeder
{
    public function run(): void
    {
        $test = Test::where('code', 'OY_MSI')->firstOrFail();
        if ($test->scoringRule) return;

        $test->scoringRule()->create(['version' => '1.0.0', 'rules' => $this->rules()]);
    }

    private function rules(): array
    {
        return [
            'factors' => [
                'DEP' => ['name' => '우울·무기력',        'included_in_overall' => true,  'tie_break' => 9],
                'TRM' => ['name' => '외상반응·안전감',    'included_in_overall' => true,  'tie_break' => 8],
                'FAM' => ['name' => '가족·보호환경',      'included_in_overall' => true,  'tie_break' => 7],
                'RSK' => ['name' => '디지털·물질·위험행동','included_in_overall' => true,  'tie_break' => 6],
                'IMP' => ['name' => '분노·충동조절',      'included_in_overall' => true,  'tie_break' => 5],
                'ISO' => ['name' => '고립·대인관계',      'included_in_overall' => true,  'tie_break' => 4],
                'LIF' => ['name' => '생활리듬·신체기능',  'included_in_overall' => true,  'tie_break' => 3],
                'ANX' => ['name' => '불안·긴장',          'included_in_overall' => true,  'tie_break' => 2],
                'FUT' => ['name' => '미래희망·학업진로 적응','included_in_overall' => true,'tie_break' => 1],
                'SAF' => ['name' => '자해·자살 안전',     'included_in_overall' => false, 'tie_break' => 0],
            ],

            // 요인 원점수(0~18) 기준. 위험지수 환산과 동치 (raw5=27.8 / raw6=33.3 / raw10=55.6 / raw11=61.1)
            'bands' => [
                'GREEN'  => ['min' => 0,  'max' => 5],
                'YELLOW' => ['min' => 6,  'max' => 10],
                'RED'    => ['min' => 11, 'max' => 18],
            ],

            // 전체 위험지수(0~100) 기준
            'overall_bands' => [
                'GREEN'  => ['min' => 0,    'max' => 29.9],
                'YELLOW' => ['min' => 30,   'max' => 59.9],
                'RED'    => ['min' => 60,   'max' => 100],
            ],

            // 003 예비안 기준 (spec §1.1). 위에서부터 먼저 맞는 등급을 채택한다.
            'safety' => [
                'S3' => [
                    ['item' => 'SAF03', 'op' => '=',  'value' => 3],
                    ['item' => 'SAF04', 'op' => '>=', 'value' => 1],
                    ['item' => 'SAF06', 'op' => '>=', 'value' => 1],
                    ['item' => 'SAF01', 'op' => '=',  'value' => 3],
                    ['item' => 'SAF02', 'op' => '=',  'value' => 3],
                    ['item' => 'SAF05', 'op' => '>=', 'value' => 2],
                ],
                'S2' => [
                    ['item' => 'SAF01', 'op' => '=', 'value' => 2],
                    ['item' => 'SAF02', 'op' => '=', 'value' => 2],
                    ['item' => 'SAF03', 'op' => '=', 'value' => 2],
                ],
                'S1' => [
                    ['item' => 'SAF01', 'op' => '=', 'value' => 1],
                    ['item' => 'SAF02', 'op' => '=', 'value' => 1],
                    ['item' => 'SAF03', 'op' => '=', 'value' => 1],
                    ['item' => 'SAF05', 'op' => '=', 'value' => 1],
                ],
            ],
            // SAF 문항 중 하나라도 무응답이면 최소 S1 (003 / 007 §5.3)
            'safety_missing_min_level' => 'S1',
            'safety_items' => ['SAF01', 'SAF02', 'SAF03', 'SAF04', 'SAF05', 'SAF06'],

            'environment' => [
                'E3' => [
                    ['item' => 'TRM06', 'op' => '=', 'value' => 3],
                    ['item' => 'FAM05', 'op' => '=', 'value' => 3],
                    ['item' => 'RSK06', 'op' => '=', 'value' => 3],
                ],
                'E2' => [
                    ['item' => 'TRM06', 'op' => '=',  'value' => 2],
                    ['item' => 'FAM05', 'op' => '=',  'value' => 2],
                    ['item' => 'RSK06', 'op' => '=',  'value' => 2],
                    ['item' => 'RSK04', 'op' => '>=', 'value' => 2],
                    ['item' => 'RSK05', 'op' => '>=', 'value' => 2],
                ],
                'E1' => [
                    ['item' => 'TRM06', 'op' => '=', 'value' => 1],
                    ['item' => 'FAM05', 'op' => '=', 'value' => 1],
                    ['item' => 'RSK06', 'op' => '=', 'value' => 1],
                    ['item' => 'RSK05', 'op' => '=', 'value' => 1],
                ],
            ],

            'case_codes' => [
                'general' => [
                    ['code' => 'R2', 'when' => ['red_count',    '>=', 2]],
                    ['code' => 'R1', 'when' => ['red_count',    '>=', 1]],
                    ['code' => 'Y2', 'when' => ['yellow_count', '>=', 3]],
                    ['code' => 'Y1', 'when' => ['yellow_count', '>=', 1]],
                    ['code' => 'G0', 'when' => null],
                ],
                'escalation' => [1 => 'C1', 2 => 'C2', 3 => 'C3'],
            ],

            'priority' => [
                'severity_weight' => ['GREEN' => 0, 'YELLOW' => 100, 'RED' => 200],
                'alert_bonus' => 1000,
                'limit' => 3,
            ],

            'strengths' => [
                'TRY_NEW'         => ['item' => 'FUT04', 'op' => '>=', 'value' => 2],
                'SMALL_GOAL'      => ['item' => 'FUT05', 'op' => '>=', 'value' => 2],
                'RECOVERY_HOPE'   => ['item' => 'FUT06', 'op' => '>=', 'value' => 2],
                'HONEST_RESPONSE' => ['always' => true],
            ],

            'solutions' => [
                'SOL_SAF_PLAN'        => ['factor' => 'SAF', 'title' => '개인 안전계획·즉시 연결',   'dedupe_group' => '안전'],
                'SOL_TRM_SAFETY'      => ['factor' => 'TRM', 'title' => '현재 안전확인·감각접지',     'dedupe_group' => '안전'],
                'SOL_DEP_ACTIVATION'  => ['factor' => 'DEP', 'title' => '행동활성화·작은 활동 시작',  'dedupe_group' => '생활회복'],
                'SOL_LIF_7DAY'        => ['factor' => 'LIF', 'title' => '7일 생활리듬 회복',          'dedupe_group' => '생활회복'],
                'SOL_ANX_BREATHING'   => ['factor' => 'ANX', 'title' => '호흡·이완·불안기록',         'dedupe_group' => '정서조절'],
                'SOL_IMP_STOP'        => ['factor' => 'IMP', 'title' => '멈춤-거리두기-재대화',       'dedupe_group' => '충동조절'],
                'SOL_ISO_CONNECT'     => ['factor' => 'ISO', 'title' => '부담 낮은 1인 연결·비대면 상담','dedupe_group' => '관계'],
                'SOL_FAM_PROTECT'     => ['factor' => 'FAM', 'title' => '보호자 안전성 평가·중재',    'dedupe_group' => '가족/보호'],
                'SOL_RSK_DIGITAL'     => ['factor' => 'RSK', 'title' => '디지털·물질·도박 위험 점검', 'dedupe_group' => '위험행동'],
                'SOL_FUT_3MONTH'      => ['factor' => 'FUT', 'title' => '3개월 진로탐색·작은 목표',   'dedupe_group' => '진로'],
            ],

            // 위에서부터 먼저 맞는 것을 채택
            'recheck' => [
                ['days' => 14, 'when_case_in' => ['C1', 'C2', 'C3', 'R1', 'R2'], 'reason' => 'RED_FACTOR_OR_SAFETY'],
                ['days' => 28, 'when_case_in' => ['Y1', 'Y2'], 'reason' => 'YELLOW_FACTOR'],
                ['days' => 90, 'when_case_in' => ['G0'],       'reason' => 'STABLE'],
            ],
        ];
    }
}
