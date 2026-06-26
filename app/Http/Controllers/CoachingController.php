<?php
namespace App\Http\Controllers;

class CoachingController extends Controller
{
    public function index()
    {
        $programs = [
            ['room'=>'초등학생',   'name'=>'마음안전 신호등 교실',      'desc'=>'감정표현, 친구관계, 스마트폰 조절', 'type'=>'4회기 집단'],
            ['room'=>'초등 부모',  'name'=>'우리아이 마음 읽기 부모코칭','desc'=>'정서행동 이해, 훈육, 양육스트레스 관리', 'type'=>'2시간 특강/4주 코칭'],
            ['room'=>'중고등학생', 'name'=>'흔들리는 10대 마음근육 훈련','desc'=>'스트레스, 시험불안, 자기조절, 진로', 'type'=>'학교 특강/집단상담'],
            ['room'=>'대학생',     'name'=>'나를 찾는 진로·관계 코칭',   'desc'=>'진로정체감, 대인관계, 자기효능감', 'type'=>'워크숍/1:1 코칭'],
            ['room'=>'직장인',     'name'=>'번아웃 리셋 마음관리',       'desc'=>'스트레스, 분노, 회복탄력성, 소통', 'type'=>'기업특강/EAP'],
            ['room'=>'실버',       'name'=>'다시 피어나는 마음정원',     'desc'=>'고독감, 우울, 회상, 삶의 의미', 'type'=>'복지관 프로그램'],
        ];
        $types = ['특강','집단상담','부모교육','교사연수','1:1 코칭','기관 패키지'];
        return view('coaching.index', compact('programs', 'types'));
    }
}
