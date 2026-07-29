# OY_MSI 만 13세 담당자 보호자확인 경로 — 설계

- 작성일: 2026-07-29
- 대상 검사: `OY_MSI` (학교 밖 청소년용 마음상태검사, 만 13~18세)
- 선행 작업: 2026-07-28 1단계 구현 (`docs/oy-msi-handover.md` §3-1 "미결"로 남긴 항목)

## 1. 문제

`OY_MSI` 는 자살·자해를 직접 묻는 문항(`SAF01`~`SAF06`)을 포함한다. 그래서 만 14세 미만
(`tests.guardian_consent_below_age = 14`)은 법정대리인 동의가 확인된 경우에만 응시할 수 있게
막아 두었다.

읽는 쪽은 완성돼 있다.

- `AgeGate::blockReason()` — 개인 경로는 무조건 `GUARDIAN_PERSONAL` 차단, 링크 경로는
  `guardianConfirmed` 가 true 일 때만 통과 (`app/Services/OyMsi/AgeGate.php:43-48`)
- `AgeGateController::linkSubmit()` — `vouchers.guardian_consent_confirmed_at !== null` 을 읽어 전달
  (`:65-67`)
- `LinkController::start()` — 서버 재검증 후, 통과 시 `consent_records` 에
  `guardian_offline`/`actor=staff` 행을 파생 생성 (`:78-82`, `:130-143`)

**쓰는 쪽이 없다.** `guardian_consent_confirmed_at` / `guardian_consent_confirmed_by` 두 컬럼을
채우는 화면·액션이 애플리케이션 전체에 존재하지 않는다(현재 값을 쓰는 유일한 코드는 테스트
픽스처 `tests/Feature/OyMsi/AgeGateTest.php:30`).

**결과: 만 13세는 개인·링크 어느 경로로도 응시할 수 없다.** 검사 대상이 만 13~18세로 선언돼
있으므로 대상 연령의 6분의 1이 봉쇄돼 있다. 안전하게 막힌 상태라 위험하지는 않으나 의도한
동작이 아니다.

**방침(2026-07-28 확정)**: 선택지가 갈리면 기존 심지 관행이 아니라 새 문서 기준을 따른다.
문서가 대상을 만 13~18세로 규정하므로 담당자 확인 경로를 구현한다.

## 2. 확정된 결정 (2026-07-29)

| # | 항목 | 결정 |
|---|---|---|
| 1 | 확인 시점 | 명부(`/my`)에서 **링크별 개별** 확인. 발급 폼 일괄 체크는 안 함 |
| 2 | 해제 | **응시 시작 전에만** 가능. 시작 후에는 확인·해제 둘 다 잠김 |
| 3 | 권한 | **발급자 본인**(`voucher->user_id === auth()->id()`). `user_type` 제한 없음 |
| 4 | 기록 범위 | **시각 + 담당자 user_id** 두 컬럼만. 확인 방법·보호자 정보는 수집 안 함 |

### 2-1. 의식적으로 수용한 트레이드오프

권한이 "발급자 본인"이므로 **개인회원도 자기 이름으로 링크를 발급하고 스스로 보호자 동의를
체크할 수 있다.** 개인 경로에서 만 13세를 막아둔 취지("훈련된 담당자가 있는 기관을 통해서만",
003 Ⅸ)가 이 경로로는 적용되지 않는다.

앱 전체에 `user_type` 으로 접근을 제어하는 코드가 하나도 없고 인가가 전부 발급자 소유권
기준이라, 기존 관행과의 일관성을 위해 감수한다. 2단계(기관 경보 워크플로)에서 기관 자격
개념이 생기면 그때 재검토한다.

### 2-2. 결정 1의 근거

발급 폼(`catalog/show.blade.php:65-74`)이 **수량만** 받는다. 발급 시점에 시스템은 각 링크를 누가
쓸지 모른다. 따라서 확인은 발급 후 명부에서 링크 단위로 하는 것만이 성립한다. 이미 발급해
전달한 링크에도 나중에 적용할 수 있다는 이점도 있다.

## 3. 데이터 · 스키마

**마이그레이션 신규 0건.** 두 컬럼이 이미 존재한다.

```php
// database/migrations/2026_07_28_000001_extend_tests_for_oy_msi.php:50-54
$t->timestamp('guardian_consent_confirmed_at')->nullable()->after('result_visible');
$t->foreignId('guardian_consent_confirmed_by')->nullable()
  ->after('guardian_consent_confirmed_at')->constrained('users')->nullOnDelete();
```

`Voucher` 모델에 `guardian_consent_confirmed_at => 'datetime'` 캐스팅도 이미 있고 `$guarded = []`
라 mass-assign 이 가능하다.

**모델에 추가할 관계 2개** (`app/Models/Voucher.php`):

```php
public function guardianConfirmedBy() { return $this->belongsTo(User::class, 'guardian_consent_confirmed_by'); }
public function attempts()           { return $this->hasMany(TestAttempt::class); }
```

`attempts()` 가 별도로 필요한 이유: 기존 `attempt()` 는 `used_attempt_id` 를 보는 belongsTo 라
**제출이 끝난 응시만** 잡는다. 응시 중(`in_progress`)인 attempt 는 잡히지 않으므로 "응시가
시작되었는가" 판정에 쓸 수 없다. `test_attempts.voucher_id` 는
`2026_06_26_100006_add_voucher_id_to_test_attempts.php:8` 에 있다.

**해제는 두 컬럼을 함께 `null` 로 쓴다.** `_at` 만 지우고 `_by` 가 남는 반쪽 상태를 만들지 않는다.

## 4. 판정 규칙 — `App\Services\OyMsi\GuardianConfirmation`

규칙의 단일 출처. 뷰와 컨트롤러가 **같은 메서드**를 호출한다.

```
canConfirm(Voucher $v, User $u): bool
  ① $v->user_id === $u->id                  발급자 본인
  ② $v->access_token !== null               링크로 발급된 것
  ③ $v->test->requiresAgeVerification()     연령확인이 필요한 검사
  ④ ! $this->hasStarted($v)                 아직 응시가 시작되지 않음

canRelease(Voucher $v, User $u): bool
  canConfirm($v, $u) && $v->guardian_consent_confirmed_at !== null

hasStarted(Voucher $v): bool
  $v->attempts()->exists() || $v->status === 'used'
```

**서비스로 빼는 이유**: 이 기능은 판정이 두 곳에서 필요하다 — 뷰는 버튼을 보일지 정해야 하고,
서버는 요청을 거부해야 한다. 인라인으로 두면 두 조건이 각각 적히고, 한쪽만 고쳐지면 화면에는
안 보이는데 POST 는 통과하는 상태가 된다. 1단계 작업에서 "새 진입점이 기존 게이트를 우회"하는
구멍이 3번 나왔고, `AgeGate`·`ConsentGate` 가 정확히 같은 이유로 서비스다.

**③이 필요한 이유**: 없으면 `OY_MSI` 가 아닌 다른 검사권 명부에도 보호자 동의 버튼이 뜬다.
검사 코드를 하드코딩하지 않고 `requiresAgeVerification()`(= `guardian_consent_below_age !== null`)
으로 판정해, 나중에 같은 성격의 검사가 추가되면 데이터만으로 따라오게 한다.

**④의 정의에서 `status === 'used'` 를 함께 보는 이유**: `attempts()->exists()` 가 실질 판정이고
`status` 는 안전벨트다. 둘 중 하나라도 참이면 잠근다(fail closed).

**버튼 노출에 나이 조건이 없는 이유**: 시스템은 각 링크를 누가 쓸지 모른다. `OY_MSI` 미응시 링크
**전부**에 버튼이 뜨고, 담당자가 만 13세에게 줄 링크를 골라 체크한다. 만 14세 이상이 확인된
링크를 써도 `needsGuardianConsentFor(14)` 가 false 라 확인 여부가 무시되고 그냥 통과한다.

## 5. 화면 · 흐름

`resources/views/my/index.blade.php:64-80` 의 명부 카드 액션 영역에 추가한다.

**① 확인 전**

```
마음상태검사
미응시 · 발급 2026.07.29
[ https://.../t/abc123... ] [링크 복사] [만 14세 미만 · 보호자 동의 확인 ▾]
```

**② 펼친 상태** (페이지 이동 없음, 모달 없음)

```
┌─────────────────────────────────────────────────────┐
│ 이 검사는 자살·자해 관련 문항을 포함합니다.         │
│ 만 14세 미만 응시자는 법정대리인 동의가 있어야      │
│ 응시할 수 있습니다.                                 │
│                                                     │
│ ☐ 법정대리인에게 동의를 받았으며, 동의서를 기관이   │
│    보관하고 있음을 확인합니다.                      │
│                          [확인 기록]                │
└─────────────────────────────────────────────────────┘
```

`<details>` 태그로 구현한다. **JS 를 쓰지 않는다.** 명부에 링크가 100개면 카드도 100개인데
모달 하나를 JS 로 돌려쓰면 "다른 카드를 눌렀는데 앞 카드 대상으로 POST 되는" 유형의 버그가
난다. 각 카드가 자기 폼을 가지면 그 문제가 존재하지 않는다.

**③ 확인 후 (미응시)**

```
[ https://.../t/abc123... ] [링크 복사] [✓ 보호자 동의 확인됨 · 7.29] [해제]
```

**④ 응시가 시작된 뒤** — 확인·해제 버튼이 사라지고 배지만 남는다.

```
[ https://.../t/abc123... ] [링크 복사] [✓ 보호자 동의 확인됨 · 7.29 · 응시 시작됨]
```

**⑤ 확인 없이 응시가 시작된 뒤** (만 14세 이상이 그냥 응시한 정상 경우) — 보호자 동의 관련 표시가
**아무것도 뜨지 않는다.** 확인이 필요 없었던 응시에 "미확인" 같은 경고성 표시를 남기지 않는다.

스타일 토큰은 기존 명부 카드와 동일하게 쓴다(`rounded-lg px-3 py-2 text-xs font-bold`,
확인됨은 `bg-signal-green/15 text-signal-green`, 미확인은 `bg-signal-yellow/15`).

## 6. 라우트 · 컨트롤러

```
POST /my/vouchers/{voucher}/guardian-consent          → my.voucher.guardian.confirm
POST /my/vouchers/{voucher}/guardian-consent/release   → my.voucher.guardian.release
```

둘 다 `routes/web.php:94-102` 의 `auth` 그룹 안. 라우트 이름은 `my.voucher.visibility` 의 형제로
맞춘다. 확인은 체크박스 선언이 필요하고 해제는 아니므로 토글 하나가 아니라 두 액션으로 나눈다.

컨트롤러는 신규 `App\Http\Controllers\OyMsi\GuardianConfirmController` (`confirm`, `release`).
`AgeGateController` 가 이미 같은 폴더에 있고, `MyTestController` 는 `index` 가 이미 무거워
성격이 다른 규칙을 얹기에 적절하지 않다.

## 7. 에러 처리

이 코드베이스의 확립 규칙인 **조용한 폴백 금지**를 따른다. 조건 불충족을 통과시키지 않고
예외로 드러낸다.

| 상황 | 처리 |
|---|---|
| 남의 검사권 | `abort(403)` |
| 연령확인이 필요 없는 검사 | `abort(403)` — 화면에 버튼이 없어도 서버가 따로 막는다 |
| 링크 미발급 검사권(`access_token === null`) | `abort(403)` |
| 이미 응시가 시작됨 | `abort(403, '응시가 시작된 검사권은 변경할 수 없습니다.')` |
| 체크박스 미체크 | `validate(['confirm' => 'accepted'])` → `back()->withErrors` |
| 이미 확인된 것을 또 확인 | 시각을 **덮어쓰지 않고** `back()->with('status', '이미 확인되어 있습니다.')` |
| 확인되지 않은 것을 해제 | `back()` (상태 변화 없음) |

**403 과 no-op 의 경계**: 403 은 `canConfirm()` 의 ①~④ 중 하나가 거짓일 때만이다. "이미 확인됨을
또 확인" 과 "미확인을 해제" 는 규칙 위반이 아니라 **결과가 같은 재요청**이므로 no-op 으로 처리한다.
따라서 컨트롤러는 `abort_unless($rules->canConfirm(...))` 로 인가를 먼저 세우고, 그 뒤에 현재
확인 상태를 보고 쓰기 여부를 정한다. `canRelease()` 를 `abort_unless` 에 직접 쓰면 미확인 해제가
403 이 되어 이 표와 어긋나므로 그렇게 쓰지 않는다 — `canRelease()` 는 **뷰에서 해제 버튼을 보일지
정하는 용도**다.

**중복 확인에서 시각을 갱신하지 않는 이유**: 최초 확인 시각이 증거다. 재제출로 시각이 밀리면
"언제 동의를 받았는가"의 기록이 훼손된다.

**레이스 처리**: 담당자가 해제를 누르는 순간 응시자가 링크를 여는 경우가 있다. `release` 를
`DB::transaction` + `lockForUpdate` 로 감싸고 잠근 뒤 `hasStarted()` 를 **다시** 확인한다.
이 처리가 없어도 최악은 "응시는 진행되고 voucher 컬럼만 null" 이며 `consent_records` 증거 행은
이미 생성돼 있어 실질 피해는 없으나, 화면과 데이터가 어긋나는 상태를 만들지 않는다.

## 8. 테스트

`tests/Feature/OyMsi/GuardianConfirmTest.php` — Pest, 한국어 시나리오 문장, `beforeEach` 에서
`TestSeeder` + `ScoringRuleSeeder` 실행(기존 관행).

**Pest 전역 함수 충돌 주의**: voucher 팩토리 헬퍼 이름은 `guardianConfirmVoucher` 로 한다.
`AgeGateTest` 의 `ageGateVoucher`, `LinkConsentGateTest` 의 `makeOyMsiVoucher` 와 겹치면 안 된다
(`tests/Feature/OyMsi/AgeGateTest.php:23` 주석 참조).

**규칙**

1. 발급자가 미응시 링크에 확인하면 `_at`·`_by` 두 컬럼이 함께 채워진다
2. 체크박스 없이 제출하면 거부되고 컬럼이 그대로다
3. 남의 검사권에 확인 시도 → 403
4. 연령확인이 필요 없는 검사의 검사권 → 403
5. 응시가 시작된 뒤에는 확인도 해제도 403
6. 해제하면 두 컬럼이 함께 null 이 된다(`_by` 만 남는 반쪽 상태가 없다)
7. 이미 확인된 것을 다시 확인해도 최초 확인 시각이 바뀌지 않는다

**화면**

8. 미확인 링크엔 확인 폼이, 확인된 링크엔 배지와 해제 버튼이, 확인 후 응시 시작된 링크엔
   배지만 렌더된다
8-1. 확인 없이 응시가 시작된 링크에는 보호자 동의 관련 표시가 아무것도 렌더되지 않는다(§5 ⑤).
   **`assertDontSee` 는 대상이 실제로 렌더되는 조건에서 해야 공허하지 않다** — 같은 명부에
   확인된 링크를 함께 두어 배지가 렌더되는 상황임을 확인한 뒤 이 링크에만 없음을 단언한다

**진짜 성공 기준 (E2E)**

9. 담당자가 확인한 링크로 **만 13세가 응시를 완주**하고, `consent_records` 에
   `guardian_offline` / `actor=staff` 행이 확인 담당자의 `user_id` 와 함께 생성된다

**회귀**

10. 확인 없는 링크로는 만 13세가 여전히 `GUARDIAN_LINK` 로 차단된다
11. 개인 경로(로그인 직접 응시)의 만 13세 차단은 그대로다

**9번이 필요한 이유**: 1단계 작업에서 회귀가 초록불로 다섯 번 왔다 — 새 가드를 기존 검사 **앞**에
세우면 기존 테스트가 *통과한 채로* 무력해진다(원래 단언에 도달하지 못함). 1~8번은 컬럼과 화면만
보므로, 컬럼은 잘 채워지는데 정작 응시자가 검사를 못 하는 상태에서도 전부 초록이다. 9번만이
"만 13세가 실제로 검사를 마쳤다"를 확인한다.

**뮤테이션 검증**: 구현 후 `canConfirm` 의 소유권 검사(①)와 응시-시작 검사(④)를 각각 일시
제거해 3번·5번이 실제로 깨지는지 확인한다. 깨지지 않으면 그 테스트는 공허하다.

## 9. 범위 밖 (YAGNI)

- **대상자 이름 입력** — 발급 폼에 없고, 링크는 사용 전에는 서로 구별할 필요가 없다. 담당자가
  체크한 링크를 만 13세에게 전달하면 된다. (`recipient_name` 쓰기 경로 부재는
  `docs/oy-msi-handover.md:102` 의 별도 백로그로 남는다)
- **확인 이력 로그 테이블** — 컬럼 두 개로 현재 상태만 남긴다
- **`user_type` 기반 제한** — §2-1 참조
- **동의 철회(`granted = false`) 표현** — `ConsentGate::record` 의 하드코딩 문제는
  `docs/oy-msi-handover.md` §3-2 의 별도 미결 항목이며 이 작업에서 건드리지 않는다
- **발급 시 일괄 체크** — §2 결정 1

## 10. 배포와의 관계

이 작업은 배포 차단 6건(`docs/oy-msi-handover.md` §1)을 해소하지 **않는다.** 특히
`render.yaml:9` 이 배포 브랜치를 못박고 있어 **push = 즉시 배포**인 상태가 유지되므로, 이
작업이 끝나도 push 금지는 그대로다.

## 11. 관련 문서

- `docs/oy-msi-handover.md` — 1단계 인수인계 (§3-1 이 이 작업의 출처)
- `docs/superpowers/specs/2026-07-27-oy-msi-laravel-design.md:250-267` — 원 설계의 담당자 흐름
- `docs/oy-msi-manual-verification.md` — 수동 검증 체크리스트 (전부 미체크)
- `.superpowers/sdd/2026-07-27-oy-msi-phase1/progress.md` — 1단계 상세 이력
