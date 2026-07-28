<?php
namespace Database\Seeders\OyMsi;

use App\Models\Test;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    private const SCALES = [
        'GEN_4PT' => ['전혀 그렇지 않다', '가끔 그렇다', '자주 그렇다', '거의 항상 그렇다'],
        'SAF_THOUGHT_4PT' => ['전혀 없었다', '한두 번 있었다', '여러 번 있었다', '자주 있었거나 지금도 그렇다'],
        'SAF_BEHAVIOR_4PT' => ['없었다', '1회 있었다', '2~3회 있었다', '4회 이상 또는 최근 1개월 안에 있었다'],
    ];

    public function run(): void
    {
        if (Test::where('code', 'OY_MSI')->exists()) return;

        $test = Test::create([
            'code' => 'OY_MSI',
            'room' => 'middle',
            'title_easy' => '마음상태검사',
            'title_pro' => '학교 밖 청소년용 마음상태검사',
            'target' => '만 13~18세 청소년',
            'min_age' => 13,
            'max_age' => 18,
            'guardian_consent_below_age' => 14,
            'duration_min' => 15,
            'item_count' => 60,
            'areas' => ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT', 'SAF'],
            'result_type' => 'signal',
            'scoring_engine' => 'oy_msi',
            'assessment_version' => '1.0.1',
            'consent_required' => true,
            'description' => '최근 마음상태와 생활기능을 확인하는 선별검사입니다. 정신질환을 진단하는 검사가 아닙니다.',
            'status' => 'draft', // 1단계는 비공개
            'thumbnail' => null,
        ]);

        foreach ($this->items() as $index => [$code, $factor, $scale, $timeframe, $reverse, $text]) {
            $test->items()->create([
                'no' => $index + 1,
                'item_code' => $code,
                'area' => $factor,
                'type' => 'likert4',
                'scale_code' => $scale,
                'timeframe_code' => $timeframe,
                'options' => self::SCALES[$scale],
                'reverse' => $reverse,
                'text' => $text,
            ]);
        }
    }

    /** @return array<int, array{0:string,1:string,2:string,3:string,4:bool,5:string}> */
    private function items(): array
    {
        $G = 'GEN_4PT'; $T = 'SAF_THOUGHT_4PT'; $B = 'SAF_BEHAVIOR_4PT';
        $W = 'PAST_2_WEEKS'; $Y = 'PAST_12_MONTHS';

        return [
            // ── 사이클 1 (Q001~Q010)
            ['DEP01','DEP',$G,$W,false,'요즘 특별한 이유가 없어도 마음이 가라앉거나 슬프다.'],
            ['LIF01','LIF',$G,$W,false,'자는 시간과 일어나는 시간이 일정하지 않다.'],
            ['ANX01','ANX',$G,$W,false,'별일이 없어도 나쁜 일이 생길 것 같아 걱정된다.'],
            ['ISO01','ISO',$G,$W,false,'내 마음을 솔직하게 이야기할 사람이 없다고 느낀다.'],
            ['FUT01','FUT',$G,$W,false,'앞으로 내 삶이 나아질 가능성이 거의 없다고 느낀다.'],
            ['IMP01','IMP',$G,$W,false,'작은 일에도 쉽게 짜증이 나거나 화가 난다.'],
            ['FAM01','FAM',$G,$W,false,'가족이나 보호자와 이야기하면 갈등이 더 심해지는 경우가 많다.'],
            ['TRM01','TRM',$G,$W,false,'과거의 힘든 일이 갑자기 떠올라 괴로울 때가 있다.'],
            ['RSK01','RSK',$G,$W,false,'스마트폰이나 게임 때문에 해야 할 일을 하지 못한다.'],
            ['SAF01','SAF',$T,$W,false,'차라리 내가 없어지는 것이 낫겠다고 생각한 적이 있다.'],

            // ── 사이클 2 (Q011~Q020)
            ['ANX02','ANX',$G,$W,false,'앞으로 무엇을 해야 할지 생각하면 마음이 답답해진다.'],
            ['FUT02','FUT',$G,$W,false,'내가 무엇을 잘하고 어떤 일을 하고 싶은지 잘 모르겠다.'],
            ['FAM02','FAM',$G,$W,false,'가족이나 보호자가 내 마음과 상황을 이해하지 못한다고 느낀다.'],
            ['DEP02','DEP',$G,$W,false,'예전에 좋아했던 일도 별로 즐겁지 않다.'],
            ['RSK02','RSK',$G,$W,false,'스마트폰이나 게임을 줄이려고 해도 잘되지 않는다.'],
            ['TRM02','TRM',$G,$W,false,'힘든 일을 떠올리게 하는 사람이나 장소를 피한다.'],
            ['LIF02','LIF',$G,$W,false,'밤에 깨어 있고 낮에 자는 생활이 반복된다.'],
            ['SAF02','SAF',$T,$W,false,'내 몸을 일부러 다치게 하고 싶다는 생각이 든 적이 있다.'],
            ['IMP02','IMP',$G,$W,false,'화가 나면 심한 말을 하거나 물건을 던지고 싶어진다.'],
            ['ISO02','ISO',$G,$W,false,'다른 사람을 만나거나 연락하는 것이 부담스럽다.'],

            // ── 사이클 3 (Q021~Q030)  ※ IMP03↔ISO03 교정
            ['IMP03','IMP',$G,$W,false,'감정이 올라오면 내 말과 행동을 멈추기 어렵다.'],
            ['ISO03','ISO',$G,$W,false,'며칠 동안 집이나 방 밖으로 거의 나가지 않을 때가 있다.'],
            ['LIF03','LIF',$G,$W,false,'식사를 거르거나 한꺼번에 많이 먹는 경우가 많다.'],
            ['ANX03','ANX',$G,$W,false,'다른 사람이 나를 어떻게 생각할지 지나치게 신경 쓰인다.'],
            ['TRM03','TRM',$G,$W,false,'작은 소리나 움직임에도 깜짝 놀라거나 긴장한다.'],
            ['SAF03','SAF',$T,$W,false,'스스로 목숨을 끊고 싶다는 생각을 한 적이 있다.'],
            ['RSK03','RSK',$G,$W,false,'온라인 활동을 하지 못하면 불안하거나 짜증이 심해진다.'],
            ['DEP03','DEP',$G,$W,false,'아무것도 시작하고 싶지 않고 가만히 있고 싶다.'],
            ['FUT03','FUT',$G,$W,false,'공부나 일을 시작해도 끝까지 해내지 못할 것 같다.'],
            ['FAM03','FAM',$G,$W,false,'집에서 무시당하거나 모욕적인 말을 듣는 경우가 있다.'],

            // ── 사이클 4 (Q031~Q040)
            ['FUT04','FUT',$G,$W,true, '나에게 맞는 공부나 일을 찾기 위해 새로운 것을 시도할 생각이 있다.'],
            ['DEP04','DEP',$G,$W,false,'내가 쓸모없거나 다른 사람에게 짐이 되는 것처럼 느껴진다.'],
            ['RSK04','RSK',$G,$W,false,'기분을 풀거나 힘든 생각을 잊기 위해 술이나 담배를 사용한다.'],
            ['SAF04','SAF',$T,$W,false,'목숨을 끊을 구체적인 방법, 장소 또는 도구를 생각하거나 준비한 적이 있다.'],
            ['FAM04','FAM',$G,$W,false,'힘든 일이 생겨도 가족이나 보호자에게 도움을 기대하기 어렵다.'],
            ['ANX04','ANX',$G,$W,false,'여러 생각이 계속 떠올라 마음을 편하게 하기 어렵다.'],
            ['ISO04','ISO',$G,$W,false,'다른 사람은 나를 이해하지 못할 것이라고 생각한다.'],
            ['IMP04','IMP',$G,$W,false,'결과를 충분히 생각하지 않고 행동할 때가 있다.'],
            ['LIF04','LIF',$G,$W,false,'씻기, 옷 갈아입기, 방 정리 같은 일상관리가 귀찮고 어렵다.'],
            ['TRM04','TRM',$G,$W,false,'너무 힘들 때 현실이 아닌 것 같거나 멍해질 때가 있다.'],

            // ── 사이클 5 (Q041~Q050)  ※ LIF05↔TRM05 교정 (SAF05는 Q042 고정)
            ['LIF05','LIF',$G,$W,false,'하루 동안 거의 움직이지 않거나 밖에 나가지 않는다.'],
            ['SAF05','SAF',$B,$Y,false,'최근 12개월 동안 내 몸을 일부러 다치게 한 적이 있다.'],
            ['TRM05','TRM',$G,$W,false,'다른 사람이 나를 해치거나 위협할 것 같아 주변을 계속 살핀다.'],
            ['FUT05','FUT',$G,$W,true, '작은 목표를 세우면 조금씩 실천할 수 있다.'],
            ['IMP05','IMP',$G,$W,false,'화가 난 뒤 내가 한 말이나 행동을 후회하는 경우가 많다.'],
            ['DEP05','DEP',$G,$W,false,'충분히 쉬어도 몸과 마음이 지쳐 있다.'],
            ['FAM05','FAM',$G,$W,false,'가족이나 보호자가 나를 때리거나 심하게 위협하는 경우가 있다.'],
            ['ANX05','ANX',$G,$W,false,'긴장하면 심장이 빨리 뛰거나 숨이 답답해질 때가 있다.'],
            ['RSK05','RSK',$G,$W,false,'인터넷 도박, 위험한 거래 또는 큰돈을 잃을 수 있는 활동을 한 적이 있다.'],
            ['ISO05','ISO',$G,$W,false,'상처받을까 봐 사람을 믿거나 가까이하기 어렵다.'],

            // ── 사이클 6 (Q051~Q060)
            ['RSK06','RSK',$G,$W,false,'온라인에서 돈, 개인정보, 사진 또는 영상을 보내라는 압박을 받은 적이 있다.'],
            ['ISO06','ISO',$G,$W,false,'힘들어도 다른 사람에게 도움을 요청하지 않고 혼자 참는다.'],
            ['DEP06','DEP',$G,$W,false,'앞으로 내 상황이 좋아질 것이라는 생각이 잘 들지 않는다.'],
            ['TRM06','TRM',$G,$W,false,'지금 머무는 곳에서 폭력이나 원하지 않는 신체접촉을 당할까 두렵다.'],
            ['FAM06','FAM',$G,$W,false,'가족과의 갈등 때문에 집을 나가고 싶거나 돌아가기 싫을 때가 있다.'],
            ['ANX06','ANX',$G,$W,false,'새로운 장소에 가거나 낯선 사람을 만나는 것이 두렵다.'],
            ['FUT06','FUT',$G,$W,true, '적절한 도움을 받으면 다시 시작할 수 있다고 생각한다.'],
            ['IMP06','IMP',$G,$W,false,'기분이 나쁘면 위험하거나 무모한 행동을 하고 싶어진다.'],
            ['LIF06','LIF',$G,$W,false,'해야 할 일을 계획하고 정해진 시간에 시작하기 어렵다.'],
            ['SAF06','SAF',$B,$Y,false,'최근 12개월 동안 실제로 목숨을 끊으려는 행동을 한 적이 있다.'],
        ];
    }
}
