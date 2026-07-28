// tools/oy-msi-reference/reference.js
//
// 원본: C:\work\심지\청소년_마음상태검사_모바일웹앱_index.html (2,523줄) 에서
// 채점 관련 심볼만 그대로 옮긴 참조 구현. 절차는 ./extract.md 참고.
//
// 계산식(산술)은 원본과 100% 동일하다. 변경한 것은 다음 두 가지뿐:
//   1. DOM 전용 함수(bandLabel/bandSymbol/caseName, 렌더링용)는 옮기지 않았다.
//   2. getAnswer/getSafetyLevel/getEnvironmentLevel 의 기본 인자
//      `answerMap = state.answers` 에서 `= state.answers` 만 제거했다
//      (원본의 state 는 브라우저 전역 상태이며 이 파일에는 존재하지 않는다;
//      항상 answerMap 을 명시적으로 넘기므로 동작은 동일하다).
//   3. getGeneralCode 는 원 브리프의 추출 목록(Step 1)에 빠져 있었으나,
//      "일반 사례코드" 비교(대조 범위)에 반드시 필요해 함께 옮겼다
//      (getFinalCaseCode 는 generalCode 를 인자로만 받고 계산하지 않는다).
// 원본 파일은 수정하지 않았다.

    const ITEMS = [
  {
    "displayOrder": 1,
    "itemId": "DEP01",
    "factor": "DEP",
    "factorName": "우울·무기력",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "요즘 특별한 이유가 없어도 마음이 가라앉거나 슬프다."
  },
  {
    "displayOrder": 2,
    "itemId": "LIF01",
    "factor": "LIF",
    "factorName": "생활리듬·신체기능",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "자는 시간과 일어나는 시간이 일정하지 않다."
  },
  {
    "displayOrder": 3,
    "itemId": "ANX01",
    "factor": "ANX",
    "factorName": "불안·긴장",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "별일이 없어도 나쁜 일이 생길 것 같아 걱정된다."
  },
  {
    "displayOrder": 4,
    "itemId": "ISO01",
    "factor": "ISO",
    "factorName": "고립·대인관계",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "내 마음을 솔직하게 이야기할 사람이 없다고 느낀다."
  },
  {
    "displayOrder": 5,
    "itemId": "FUT01",
    "factor": "FUT",
    "factorName": "미래희망·학업진로 적응",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "앞으로 내 삶이 나아질 가능성이 거의 없다고 느낀다."
  },
  {
    "displayOrder": 6,
    "itemId": "IMP01",
    "factor": "IMP",
    "factorName": "분노·충동조절",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "작은 일에도 쉽게 짜증이 나거나 화가 난다."
  },
  {
    "displayOrder": 7,
    "itemId": "FAM01",
    "factor": "FAM",
    "factorName": "가족·보호환경",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "가족이나 보호자와 이야기하면 갈등이 더 심해지는 경우가 많다."
  },
  {
    "displayOrder": 8,
    "itemId": "TRM01",
    "factor": "TRM",
    "factorName": "외상반응·안전감",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "과거의 힘든 일이 갑자기 떠올라 괴로울 때가 있다."
  },
  {
    "displayOrder": 9,
    "itemId": "RSK01",
    "factor": "RSK",
    "factorName": "디지털·물질·위험행동",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "스마트폰이나 게임 때문에 해야 할 일을 하지 못한다."
  },
  {
    "displayOrder": 10,
    "itemId": "SAF01",
    "factor": "SAF",
    "factorName": "자해·자살 안전",
    "period": "최근 2주",
    "reverse": false,
    "scale": "SAF-T",
    "alert": "즉시평가",
    "text": "차라리 내가 없어지는 것이 낫겠다고 생각한 적이 있다."
  },
  {
    "displayOrder": 11,
    "itemId": "ANX02",
    "factor": "ANX",
    "factorName": "불안·긴장",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "앞으로 무엇을 해야 할지 생각하면 마음이 답답해진다."
  },
  {
    "displayOrder": 12,
    "itemId": "FUT02",
    "factor": "FUT",
    "factorName": "미래희망·학업진로 적응",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "내가 무엇을 잘하고 어떤 일을 하고 싶은지 잘 모르겠다."
  },
  {
    "displayOrder": 13,
    "itemId": "FAM02",
    "factor": "FAM",
    "factorName": "가족·보호환경",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "가족이나 보호자가 내 마음과 상황을 이해하지 못한다고 느낀다."
  },
  {
    "displayOrder": 14,
    "itemId": "DEP02",
    "factor": "DEP",
    "factorName": "우울·무기력",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "예전에 좋아했던 일도 별로 즐겁지 않다."
  },
  {
    "displayOrder": 15,
    "itemId": "RSK02",
    "factor": "RSK",
    "factorName": "디지털·물질·위험행동",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "스마트폰이나 게임을 줄이려고 해도 잘되지 않는다."
  },
  {
    "displayOrder": 16,
    "itemId": "TRM02",
    "factor": "TRM",
    "factorName": "외상반응·안전감",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "힘든 일을 떠올리게 하는 사람이나 장소를 피한다."
  },
  {
    "displayOrder": 17,
    "itemId": "LIF02",
    "factor": "LIF",
    "factorName": "생활리듬·신체기능",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "밤에 깨어 있고 낮에 자는 생활이 반복된다."
  },
  {
    "displayOrder": 18,
    "itemId": "SAF02",
    "factor": "SAF",
    "factorName": "자해·자살 안전",
    "period": "최근 2주",
    "reverse": false,
    "scale": "SAF-T",
    "alert": "즉시평가",
    "text": "내 몸을 일부러 다치게 하고 싶다는 생각이 든 적이 있다."
  },
  {
    "displayOrder": 19,
    "itemId": "IMP02",
    "factor": "IMP",
    "factorName": "분노·충동조절",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "화가 나면 심한 말을 하거나 물건을 던지고 싶어진다."
  },
  {
    "displayOrder": 20,
    "itemId": "ISO02",
    "factor": "ISO",
    "factorName": "고립·대인관계",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "다른 사람을 만나거나 연락하는 것이 부담스럽다."
  },
  {
    "displayOrder": 21,
    "itemId": "ISO03",
    "factor": "ISO",
    "factorName": "고립·대인관계",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "며칠 동안 집이나 방 밖으로 거의 나가지 않을 때가 있다."
  },
  {
    "displayOrder": 22,
    "itemId": "IMP03",
    "factor": "IMP",
    "factorName": "분노·충동조절",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "감정이 올라오면 내 말과 행동을 멈추기 어렵다."
  },
  {
    "displayOrder": 23,
    "itemId": "LIF03",
    "factor": "LIF",
    "factorName": "생활리듬·신체기능",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "식사를 거르거나 한꺼번에 많이 먹는 경우가 많다."
  },
  {
    "displayOrder": 24,
    "itemId": "ANX03",
    "factor": "ANX",
    "factorName": "불안·긴장",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "다른 사람이 나를 어떻게 생각할지 지나치게 신경 쓰인다."
  },
  {
    "displayOrder": 25,
    "itemId": "TRM03",
    "factor": "TRM",
    "factorName": "외상반응·안전감",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "작은 소리나 움직임에도 깜짝 놀라거나 긴장한다."
  },
  {
    "displayOrder": 26,
    "itemId": "SAF03",
    "factor": "SAF",
    "factorName": "자해·자살 안전",
    "period": "최근 2주",
    "reverse": false,
    "scale": "SAF-T",
    "alert": "즉시평가",
    "text": "스스로 목숨을 끊고 싶다는 생각을 한 적이 있다."
  },
  {
    "displayOrder": 27,
    "itemId": "RSK03",
    "factor": "RSK",
    "factorName": "디지털·물질·위험행동",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "온라인 활동을 하지 못하면 불안하거나 짜증이 심해진다."
  },
  {
    "displayOrder": 28,
    "itemId": "DEP03",
    "factor": "DEP",
    "factorName": "우울·무기력",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "아무것도 시작하고 싶지 않고 가만히 있고 싶다."
  },
  {
    "displayOrder": 29,
    "itemId": "FUT03",
    "factor": "FUT",
    "factorName": "미래희망·학업진로 적응",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "공부나 일을 시작해도 끝까지 해내지 못할 것 같다."
  },
  {
    "displayOrder": 30,
    "itemId": "FAM03",
    "factor": "FAM",
    "factorName": "가족·보호환경",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "집에서 무시당하거나 모욕적인 말을 듣는 경우가 있다."
  },
  {
    "displayOrder": 31,
    "itemId": "FUT04",
    "factor": "FUT",
    "factorName": "미래희망·학업진로 적응",
    "period": "최근 2주",
    "reverse": true,
    "scale": "GEN",
    "alert": "-",
    "text": "나에게 맞는 공부나 일을 찾기 위해 새로운 것을 시도할 생각이 있다."
  },
  {
    "displayOrder": 32,
    "itemId": "DEP04",
    "factor": "DEP",
    "factorName": "우울·무기력",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "내가 쓸모없거나 다른 사람에게 짐이 되는 것처럼 느껴진다."
  },
  {
    "displayOrder": 33,
    "itemId": "RSK04",
    "factor": "RSK",
    "factorName": "디지털·물질·위험행동",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "조건경보",
    "text": "기분을 풀거나 힘든 생각을 잊기 위해 술이나 담배를 사용한다."
  },
  {
    "displayOrder": 34,
    "itemId": "SAF04",
    "factor": "SAF",
    "factorName": "자해·자살 안전",
    "period": "최근 2주",
    "reverse": false,
    "scale": "SAF-T",
    "alert": "즉시평가",
    "text": "목숨을 끊을 구체적인 방법, 장소 또는 도구를 생각하거나 준비한 적이 있다."
  },
  {
    "displayOrder": 35,
    "itemId": "FAM04",
    "factor": "FAM",
    "factorName": "가족·보호환경",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "힘든 일이 생겨도 가족이나 보호자에게 도움을 기대하기 어렵다."
  },
  {
    "displayOrder": 36,
    "itemId": "ANX04",
    "factor": "ANX",
    "factorName": "불안·긴장",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "여러 생각이 계속 떠올라 마음을 편하게 하기 어렵다."
  },
  {
    "displayOrder": 37,
    "itemId": "ISO04",
    "factor": "ISO",
    "factorName": "고립·대인관계",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "다른 사람은 나를 이해하지 못할 것이라고 생각한다."
  },
  {
    "displayOrder": 38,
    "itemId": "IMP04",
    "factor": "IMP",
    "factorName": "분노·충동조절",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "결과를 충분히 생각하지 않고 행동할 때가 있다."
  },
  {
    "displayOrder": 39,
    "itemId": "LIF04",
    "factor": "LIF",
    "factorName": "생활리듬·신체기능",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "씻기, 옷 갈아입기, 방 정리 같은 일상관리가 귀찮고 어렵다."
  },
  {
    "displayOrder": 40,
    "itemId": "TRM04",
    "factor": "TRM",
    "factorName": "외상반응·안전감",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "너무 힘들 때 현실이 아닌 것 같거나 멍해질 때가 있다."
  },
  {
    "displayOrder": 41,
    "itemId": "TRM05",
    "factor": "TRM",
    "factorName": "외상반응·안전감",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "다른 사람이 나를 해치거나 위협할 것 같아 주변을 계속 살핀다."
  },
  {
    "displayOrder": 42,
    "itemId": "SAF05",
    "factor": "SAF",
    "factorName": "자해·자살 안전",
    "period": "최근 12개월",
    "reverse": false,
    "scale": "SAF-B",
    "alert": "즉시평가",
    "text": "최근 12개월 동안 내 몸을 일부러 다치게 한 적이 있다."
  },
  {
    "displayOrder": 43,
    "itemId": "LIF05",
    "factor": "LIF",
    "factorName": "생활리듬·신체기능",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "하루 동안 거의 움직이지 않거나 밖에 나가지 않는다."
  },
  {
    "displayOrder": 44,
    "itemId": "FUT05",
    "factor": "FUT",
    "factorName": "미래희망·학업진로 적응",
    "period": "최근 2주",
    "reverse": true,
    "scale": "GEN",
    "alert": "-",
    "text": "작은 목표를 세우면 조금씩 실천할 수 있다."
  },
  {
    "displayOrder": 45,
    "itemId": "IMP05",
    "factor": "IMP",
    "factorName": "분노·충동조절",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "화가 난 뒤 내가 한 말이나 행동을 후회하는 경우가 많다."
  },
  {
    "displayOrder": 46,
    "itemId": "DEP05",
    "factor": "DEP",
    "factorName": "우울·무기력",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "충분히 쉬어도 몸과 마음이 지쳐 있다."
  },
  {
    "displayOrder": 47,
    "itemId": "FAM05",
    "factor": "FAM",
    "factorName": "가족·보호환경",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "조건경보",
    "text": "가족이나 보호자가 나를 때리거나 심하게 위협하는 경우가 있다."
  },
  {
    "displayOrder": 48,
    "itemId": "ANX05",
    "factor": "ANX",
    "factorName": "불안·긴장",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "긴장하면 심장이 빨리 뛰거나 숨이 답답해질 때가 있다."
  },
  {
    "displayOrder": 49,
    "itemId": "RSK05",
    "factor": "RSK",
    "factorName": "디지털·물질·위험행동",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "조건경보",
    "text": "인터넷 도박, 위험한 거래 또는 큰돈을 잃을 수 있는 활동을 한 적이 있다."
  },
  {
    "displayOrder": 50,
    "itemId": "ISO05",
    "factor": "ISO",
    "factorName": "고립·대인관계",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "상처받을까 봐 사람을 믿거나 가까이하기 어렵다."
  },
  {
    "displayOrder": 51,
    "itemId": "RSK06",
    "factor": "RSK",
    "factorName": "디지털·물질·위험행동",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "조건경보",
    "text": "온라인에서 돈, 개인정보, 사진 또는 영상을 보내라는 압박을 받은 적이 있다."
  },
  {
    "displayOrder": 52,
    "itemId": "ISO06",
    "factor": "ISO",
    "factorName": "고립·대인관계",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "힘들어도 다른 사람에게 도움을 요청하지 않고 혼자 참는다."
  },
  {
    "displayOrder": 53,
    "itemId": "DEP06",
    "factor": "DEP",
    "factorName": "우울·무기력",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "앞으로 내 상황이 좋아질 것이라는 생각이 잘 들지 않는다."
  },
  {
    "displayOrder": 54,
    "itemId": "TRM06",
    "factor": "TRM",
    "factorName": "외상반응·안전감",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "조건경보",
    "text": "지금 머무는 곳에서 폭력이나 원하지 않는 신체접촉을 당할까 두렵다."
  },
  {
    "displayOrder": 55,
    "itemId": "FAM06",
    "factor": "FAM",
    "factorName": "가족·보호환경",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "가족과의 갈등 때문에 집을 나가고 싶거나 돌아가기 싫을 때가 있다."
  },
  {
    "displayOrder": 56,
    "itemId": "ANX06",
    "factor": "ANX",
    "factorName": "불안·긴장",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "새로운 장소에 가거나 낯선 사람을 만나는 것이 두렵다."
  },
  {
    "displayOrder": 57,
    "itemId": "FUT06",
    "factor": "FUT",
    "factorName": "미래희망·학업진로 적응",
    "period": "최근 2주",
    "reverse": true,
    "scale": "GEN",
    "alert": "-",
    "text": "적절한 도움을 받으면 다시 시작할 수 있다고 생각한다."
  },
  {
    "displayOrder": 58,
    "itemId": "IMP06",
    "factor": "IMP",
    "factorName": "분노·충동조절",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "기분이 나쁘면 위험하거나 무모한 행동을 하고 싶어진다."
  },
  {
    "displayOrder": 59,
    "itemId": "LIF06",
    "factor": "LIF",
    "factorName": "생활리듬·신체기능",
    "period": "최근 2주",
    "reverse": false,
    "scale": "GEN",
    "alert": "-",
    "text": "해야 할 일을 계획하고 정해진 시간에 시작하기 어렵다."
  },
  {
    "displayOrder": 60,
    "itemId": "SAF06",
    "factor": "SAF",
    "factorName": "자해·자살 안전",
    "period": "최근 12개월",
    "reverse": false,
    "scale": "SAF-B",
    "alert": "즉시평가",
    "text": "최근 12개월 동안 실제로 목숨을 끊으려는 행동을 한 적이 있다."
  }
];
    const FACTOR_META = {
  "DEP": {
    "name": "우울·무기력",
    "icon": "🌧️"
  },
  "ANX": {
    "name": "불안·긴장",
    "icon": "🌬️"
  },
  "IMP": {
    "name": "분노·충동조절",
    "icon": "🔥"
  },
  "TRM": {
    "name": "외상반응·안전감",
    "icon": "🛡️"
  },
  "ISO": {
    "name": "고립·대인관계",
    "icon": "🤝"
  },
  "FAM": {
    "name": "가족·보호환경",
    "icon": "🏠"
  },
  "LIF": {
    "name": "생활리듬·신체기능",
    "icon": "⏰"
  },
  "RSK": {
    "name": "디지털·물질·위험행동",
    "icon": "📱"
  },
  "FUT": {
    "name": "미래희망·학업진로 적응",
    "icon": "🧭"
  }
};
    function bandFromScore(score) {
      if (score <= 5) return "GREEN";
      if (score <= 10) return "YELLOW";
      return "RED";
    }
    function scoreAnswers(answerMap) {
      const factorScores = {};
      Object.keys(FACTOR_META).forEach((code) => {
        factorScores[code] = { raw: 0, count: 0, riskIndex: 0, band: "GREEN" };
      });

      ITEMS.filter((item) => item.factor !== "SAF").forEach((item) => {
        const rawValue = answerMap[item.itemId];
        if (!Number.isInteger(rawValue)) return;
        const scoredValue = item.reverse ? 3 - rawValue : rawValue;
        factorScores[item.factor].raw += scoredValue;
        factorScores[item.factor].count += 1;
      });

      Object.values(factorScores).forEach((factor) => {
        factor.riskIndex = Math.round((factor.raw / 18) * 1000) / 10;
        factor.band = bandFromScore(factor.raw);
      });

      const overallRaw = Object.values(factorScores).reduce((sum, factor) => sum + factor.raw, 0);
      const overallIndex = Math.round((overallRaw / 162) * 1000) / 10;
      const overallBand = overallIndex < 30 ? "GREEN" : overallIndex < 60 ? "YELLOW" : "RED";

      return { factorScores, overallRaw, overallIndex, overallBand };
    }
    function getAnswer(itemId, answerMap) {
      const value = answerMap[itemId];
      return Number.isInteger(value) ? value : 0;
    }

    function getSafetyLevel(answerMap) {
      const s1 = getAnswer("SAF01", answerMap);
      const s2 = getAnswer("SAF02", answerMap);
      const s3 = getAnswer("SAF03", answerMap);
      const s4 = getAnswer("SAF04", answerMap);
      const s5 = getAnswer("SAF05", answerMap);
      const s6 = getAnswer("SAF06", answerMap);

      if (s3 === 3 || s4 >= 2 || s6 >= 1) return "S3";
      if (s1 >= 2 || s2 >= 2 || s3 === 2 || s4 === 1 || s5 >= 2) return "S2";
      if (s1 >= 1 || s2 === 1 || s3 === 1 || s5 === 1) return "S1";
      return "S0";
    }

    function getEnvironmentLevel(answerMap) {
      const trm = getAnswer("TRM06", answerMap);
      const fam = getAnswer("FAM05", answerMap);
      const online = getAnswer("RSK06", answerMap);
      const substance = getAnswer("RSK04", answerMap);
      const gambling = getAnswer("RSK05", answerMap);

      if (trm === 3 || fam === 3 || online === 3) return "E3";
      if (trm === 2 || fam === 2 || online === 2 || substance >= 2 || gambling >= 2) return "E2";
      if (trm === 1 || fam === 1 || online === 1 || gambling === 1) return "E1";
      return "E0";
    }
    function getGeneralCode(factorScores) {
      const factors = Object.values(factorScores);
      const redCount = factors.filter((factor) => factor.band === "RED").length;
      const yellowCount = factors.filter((factor) => factor.band === "YELLOW").length;
      if (redCount >= 2) return "R2";
      if (redCount === 1) return "R1";
      if (yellowCount >= 3) return "Y2";
      if (yellowCount >= 1) return "Y1";
      return "G0";
    }

    function getFinalCaseCode(generalCode, safetyLevel, environmentLevel) {
      const safetyRank = Number(safetyLevel.slice(1));
      const environmentRank = Number(environmentLevel.slice(1));
      const highest = Math.max(safetyRank, environmentRank);
      return highest > 0 ? `C${highest}` : generalCode;
    }
    function priorityFactors(factorScores) {
      const weight = { GREEN: 0, YELLOW: 100, RED: 200 };
      const tieBreak = { DEP: 9, TRM: 8, FAM: 7, RSK: 6, IMP: 5, ISO: 4, LIF: 3, ANX: 2, FUT: 1 };
      return Object.entries(factorScores)
        .map(([code, data]) => ({ code, ...data, priority: weight[data.band] + data.riskIndex + tieBreak[code] / 100 }))
        .sort((a, b) => b.priority - a.priority)
        .slice(0, 3);
    }

// ── 여기부터는 원본에 없는, 테스트 하네스 전용 추가 함수다 (2026-07-28 리뷰 라운드 1 대응) ──
// priorityFactors() 의 `.slice(0, 3)` 은 원본 알고리즘 자체의 일부이므로 손대지 않았다
// (원본 그대로 top-3 만 반환). 하지만 PHP 쪽 대조 테스트가 alert_bonus(007 §9.5) 로 인한
// 순위 변동을 "집합 비교"가 아니라 "완전 도출 가능한 순서 비교"로 검증하려면 9개 요인
// 전체의 JS 상대순서가 필요하다. 정렬 공식(weight+riskIndex+tieBreak)은 priorityFactors 와
// 100% 동일하게 재사용했고 바뀐 것은 slice 를 뺀 것뿐이다 — 즉 이 함수의 산출물은
// priorityFactors(factorScores) 를 3개로 자르기 전 상태와 항상 정확히 같다.
function priorityFactorsFull(factorScores) {
  const weight = { GREEN: 0, YELLOW: 100, RED: 200 };
  const tieBreak = { DEP: 9, TRM: 8, FAM: 7, RSK: 6, IMP: 5, ISO: 4, LIF: 3, ANX: 2, FUT: 1 };
  return Object.entries(factorScores)
    .map(([code, data]) => ({ code, ...data, priority: weight[data.band] + data.riskIndex + tieBreak[code] / 100 }))
    .sort((a, b) => b.priority - a.priority);
}

module.exports = {
  ITEMS, FACTOR_META,
  scoreAnswers, getSafetyLevel, getEnvironmentLevel, getGeneralCode, getFinalCaseCode, priorityFactors,
  priorityFactorsFull,
};
