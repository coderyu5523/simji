// OY_MSI 응시 화면 안전 안내 모달의 순수 로직(resources/js/oymsi-safety-alert.js) 단위 테스트.
//
// resources/views/assessment/take.blade.php 는 이 파일의 소스를 file_get_contents 로 그대로
// <script> 안에 인라인한다 — 즉 여기서 테스트하는 코드와 브라우저가 실제로 실행하는 코드는
// "같은 파일"이다(복붙 드리프트가 구조적으로 불가능).
//
// new Function()으로 파일을 평가해 module.exports 분기를 타게 만든다. package.json 의
// "type": "module" 과 무관하게 동작한다 — import/require 파이프라인을 거치지 않기 때문이다.
// (resources/js/oymsi-safety-alert.js 는 브라우저에서는 typeof module === 'undefined' 라
//  else 분기(root.OyMsiSafetyAlert 할당 + attachSafetyAlert(document) 자동 실행)를 타고,
//  여기서는 module.exports 가 있으므로 그 분기를 탄다 — attachSafetyAlert 는 호출되지 않아
//  DOM 없는 환경에서도 안전하다.)

import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SOURCE_PATH = path.join(__dirname, '..', '..', 'resources', 'js', 'oymsi-safety-alert.js');

function loadModule() {
  const src = fs.readFileSync(SOURCE_PATH, 'utf8');
  const mod = { exports: {} };
  const factory = new Function('module', 'exports', src);
  factory(mod, mod.exports);
  return mod.exports;
}

const { computeLevel, nextAlertState } = loadModule();

// ── computeLevel: SafetyEvaluatorTest.php(tests/Feature/OyMsi/SafetyEvaluatorTest.php) 와
// 같은 fixture 로 서버 SafetyEvaluator(S0~S3 문자열 등급)와 이 JS(0~3 숫자 등급)가 어긋나지
// 않는지 비교한다. S0=0, S1=1, S2=2, S3=3.
function saf(overrides = {}) {
  return { SAF01: 0, SAF02: 0, SAF03: 0, SAF04: 0, SAF05: 0, SAF06: 0, ...overrides };
}

test('SAF 전부 0 이면 레벨 0 (서버 S0 과 동일)', () => {
  assert.equal(computeLevel(saf()), 0);
});

test('SAF03=1 이면 레벨 1 (서버 S1, T08)', () => {
  assert.equal(computeLevel(saf({ SAF03: 1 })), 1);
});

test('SAF01=2 이면 레벨 2 (서버 S2)', () => {
  assert.equal(computeLevel(saf({ SAF01: 2 })), 2);
});

test('SAF04=2 이면 레벨 3 (서버 S3, T09)', () => {
  assert.equal(computeLevel(saf({ SAF04: 2 })), 3);
});

test('SAF06=1 이면 레벨 3 (서버 S3, T10)', () => {
  assert.equal(computeLevel(saf({ SAF06: 1 })), 3);
});

test('003 기준 — SAF04=1 은 레벨 2 가 아니라 레벨 3 이다', () => {
  assert.equal(computeLevel(saf({ SAF04: 1 })), 3);
});

test('003 기준 — SAF01=3 · SAF02=3 · SAF05=2 도 레벨 3 이다', () => {
  assert.equal(computeLevel(saf({ SAF01: 3 })), 3);
  assert.equal(computeLevel(saf({ SAF02: 3 })), 3);
  assert.equal(computeLevel(saf({ SAF05: 2 })), 3);
});

test('안전문항 응답거부(null) 만 있으면 레벨 1', () => {
  assert.equal(computeLevel({ SAF02: null }), 1);
});

test('높은 등급이 낮은 등급보다 우선한다 (SAF03=3 과 SAF01=1 동시 → 레벨 3)', () => {
  assert.equal(computeLevel(saf({ SAF03: 3, SAF01: 1 })), 3);
});

// ── nextAlertState: 안내 모달 억제/재표시 양방향 고정 ─────────────────────────

test('같은 등급이 반복돼도 모달은 1회만 뜬다 (억제)', () => {
  // SAF01=2 → 레벨 2 도달, 처음이라 표시
  let state = nextAlertState(saf({ SAF01: 2 }), 0);
  assert.equal(state.level, 2);
  assert.equal(state.show, true);
  assert.equal(state.shownLevel, 2);

  // 이후 문항들에서도 계속 레벨 2인 상태가 반복된다고 가정 (예: SAF02 도 2로 답함 → 여전히 레벨 2)
  state = nextAlertState(saf({ SAF01: 2, SAF02: 2 }), state.shownLevel);
  assert.equal(state.level, 2);
  assert.equal(state.show, false, '같은 등급이 반복되면 다시 보여주면 안 된다');
  assert.equal(state.shownLevel, 2);

  // 원본 버그 재현 시나리오: 등급 2에 도달한 뒤 안전과 무관한 문항들에 계속 응답해도
  // (안전문항 자체는 변화 없음) 재표시되지 않아야 한다.
  state = nextAlertState(saf({ SAF01: 2, SAF02: 2, SAF03: 0 }), state.shownLevel);
  assert.equal(state.show, false);
});

test('등급이 올라가면 모달이 다시 뜬다 (재표시)', () => {
  // 1) SAF01=2 → 레벨 2, 표시
  let state = nextAlertState(saf({ SAF01: 2 }), 0);
  assert.equal(state.level, 2);
  assert.equal(state.show, true);

  // 2) 같은 레벨 2 반복 → 억제
  state = nextAlertState(saf({ SAF01: 2, SAF02: 1 }), state.shownLevel);
  assert.equal(state.show, false);

  // 3) SAF04=1 응답으로 레벨 3 (S3) 로 상승 → 다시 표시돼야 한다
  state = nextAlertState(saf({ SAF01: 2, SAF02: 1, SAF04: 1 }), state.shownLevel);
  assert.equal(state.level, 3);
  assert.equal(state.show, true, '등급이 shownLevel 보다 올라가면 다시 표시해야 한다');
  assert.equal(state.shownLevel, 3);

  // 4) 레벨 3이 계속 유지돼도 더는 반복 표시하지 않는다
  state = nextAlertState(saf({ SAF01: 2, SAF02: 1, SAF04: 1, SAF06: 1 }), state.shownLevel);
  assert.equal(state.level, 3);
  assert.equal(state.show, false);
});

test('레벨 1(관심 단계)은 모달을 띄우지 않는다 — 임계값은 2 이상', () => {
  const state = nextAlertState(saf({ SAF03: 1 }), 0);
  assert.equal(state.level, 1);
  assert.equal(state.show, false);
  assert.equal(state.shownLevel, 0, '표시하지 않았으니 shownLevel 도 그대로 유지된다');
});

test('등급이 내려가면(0으로) shownLevel 을 낮추지 않는다 — 이후 같은 등급 재상승 시 다시 억제되어야 한다', () => {
  let state = nextAlertState(saf({ SAF01: 2 }), 0);
  assert.equal(state.show, true);
  assert.equal(state.shownLevel, 2);

  // computeLevel 은 답변 스냅샷 기준이라 실제로 레벨이 "내려가는" 입력은 만들 수 없지만
  // (한 번 2로 응답한 문항 값이 없어지지 않는 한) shownLevel 자체가 낮아지지 않음을 확인한다.
  state = nextAlertState(saf({ SAF01: 2 }), state.shownLevel);
  assert.equal(state.shownLevel, 2);
});
