/**
 * OY_MSI 응시 화면 — 안전 안내 모달 로직.
 *
 * 이 파일은 브라우저에서는 일반 <script> 로 그대로 인라인 실행되고,
 * (resources/views/assessment/take.blade.php 가 file_get_contents 로 이 파일을 읽어
 *  <script> 태그 안에 그대로 넣는다 — 별도 정적 자산 URL이 아니므로 캐시 무효화 문제가 없다)
 * 테스트(tests/js/oymsi-safety-alert.test.js)에서는 module.exports 를 통해
 * computeLevel()/nextAlertState() 를 직접 검증한다. 두 실행 경로 모두 같은 파일 하나이므로
 * "테스트가 통과했는데 실제 화면 코드는 다르다" 는 드리프트가 구조적으로 불가능하다.
 *
 * 서버 권위: 실제 안전등급의 최종 판정은 App\Services\Scoring\OyMsi\SafetyEvaluator (Task 6) 다.
 * 이 스크립트는 화면에서 즉시 안내를 보여주기 위한 것이고, RULES 는
 * database/seeders/OyMsi/ScoringRuleSeeder.php 의 rules()['safety'] 와 값이 동일해야 한다.
 * (tests/js/oymsi-safety-alert.test.js 가 SafetyEvaluatorTest.php 와 같은 fixture 로 등급을 비교해
 *  두 구현이 어긋나지 않는지 고정한다.)
 */
(function (root) {
  'use strict';

  var RULES = [
    { level: 3, conds: [['SAF03', '=', 3], ['SAF04', '>=', 1], ['SAF06', '>=', 1],
                        ['SAF01', '=', 3], ['SAF02', '=', 3], ['SAF05', '>=', 2]] },
    { level: 2, conds: [['SAF01', '=', 2], ['SAF02', '=', 2], ['SAF03', '=', 2]] },
    { level: 1, conds: [['SAF01', '=', 1], ['SAF02', '=', 1], ['SAF03', '=', 1], ['SAF05', '=', 1]] }
  ];

  /** @param {Object<string, number|null>} answers 문항코드 -> 응답값(0-based) | null(응답거부) */
  function computeLevel(answers) {
    for (var i = 0; i < RULES.length; i++) {
      var r = RULES[i];
      for (var j = 0; j < r.conds.length; j++) {
        var c = r.conds[j], v = answers[c[0]];
        if (v === undefined || v === null) continue;
        if (c[1] === '=' ? v === c[2] : v >= c[2]) return r.level;
      }
    }
    // 안전문항 응답거부는 최소 S1 (사용자가 실제로 응답거부를 선택한 문항에 한해서만 —
    // 서버 SafetyEvaluator 는 SAF 6문항 전체 무응답 여부까지 보므로 더 엄격하다.
    // 이 화면 스크립트는 어디까지나 안내용이고, 최종 권위는 서버다.)
    for (var code in answers) {
      if (answers[code] === null) return Math.max(1, 0);
    }
    return 0;
  }

  /**
   * ★ 원본 버그 수정: 억제 키가 "문항 단위"(itemId:value) 가 아니라
   * "이미 보여준 최고 등급"(shownLevel) 이다.
   * 원본은 문항 단위 키라서 한 번 S2 에 도달한 뒤에도 남은 모든 문항에서 모달이 다시 떴다
   * (10번 문항에서 S2 가 되면 이후 11~60번 문항 전부에서 재표시 — 최악 50회).
   *
   * 이 함수는 두 방향을 모두 보장한다:
   *  - 같은 등급이 반복되면(level <= shownLevel) 다시 보여주지 않는다.
   *  - 등급이 shownLevel 보다 올라가면 다시 보여준다.
   *
   * @param {Object<string, number|null>} answers
   * @param {number} shownLevel 지금까지 보여준 안내 중 최고 등급 (0 = 아직 없음)
   * @returns {{level:number, show:boolean, shownLevel:number}}
   */
  function nextAlertState(answers, shownLevel) {
    var level = computeLevel(answers);
    var peak = Math.max(level, shownLevel);

    return {
      level: level,
      // 검사를 멈추는 모달은 레벨 3(자살 계획·준비·시도)에만. 등급이 오를 때 한 번.
      showModal: level >= 3 && level > shownLevel,
      // 레벨 2 는 화면을 가리지 않는 배너로 알린다. 한 번 뜨면 계속 남긴다 —
      // 나중에 답을 낮춰도 이미 드러난 신호를 화면에서 지우지 않는다.
      showBanner: peak >= 2,
      shownLevel: peak,
    };
  }

  function attachSafetyAlert(doc) {
    doc = doc || document;
    var modal = doc.getElementById('safety-modal');
    if (!modal) return;

    var banner = doc.getElementById('safety-banner');
    var answers = {};
    var shownLevel = 0;

    var continueBtn = doc.getElementById('safety-continue');
    continueBtn.addEventListener('click', function () {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    });

    doc.querySelectorAll('.js-answer[data-item-code]').forEach(function (input) {
      input.addEventListener('change', function () {
        var code = input.dataset.itemCode;
        answers[code] = input.value === 'PREFER_NOT' ? null : parseInt(input.value, 10);

        var state = nextAlertState(answers, shownLevel);
        shownLevel = state.shownLevel;

        if (state.showBanner && banner) banner.classList.remove('hidden');

        if (!state.showModal) return;

        doc.getElementById('safety-title').textContent = '지금 바로 도움이 필요해 보입니다';
        doc.getElementById('safety-body').textContent =
          '혼자 있지 말고 지금 전화해 주세요. 위급하면 112나 119에 연락해도 됩니다. 검사는 이어서 하셔도 괜찮습니다.';

        modal.classList.remove('hidden');
        modal.classList.add('flex');
      });
    });
  }

  var api = { computeLevel: computeLevel, nextAlertState: nextAlertState, attachSafetyAlert: attachSafetyAlert };

  if (typeof module !== 'undefined' && module.exports) {
    // Node 테스트 환경: DOM 이 없으므로 attachSafetyAlert 를 자동 호출하지 않는다.
    module.exports = api;
  } else {
    root.OyMsiSafetyAlert = api;
    attachSafetyAlert(document);
  }
})(typeof window !== 'undefined' ? window : globalThis);
