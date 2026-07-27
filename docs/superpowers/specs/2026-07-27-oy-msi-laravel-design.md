# 학교 밖 청소년 마음상태검사(OY_MSI) — 심지 Laravel 구현 설계

- 작성일: 2026-07-27
- 상태: 설계 승인 완료 / 구현계획 미작성
- 검사 코드: `OY_MSI` (007 §3.1)
- 노선: **심지 Laravel 유지** (그누보드 재구축 폐기 — 2026-07-27 결정)

## 0. 원자료

| 문서 | 역할 |
|---|---|
| `003학교 밖 청소년 마음상태검사 예비안.docx` | 검사 원본 사양 — 10요인 60문항 전문, 척도, 신호등, 요인별 해석·솔루션 |
| `007학교밖청소년_마음상태검사_개발자용_자동채점_설계서.docx` | 구현 명세 v1.0 — 문항 마스터, 경보 규칙, 사례코드, DB/API, 테스트 |
| `005학교 밖 청소년 마음상태 결과보고서.docx` | 청소년용 결과 문안 20p + 자동출력 규칙 |
| `006학교 밖 청소년 마음상태검사 결과보고서.docx` | 보호자용 16p + 교사·상담자용 15p |
| `청소년_마음상태검사_모바일웹앱_index.html` | 007 지시문으로 생성된 **부분 구현물**(2,523줄). 참조 구현으로 활용 |

선행 분석: `2026-07-27-youth-mind-state-test-analysis.md`

## 1. 확정된 결정 사항

| # | 항목 | 결정 |
|---|---|---|
| 1 | 범위 | **1단계 A**(검사+채점+청소년/보호자 결과, 비공개) → **2단계 B**(기관 경보 워크플로 후 공개) |
| 2 | 안전등급 기준 | **003 예비안 채택**(더 보수적). 임계값은 `scoring_rules.rules`에 데이터로 분리해 교체 가능하게 |
| 3 | 응시 경로 | **개인 직접 + 기관 링크 둘 다** |
| 4 | 만 14세 미만 | 개인 경로 **차단**, 기관 경로만 허용 + 기관의 오프라인 법정대리인 동의 확인 기록 |
| 5 | 결과 열람 | 응시 직후 **청소년용만**. 보호자용은 청소년 선택 또는 담당자 검토 후 별도 발급 |
| 6 | 기본정보 | 닉네임 · 생년월일(→만나이만 저장) · 성별. **학년·학교명 제거** |
| 7 | 구현 방식 | **검사별 전용 엔진 + 기존 스키마 가산 확장** |
| 8 | 응답거부 | `PREFER_NOT` 1단계 포함(안전 로직의 일부) |
| 9 | 배치·가격 | 중고등 방(`middle`) 노출 + 기관 링크 직접 진입 / **무료** |
| 10 | PDF·재검추적 | 1단계 제외 (인쇄만) |
| 11 | 문항 배치 | 007 고정 혼합배치 유지 + **연속 배치 위반 2건 교정** → `assessment_version 1.0.1` (§1.3) |

### 1.1 안전등급 — 003 vs 007 충돌과 해소

003과 007이 같은 응답에 다른 등급을 매긴다. **003을 채택**한다.

| 응답 | 003 | 007 | 채택 |
|---|---|---|---|
| SAF04(구체적 방법·도구) ≥ 1 | 빨강 | S2 | **S3** |
| SAF01 = 3 | 빨강 | S2 | **S3** |
| SAF02 = 3 | 빨강 | S2 | **S3** |
| SAF05(12개월 자해) ≥ 2 | 빨강 | S2 | **S3** |
| SAF03 = 3 / SAF06 ≥ 1 | 빨강 | S3 | S3 (동일) |

근거: 007 §7.1이 "S3는 응급실 자동이송을 뜻하지 않으며 실제 응급조치는 `acute_emergency` 필드로 결정한다"고 명시하므로, S3를 넓게 잡아도 과잉 응급대응이 되지 않는 안전장치가 설계에 이미 있다. 반대 방향의 오분류(계획 수립자를 S2로 낮춰 확인이 하루 늦음)는 되돌릴 수 없다. 대상 집단(학교 밖 청소년)의 12개월 자살시도율 7.8%, 자해 16.2%.

**단, 임상 판단 영역이므로 검사 저자 확인 시 007로 되돌릴 수 있게 규칙을 데이터로 분리한다**(코드 수정 없이 시더 교체 + `scoring_version` 상향).

**등급 체계 병합 방식**: 003은 초록/노랑/빨강 **3단계**, 007은 S0~S3 **4단계**다. 003의 빨강을 S3로 올리고, 나머지는 007의 S1/S2 구분을 유지한다. 결과는 §2.3의 `safety` 규칙.

**003의 환경 조건이 누락되지 않는 이유**: 003은 안전등급 안에 환경문항을 섞어 넣었다(초록 조건 "24·35번 0~1점", 노랑 "24 또는 35번 2점", 빨강 "24 또는 35번 3점"). 007 방식대로 S와 E를 분리해도, 그 조건들은 각각 E1·E2·E3로 매핑되고 최종 사례코드가 `max(S,E)`이므로 **최종 등급(C1/C2/C3)은 동일하게 나온다.** 003의 안전 요구가 손실되지 않는다. 이 등가성은 §6 테스트 1에서 확인한다.

### 1.2 법적 근거 (개인정보보호법)

- **§22-2** 만 14세 미만 아동의 개인정보 처리 시 법정대리인 동의 + **동의 여부 확인 의무**
- **§23** 건강 정보는 민감정보. 심리검사 결과 포함. **다른 동의와 별도 동의** 필요
- **§22-2②** 법정대리인 동의를 받기 위해 필요한 최소한의 정보는 동의 없이 아동에게서 직접 수집 가능 → **연령 확인을 동의보다 먼저** 배치하는 근거
- **§15①5호**(급박한 생명·신체 이익) 예외는 "법정대리인이 의사표시를 할 수 없거나 주소불명 등으로 사전 동의를 받을 수 없는 경우"가 전제. **"보호자가 가해자일 수 있다"를 자동으로 커버하지 않는다.** 검사 시작의 동의 면제 근거로 쓸 수 없고, 검사 중 확인된 위기의 사후 대응 근거로만 사용.

> 위 해석은 조문·해석자료 기반이며 **운영 개시 전 법무 검토 필수**. 특히 "기관이 오프라인 동의를 받고 시스템은 확인 기록만 남긴다"는 구조가 §22-2 확인 의무를 충족하는지.

기존 `tests.requires_guardian_consent`(검사 단위 플래그)는 **판정 단위가 틀렸다**. 이 검사는 대상이 만 13~18세로 14세 미만과 이상이 섞이므로, 검사 단위가 아니라 **응시자 연령 단위**로 판정해야 한다. 컬럼은 남기되 이 검사에서는 사용하지 않는다.

### 1.3 문항 배치 — 007 순서 + 위반 2건 교정

007의 Q001~Q060은 **라운드로빈 혼합배치**다. 10문항씩 한 사이클이고, 각 사이클에 10개 요인이 정확히 한 번씩 나오며, 사이클 안의 요인 순서는 매번 다르게 섞여 있다.

| 사이클 | 문항 | 배치(교정 후) |
|---|---|---|
| 1 | Q001~Q010 | DEP01 · LIF01 · ANX01 · ISO01 · FUT01 · IMP01 · FAM01 · TRM01 · RSK01 · SAF01 |
| 2 | Q011~Q020 | ANX02 · FUT02 · FAM02 · DEP02 · RSK02 · TRM02 · LIF02 · SAF02 · IMP02 · ISO02 |
| 3 | Q021~Q030 | **IMP03 · ISO03** · LIF03 · ANX03 · TRM03 · SAF03 · RSK03 · DEP03 · FUT03 · FAM03 |
| 4 | Q031~Q040 | FUT04 · DEP04 · RSK04 · SAF04 · FAM04 · ANX04 · ISO04 · IMP04 · LIF04 · TRM04 |
| 5 | Q041~Q050 | **LIF05 · SAF05 · TRM05** · FUT05 · IMP05 · DEP05 · FAM05 · ANX05 · RSK05 · ISO05 |
| 6 | Q051~Q060 | RSK06 · ISO06 · DEP06 · TRM06 · FAM06 · ANX06 · FUT06 · IMP06 · LIF06 · SAF06 |

**교정한 이유**: 007 §4.1의 첫 규칙 "동일 요인 문항은 연속 배치하지 않는다"를 007 표 자신이 **사이클 경계에서 2번 위반**한다. 사이클 안에서만 섞고 경계를 확인하지 않은 결과로 보인다.

| 위치 | 원본 | 교정 |
|---|---|---|
| Q020 → Q021 | ISO02 → **ISO03** (연속) | Q021↔Q022 교환 → ISO02 → IMP03 → ISO03 |
| Q040 → Q041 | TRM04 → **TRM05** (연속) | Q041↔Q043 교환 → TRM04 → LIF05 → SAF05 → TRM05 |

Q041은 Q042가 아니라 **Q043과 교환**했다. 007 §4.1이 안전문항 위치를 Q010·Q018·Q026·Q034·**Q042**·Q060으로 고정했기 때문에 SAF05는 Q042에 그대로 둬야 한다.

교정 후 상태: 동일 요인 연속 **0건** / 안전문항 6개 위치 유지 / 역채점 후반 분산 유지(FUT04=Q031, FUT05=Q044, FUT06=Q057) / 사이클당 10요인 1회씩 유지.

**지금 교정하는 근거**: 007 부록 C가 "표시순서가 바뀌면 `assessment_version`을 올린다"고 규정하고, 아직 **표준화 전이라 기존 응답 데이터가 없다.** 표본 수집 시작 후에는 순서를 변경할 수 없다.

개인별 무작위 순서변경은 007 §4.1대로 **금지**한다(표준화·재현성). 동일 `assessment_version`이면 모든 응시자가 같은 순서를 본다.

## 2. 데이터 모델

기존 컬럼 **삭제 0건**. 기존 컬럼 **변경 1건**(`attempt_answers.value`를 nullable로 — 응답거부 저장에 필요). 나머지는 전부 추가.
Laravel 11은 `change()`에 doctrine/dbal이 필요 없다. 기존 행은 전부 값이 있으므로 nullable 완화는 무손실.

### 2.1 기존 테이블 확장

| 테이블 | 추가 컬럼 | 비고 |
|---|---|---|
| `tests` | `scoring_engine` varchar default `'signal'` | `'oy_msi'` |
| | `assessment_version` varchar(30) default `'1.0.0'` | 문항 구성·표시순서 버전. 이 검사는 **1.0.1**(§1.3 교정 반영) |
| | `min_age` / `max_age` smallint null | **13 / 18 = 검사의 대상 연령**(문서 기준). 개인 경로의 14세 하한은 아래 컬럼이 만든다 |
| | `guardian_consent_below_age` smallint null | **14**(PIPA). 이 미만이면 법정대리인 동의 필요 → 개인 경로 차단·기관 경로만. null이면 연령 규칙 없음 |
| `test_items` | `item_code` varchar(20) | `DEP01`… **채점의 유일한 기준**. unique(test_id, item_code) |
| | `scale_code` varchar(30) | `GEN_4PT` / `SAF_THOUGHT_4PT` / `SAF_BEHAVIOR_4PT` |
| | `timeframe_code` varchar(30) | `PAST_2_WEEKS` / `PAST_12_MONTHS` |
| `attempt_answers` | `value` → **nullable** | 응답거부 시 null |
| | `missing_code` varchar(30) null | `PREFER_NOT` |
| `test_attempts` | `nickname` varchar(50) null | |
| | `age_at_test` smallint null | **생년월일은 저장하지 않음** — 만나이만 |
| | `gender` varchar(20) null | |
| | `assessment_version` / `scoring_version` varchar(30) null | 시작 시점 스냅샷 (007 버전 고정 원칙) |
| `test_results` | `general_case_code` varchar(5) | G0/Y1/Y2/R1/R2 |
| | `final_case_code` varchar(5) | 위 또는 C1~C3 |
| | `safety_level` char(2) / `environment_level` char(2) | S0~S3 / E0~E3 |
| | `score_status` varchar(20) default `'COMPLETE'` | COMPLETE/PARTIAL/UNSCORABLE |
| | `engine_result` json | 007 §11.4 구조 전체 |
| `scoring_rules` | `version` varchar(30) default `'1.0.0'` | |
| `vouchers` | `guardian_consent_confirmed_at` timestamp null | |
| | `guardian_consent_confirmed_by` foreignId users null | |

기존 `test_results` 컬럼(`area_scores`·`area_signals`·`overall_signal`·`overall_level`·`interpretation`·`recommendations`)은 **계속 채운다**. 기존 결과 화면 회귀 방지.

`vouchers.result_visible`(기존)은 담당자 결과 열람 승인 게이트로 그대로 재사용.

### 2.2 신규 테이블 3개

**`interpretation_templates`** — 결과 문안
`template_key` / `locale` / `version` / `text` / `active`
키 규칙 007 §9.2: `result.{audience}.{factor}.{band}.{component}`
예: `result.YOUTH.DEP.RED.meaning`, `result.GUARDIAN.DEP.RED.actions`, `result.YOUTH.SAF.S2.notice`

**`report_shares`** — 보호자용 결과 공유
`attempt_id` / `audience`(`guardian`) / `token` unique / `source`(`youth_self` \| `staff`) / `created_by` null / `expires_at` / `revoked_at` / `viewed_at`

**`consent_records`** — 동의 증빙
`attempt_id` / `consent_type`(`sensitive` \| `guardian_offline`) / `granted` / `granted_at` / `actor`(`youth` \| `staff`) / `actor_user_id` null / `meta` json

### 2.3 `scoring_rules.rules` 구조

```
factors:      코드·이름·included_in_overall·tie_break 가중치
              (SAF는 included_in_overall=false)
bands:        GREEN 0~5 / YELLOW 6~10 / RED 11~18
safety:       S3 [SAF03=3, SAF04>=1, SAF06>=1, SAF01=3, SAF02=3, SAF05>=2]   ← 003 빨강
              S2 [SAF01=2, SAF02=2, SAF03=2]                                ← 007 S2 잔여
              S1 [SAF01=1, SAF02=1, SAF03=1, SAF05=1, SAF 무응답/PREFER_NOT]
environment:  E3 [TRM06=3, FAM05=3, RSK06=3]
              E2 [TRM06=2, FAM05=2, RSK06=2, RSK04>=2, RSK05>=2]
              E1 [TRM06=1, FAM05=1, RSK06=1, RSK05=1]
case_codes:   R2(red>=2) R1(red=1) Y2(yellow>=3) Y1(yellow>=1) G0
              max(S,E)>0 → C1/C2/C3 격상
priority:     severity_weight {GREEN 0, YELLOW 100, RED 200}
              + risk_index + alert_bonus 1000 + tie_break
              tie_break: DEP 9 > TRM 8 > FAM 7 > RSK 6 > IMP 5 > ISO 4 > LIF 3 > ANX 2 > FUT 1
strengths:    TRY_NEW(FUT04 raw>=2) / SMALL_GOAL(FUT05>=2) / RECOVERY_HOPE(FUT06>=2)
              / HONEST_RESPONSE / HELP_SEEKING
solutions:    10종 + dedupe_group
recheck:      RECHECK_14D / 28D / 90D 산출 규칙
```

하드코딩된 임계값 0개.

## 3. 채점 엔진

### 3.1 구조

`ScoringService`를 디스패처로 전환. 기존 호출부(`AssessmentController::submit`, `LinkController::submit`) 무변경.

```php
interface ScoringEngine {
    public function score(TestAttempt $attempt): TestResult;
}
// tests.scoring_engine 으로 구현체 선택
//   'signal' → SignalScoringEngine  (기존 로직 그대로 이동)
//   'oy_msi' → OyMsiScoringEngine
```

### 3.2 `OyMsiScoringEngine` 구성 (007 §15 의사코드 순서)

| 클래스 | 책임 |
|---|---|
| `ItemScorer` | 역채점 `3 - raw`, `PREFER_NOT` → null |
| `FactorScorer` | 6문항 합(0~18) / 5문항 `×6/5` PARTIAL / 4문항 이하 UNSCORABLE, `risk_index = raw/18*100`, 밴드 |
| `SafetyEvaluator` | S0~S3 + **SAF 무응답 → 최소 S1** |
| `EnvironmentEvaluator` | E0~E3 |
| `CaseClassifier` | general → final 격상 |
| `PriorityRanker` | 상위 3영역 |
| `StrengthExtractor` | 강점 5종, 최소 1개 보장 |
| `SolutionRecommender` | 안전 솔루션 최우선 고정, `dedupe_group` 중복 제거, 3개 이하 |

전부 `scoring_rules.rules`를 읽는다.

### 3.3 고칠 버그

| 버그 | 수정 |
|---|---|
| **0점 응답 서버 거부** — `'answers.*' => 'integer\|min:1\|max:5'`가 `AssessmentController:91`·`LinkController:92` 두 곳 | 문항의 `options`에서 허용값을 끌어오는 검증 규칙으로 교체. 5점 문항 1~5, 4점 문항 0~3, `PREFER_NOT` 허용. 두 컨트롤러 공용 |
| **안전 모달 반복 팝업** — 원본 `maybeShowImmediateAlert()`의 중복방지 키가 문항 단위인데 발동 조건은 전역 안전등급. S2 도달 후 남은 모든 문항에서 재표시(최악 50회) | 억제 키를 **도달 최고 등급**으로. 등급 상승 시에만 재표시 |
| **`shownAlerts` 미초기화** — 뒤로 가서 답을 낮춰도 유지 | 등급 기준 억제로 자연 해소 + 재평가 |
| **`priorityFactors` weight 가산 무의미** | 007 명세대로 `severity_weight 100/200` + `alert_bonus 1000` 적용 |
| **`factorScores[].count` 죽은 코드** | `answered_count`가 PARTIAL 판정에 실제 사용되는 필드가 됨 |
| **E1 하나로 일반 코드가 가려짐** → 기관 통계 왜곡 | `general_case_code`·`final_case_code` **둘 다 저장**. 통계는 general, 대응은 final |
| **문항 번호 체계 이중화** — 003/005/006은 요인순 1~60, 007은 화면순 Q001~Q060 | `item_code` 단일 기준 |
| **SAF 요인점수 미표시** | 의도된 설계로 명시. 청소년·보호자 화면 미노출, 2단계 전문가용에서만 |

### 3.4 응시 중 안전 안내 — 1단계는 클라이언트만

007은 문항 응답마다 서버가 즉시 경보를 평가하도록 요구하나, 1단계에는 경보를 받을 담당자가 없다. 1단계는 SAF 문항 응답 시 화면 안내만 띄우고 **문항별 서버 저장 + 담당자 알림은 2단계**로 미룬다. 폼 일괄 제출 구조 유지.

- **S1 도달** — 하단 안내 배너 (중단 없음)
- **S2 이상 도달** — 모달 + 109 / 1388 전화 버튼 + "검사 계속하기"
- 검사를 강제 중단시키지 않는다. 중단하면 결과가 안 나와 담당자가 볼 근거가 사라진다.

## 4. 응시 흐름

### 4.1 동의 우회 차단 (6/26 spec의 하드 선결조건 해소)

현재 `agree()`는 세션 플래그만 남기고 `start()` 직접 호출로 우회 가능하다. 실제 아동 대상 검사를 켜는 지금이 그 선결조건 시점이다.

**수정**: 동의 시점에 `test_attempts`를 `status='created'`로 먼저 생성하고 `consent_records`를 붙인다. `take`·`submit`은 동의 레코드가 없으면 진입 불가. 007의 세션 상태(`CREATED → IN_PROGRESS → COMPLETED`)와 정합.

### 4.2 경로 1 — 개인 직접 (무료·비로그인 가능)

```
검사 소개
 → ① 연령 확인 (생년월일)
      만 14세 미만 → 차단: "기관을 통해 응시할 수 있어요" + 꿈드림 안내 + 1388
      만 19세 이상 → 대상 아님 안내
      만 14~18세  → 계속
 → ② 민감정보 별도 동의 (PIPA §23)
      → attempt 생성(status=created) + consent_records(sensitive)
 → ③ 기본정보 (닉네임 · 성별)      ※ 생년월일은 만나이만 남기고 폐기
 → ④ 60문항 응시 (SAF 응답 시 안전 안내)
 → ⑤ 청소년용 결과
 → ⑥ [보호자와 공유하기] → report_shares 발급
```

연령을 동의보다 먼저 받는 근거는 §1.2(PIPA §22-2②).

### 4.3 경로 2 — 기관 링크

```
[담당자] 로그인 → 검사권 발급
   → 대상자 메모(명부용 이름)
   → 만 14세 미만 대상이면 ☑ "법정대리인 동의를 확보했습니다"
        → vouchers.guardian_consent_confirmed_at / _by 기록
        → consent_records(guardian_offline, actor=staff)
   → /t/{token} 생성 → 문자·QR·현장 태블릿

[청소년] 링크 열기 (로그인 없음)
   → ① 안내 → ② 연령 확인
        만 14세 미만인데 담당자 확인 기록 없음 → 차단, 담당자 문의 안내
   → ③ 동의 → attempt(created) + consent_records
   → ④ 기본정보 → ⑤ 60문항 → ⑥ 청소년용 결과

[담당자] 명부에서 결과 확인 (vouchers.result_visible)
```

**이름 분리**: 담당자 명부의 이름(`vouchers.recipient_name`)과 청소년이 적는 닉네임(`test_attempts.nickname`)은 별개 필드. 결과지에는 닉네임만.

### 4.4 공통

응시 화면·채점·결과 렌더는 한 벌만 만든다. 현재 `LinkController::take()`가 `assessment.take` 뷰를 `submitUrl`만 바꿔 재사용하는 패턴을 따른다.

## 5. 결과 화면과 공유

### 5.1 청소년용 (005 부록1 간편형 기준)

```
[안전 패널]        S1·E1 이상일 때만 최상단. 109 / 1388 / 112 / 119
1. 종합 마음상태    신호등 + 전체 위험지수 + 자동 종합문안
2. 영역별 신호등    9요인 (막대 + raw/18)
3. 지금 먼저 살펴볼 3가지   상위 3영역 × (의미 + 이번에 해볼 일)
4. 나에게 남아 있는 강점    최소 1개 보장
5. 이번 주 작은 실천        3개 이하, 각 5~15분 내 시작 가능
6. 도움받을 수 있는 곳      109 · 1388 · 112 · 119 + 꿈드림
7. 다시 확인할 시점         재검 권고 문구만 (14 / 28 / 90일). 종단 비교는 2단계
[전체 영역 자세히 보기]  ← 집중지원형 규칙: 빨강·노랑 상세, 초록 요약
[인쇄하기]  [보호자와 공유하기]
하단 고지문 (005 부록2 전문)
```

표시 순서는 005 부록1 §2: 자해·자살 → 학대·폭력·착취 → 빨강 → 노랑 → 강점 → 실천 → 기관.

**SAF 요인 원점수는 청소년·보호자 화면에 표시하지 않는다.** 안전 패널로만 표현.

### 5.2 금지 표현 자동 검사

005 부록1 §3의 금지 표현 10개("문제가 심각하다", "비정상이다", "자살성향이 있다", "게으르다" 등)를 `interpretation_templates` 전체에서 스캔해 발견 시 실패하는 테스트를 둔다.

### 5.3 보호자용 공유

```
[보호자와 공유하기]
   S0·S1  → 공유 링크 생성 (기본 30일 만료)
   S2 이상 → 먼저: "지금은 먼저 이야기할 사람이 필요해 보여"
             [109] [1388]
             그 아래 작게 → "그래도 보호자와 공유할래" (기본값 아님)
```

기관 경로는 담당자가 안전성 검토 후 발급 가능(`source=staff`). 공유는 철회 가능(`revoked_at`), 열람 시각 기록.

**보호자용 내용**(006 1부): 종합등급 · 영역별 신호등 · 상위 3영역 · 가정에서의 대응방법 · 피해야 할 반응 · 상담 권고 · 재검시점.
자해·자살 문항별 응답, 성적 피해·착취, 가족폭력 세부는 **미포함**(006 부록 제한).

### 5.4 결과 재열람

| 경로 | 재열람 |
|---|---|
| 개인 · 비로그인 | 세션 유지 동안. "링크 저장" 안내 |
| 개인 · 로그인 | 내 검사함 |
| 기관 링크 | 담당자는 명부에서 상시. 청소년은 세션 동안 |

PDF는 2단계. 1단계는 브라우저 인쇄(`@media print` CSS는 원본 HTML에서 이식).

## 6. 검증

| # | 테스트 | 내용 |
|---|---|---|
| 1 | 경계값 | 007 §17 T01~T18. 합 5→GREEN / 6→YELLOW / 10→YELLOW / 11→RED, FUT04 raw3→scored0, **SAF04=1→S3(003 기준)**, FAM05=3→E3→C3, RED 2개→R2 |
| 2 | **JS ↔ PHP 0 diff** | 무작위 응답 수천 건 대조. 요인점수·위험지수·밴드·S/E·사례코드·상위3영역. S등급만 의도적 차이(003) — 차이가 나야 할 곳에서만 나는지 확인 |
| 3 | 문항 무결성 | 60문항 / `item_code` 유니크 / `display_order` 1~60 연속 / 요인별 6문항 / 역채점 FUT04~06 셋뿐 / SAF `included_in_overall=false` / 척도 배정 GEN 54·SAF-T 4·SAF-B 2 |
| 3b | **배치 규칙** (007 §4.1) | **동일 요인 연속 0건**(사이클 경계 포함) / 안전문항이 Q010·Q018·Q026·Q034·Q042·Q060에 위치 / 10문항 사이클마다 10요인이 정확히 1회씩 / 역채점 FUT04~06이 후반(Q031 이후)에 분산 |
| 4 | 문안 누락 0 | 135(9×3×5) + S0~S3 + E0~E3 + 종합 3 + 강점 5 + 솔루션 10 |
| 5 | 금지 표현 | 005 부록1 §3의 10개 |
| 6 | 동의 우회 차단 | `consent_records` 없이 `take`/`submit` 직접 호출 → 차단 |
| 7 | 연령 분기 | 만 13세 개인 차단 / 기관은 담당자 확인 있으면 통과·없으면 차단 / 만 19세 대상 아님 |
| 8 | 회귀 | 기존 55 pass 유지, 5점 샘플 결과 무변화 |

## 7. 1단계 완료 정의

- 테스트 8종 전부 통과
- 개인·기관 두 경로 수동 응시 확인
- S0 / S1 / S2 / S3 / E3 각 케이스 화면·안내 확인
- **`tests.status`를 `active`로 올리지 않는다** — 내부·시연용

## 8. 2단계(B) 공개 오픈 게이트

003 Ⅸ: *"안전문항 양성반응에 대응할 훈련된 담당자가 없는 환경에서는 검사를 시행하지 않는다."*
1단계만으로는 이 조건을 충족하지 못하므로 공개할 수 없다.

- [ ] 기관 계정·역할(담당자/사례관리자) + 권한별 노출 통제
- [ ] `alert_events` + 담당자 알림 + 확인(ACK) + 미확인 에스컬레이션
- [ ] 문항별 서버 저장 (중도 이탈해도 SAF 응답 보존·경보 발생)
- [ ] 전문가용 결과(006 2부) + 안전평가 기록 양식
- [ ] 감사로그
- [ ] **법무 검토** (§1.2)
- [ ] 문안 운영기관 검수 (109·1388 등)
- [ ] 위기대응 담당자 지정 + 모의훈련 (007 §18)

## 9. 이번 범위 아님

PDF 생성 · 재검 종단추적 · 백분위/T점수(표준화 후 `norm_version` 발행 시) · 교사용·사례관리자용 보고서 · 기관 집단 통계 · 결제

## 10. 미해결 / 확인 필요

| 항목 | 내용 |
|---|---|
| 안전등급 기준 | 003 채택했으나 **검사 저자 확인 권장**. 규칙 데이터라 교체 비용 낮음 |
| 법무 검토 | "기관 오프라인 동의 + 시스템 확인 기록" 구조의 §22-2 충족 여부 |
| KMSIY 관계 | 단디의 미구현 KMSIY(청소년 마음상태검사)와 이 검사의 관계 미확정. 검사코드·명칭 체계에 영향 가능 |
| 문안 이관 | 원문은 003 Ⅵ·Ⅶ / 005 / 006 1부에 모두 존재(창작 아님, **전사** 작업). 1단계 소요 레코드 **약 174건**: 요인 문안 135(9요인×3밴드×[YOUTH meaning·actions + GUARDIAN meaning·actions·avoid]) + SAF S0~S3 × 2대상 8 + ENV E0~E3 × 2대상 8 + 종합 3밴드 × 2대상 6 + 강점 5 + 솔루션 10 + 고지문 2 |
