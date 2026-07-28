// tools/oy-msi-reference/generate-cases.js
//
// 무작위 응답 3000건을 생성해 참조(JS) 구현으로 채점하고, PHP 엔진과 대조할
// 고정 fixture(tests/fixtures/oy-msi-reference-cases.json)를 만든다.
//
// 재현성: mulberry32 PRNG + 고정 seed(20260727). Math.random() 은 쓰지 않는다.
//
// Run: node generate-cases.js   (cwd 무관 — __dirname 기준으로 경로를 계산한다)

const fs = require('fs');
const path = require('path');
const ref = require('./reference.js');

// 재현 가능한 난수 (mulberry32)
function rng(seed) {
  return function () {
    seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const rand = rng(20260727);
const CASE_COUNT = 3000;
const cases = [];

for (let n = 0; n < CASE_COUNT; n++) {
  const answers = {};
  for (const item of ref.ITEMS) {
    answers[item.itemId] = Math.floor(rand() * 4); // 0~3
  }

  const scored = ref.scoreAnswers(answers);
  const generalCode = ref.getGeneralCode(scored.factorScores);

  cases.push({
    answers,
    expected: {
      factors: scored.factorScores,       // {code: {raw, count, riskIndex, band}}
      overall_index: scored.overallIndex,
      general_case_code: generalCode,
      environment_level: ref.getEnvironmentLevel(answers),
      // 9개 요인 전체의 JS 상대순서(top-3 로 자르지 않음). alert_bonus(007 §9.5) 가
      // "해당 요인에만" 붙는 PHP 쪽과 대조할 때, 경보 요인은 이 순서 그대로 앞으로
      // 당기고 나머지는 이 순서 그대로 뒤에 둔 것이 PHP 의 정확한 기대값이 된다
      // (ReferenceParityTest.php 참고). top-3 로 미리 잘라두면 이 재구성이 불가능하다.
      priority_full: ref.priorityFactorsFull(scored.factorScores).map((f) => f.code),
      // 참고용 — 대조하지 않음 (안전등급은 003 기준을 채택해 의도적으로 JS 007 기준과 다르다)
      js_safety_level: ref.getSafetyLevel(answers),
    },
  });
}

const outPath = path.join(__dirname, '..', '..', 'tests', 'fixtures', 'oy-msi-reference-cases.json');
fs.writeFileSync(outPath, JSON.stringify(cases, null, 0));
console.log(`generated ${cases.length} cases -> ${outPath}`);
