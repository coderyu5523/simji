# JS 참조 구현 추출 절차

원본: `C:\work\심지\청소년_마음상태검사_모바일웹앱_index.html` (2,523줄)

1. `<script>` 블록에서 아래 심볼을 그대로 복사해 `reference.js` 로 옮긴다.
   - `ITEMS`, `FACTOR_META`
   - `scoreAnswers`, `getSafetyLevel`, `getEnvironmentLevel`,
     `getFinalCaseCode`, `priorityFactors`
2. DOM 접근 코드(`document.*`, `state.*` 중 렌더링용)는 제거하고,
   함수 인자로 응답 객체를 받도록 최소 수정한다. **계산식은 손대지 않는다.**
3. 파일 끝에 `module.exports = { ... }` 를 붙인다.
4. 원본 파일은 수정하지 않는다.

주의: 원본의 `maybeShowImmediateAlert()` 는 UI 전용이며 버그가 있다(문항 단위
중복방지 키 + 전역 등급 발동 조건). 참조 구현에 포함하지 않는다.

## 실제 추출 시 발견한 이슈 (검증 기록)

- **`getGeneralCode` 추가.** Step 1 목록에 없었지만, 원본 `getFinalCaseCode(generalCode, safetyLevel, environmentLevel)`
  는 `generalCode` 를 인자로만 받을 뿐 계산하지 않는다 — 실제 계산은 별도 함수
  `getGeneralCode(factorScores)` (원본 2190~2199행) 가 담당한다. "일반 사례코드" 를
  대조하려면 이 함수 없이는 불가능하므로 함께 옮겼다. 계산식은 원본과 100% 동일
  (byte-diff 확인, 아래 참고).
- **`getAnswer`/`getSafetyLevel`/`getEnvironmentLevel` 의 기본 인자 제거.**
  원본은 `answerMap = state.answers` (브라우저 전역 상태) 를 기본값으로 쓴다.
  `state` 가 이 파일에 존재하지 않으므로 `= state.answers` 부분만 제거했다.
  참조 구현은 항상 `answerMap` 을 명시적으로 넘기므로 동작은 원본과 동일하다.
- **`bandLabel`/`bandSymbol`/`caseName` 은 옮기지 않았다.** 셋 다 코드→한글 라벨/이모지
  매핑뿐인 렌더링 전용 함수이며 채점 계산에 관여하지 않는다.

## 검증(byte-diff)

원본 HTML의 `ITEMS`(865~1526행), `FACTOR_META`(1527~1564행), 그리고
`bandFromScore`/`scoreAnswers`/`getAnswer`/`getSafetyLevel`/`getEnvironmentLevel`/
`getGeneralCode`/`getFinalCaseCode`/`priorityFactors` 함수 본문(2118~2228행)을
`reference.js` 의 대응 블록과 `diff` 로 대조했다. `ITEMS`/`FACTOR_META` 는 완전히
바이트 동일. 함수들은 위에 기록한 두 가지 차이(기본 인자 제거, UI 전용 함수 제외)를
빼면 계산식·연산자·상수가 전부 동일했다. 원본 파일은 이 과정에서 수정하지 않았다.
