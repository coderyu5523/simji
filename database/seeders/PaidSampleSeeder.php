<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{Test, Product};

class PaidSampleSeeder extends Seeder
{
    public function run(): void
    {
        if (Test::where('code','KPAID-SAMPLE')->exists()) return;
        $t = Test::create([
            'code'=>'KPAID-SAMPLE','room'=>'univ',
            'title_easy'=>'대학생 마음상태 검사(유료 샘플)','title_pro'=>'KPAID Sample',
            'target'=>'대학생','duration_min'=>5,'item_count'=>10,
            'areas'=>['스트레스','우울','불안','회복탄력성'],'result_type'=>'signal',
            'description'=>'결제 흐름 시연용 유료 샘플 검사입니다.','status'=>'active',
        ]);
        // 문항은 무료 샘플과 동일 패턴(시연용)
        $items = [
            ['스트레스','요즘 사소한 일에도 쉽게 짜증이 난다',false],
            ['스트레스','해야 할 일이 너무 많아 압도되는 느낌이다',false],
            ['우울','최근 일상에서 즐거움을 느끼기 어렵다',false],
            ['우울','이유 없이 기운이 없고 무기력하다',false],
            ['우울','나 자신이 쓸모없다고 느껴질 때가 있다',false],
            ['불안','특별한 이유 없이 긴장되거나 초조하다',false],
            ['불안','걱정이 꼬리를 물어 잠들기 어렵다',false],
            ['회복탄력성','힘든 일이 있어도 곧 회복하는 편이다',true],
            ['회복탄력성','어려움을 성장의 기회로 받아들인다',true],
            ['회복탄력성','주변에 기댈 사람이 있다고 느낀다',true],
        ];
        foreach ($items as $i => [$area,$text,$rev]) {
            $t->items()->create(['no'=>$i+1,'text'=>$text,'type'=>'likert5','reverse'=>$rev,'area'=>$area]);
        }
        $t->scoringRule()->create(['rules'=>[
            'areas'=>['스트레스'=>['yellow'=>6,'red'=>8],'우울'=>['yellow'=>9,'red'=>12],'불안'=>['yellow'=>6,'red'=>8],'회복탄력성'=>['yellow'=>10,'red'=>13]],
            'interpretation'=>['green'=>'안정적입니다.','yellow'=>'관심이 필요합니다.','red'=>'전문가 상담을 권장합니다.'],
            'recommendations'=>['green'=>['유지 루틴'],'yellow'=>['스트레스 관리 4주'],'red'=>['전문가 상담']],
        ]]);
        Product::create(['test_id'=>$t->id,'name'=>'대학생 마음상태 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    }
}
