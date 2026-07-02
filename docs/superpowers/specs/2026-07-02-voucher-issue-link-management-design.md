# 검사권 발급·전달(링크)·응시·결과 관리 설계

작성 2026-07-02. 단디(maumrg.kr)의 검사권 발급→링크 응시→결과 관리 모델을 심지(Laravel)에 재설계.

## 목표

회원(발급자)이 보유 검사권을 **대상자에게 링크로 전달** → 대상자가 **로그인 없이** 링크로 응시 → 발급자가 **결과를 명부로 관리**(열람 승인/대기 포함). 개인/기관 구분 없이 동일 기능.

## 확정된 결정

- **발송 방식**: 이번엔 **링크 생성 + 화면 전달(링크 복사)** 까지만. 실제 문자/알림톡 발송은 다음 단계.
- **결과 열람 통제**: **포함** (발급자가 승인/대기 토글, 업계 필수).
- **응시자 정보**: **응시자가 링크에서 직접 입력** (발급자는 대상자 미리 안 넣음).
- **메뉴 위치**: **A안 — `내 검사함` 하나로 통합** (탭 2개). 이름은 "내 검사함" 유지.

## 개념 매핑 (단디 → 심지)

| 단디 | 심지 |
|---|---|
| `g5_inspection_coupon` 코드 1개 | `vouchers` row 1개 (기존 재사용) |
| 코드 발급(index.php) | 발급 = 보유 voucher에 `access_token` 부여 |
| 접속코드/자동링크 | `/t/{access_token}` 링크 |
| `cp_result` 승인/대기 | `vouchers.result_visible` (true/false) |
| `cp_rec_number`/대상자 | 응시자가 입력 → `vouchers.recipient_name/age` |
| status_list 명부 | `내 검사함` [검사권 관리] 탭 명부 |
| 문자 발송(send_sms_api) | (이번 범위 밖) 링크 복사로 대체 |

## 데이터 변경 (가산적, 기존 로직 무손상)

`vouchers` 컬럼 추가 (신규 마이그레이션):
- `access_token` VARCHAR(64) NULL UNIQUE — 링크 토큰. 발급 시 부여.
- `recipient_name` VARCHAR(100) NULL — 응시자가 입력.
- `recipient_age` VARCHAR(20) NULL — 응시자가 입력(선택).
- `result_visible` BOOLEAN NOT NULL DEFAULT true — 열람 승인 여부.
- `assigned_at` TIMESTAMP NULL — 발급 시각.

`test_attempts`: 변경 없음 (이미 `voucher_id`, `user_id`(nullable), `guest_token` 보유).

**voucher 상태 의미 (기존 status 재사용)**
- `active` + `access_token` NULL → 보유(미발급)
- `active` + `access_token` 있음 → 발급됨·미응시
- `used` → 응시 완료 (`used_attempt_id` 연결)

## 흐름 & 컴포넌트

### 1. 발급 (VoucherService 확장)
`issueLinks(User $issuer, Test $test, int $qty): Collection`
- 유료 검사: 보유 active·미발급(`access_token IS NULL`) voucher를 qty개 골라 토큰 부여. 부족하면 예외("보유 검사권 N개 부족").
- 무료 검사: qty개 새 voucher 생성(`source='issued_free'`, active) + 토큰. (단디 "무료도 검사권 1개 생성")
- 토큰 = `Str::random(40)`, 유니크 보장.

### 2. 명부 관리 (`내 검사함` [검사권 관리] 탭)
- MyTestController@index: 발급자 소유 voucher 중 `access_token` 있는 것들 + attempt/result 로드.
- 표시: 검사명 · 대상자(응시 후) · 상태(미응시/완료) · 신호등 · [링크 복사] · [결과 보기] · [열람 토글].
- 발급 폼: 검사 선택 + 수량 → POST `/my/issue`.
- 열람 토글: POST `/my/vouchers/{voucher}/visibility` (소유권 체크).

### 3. 링크 응시 (LinkController, auth 없음)
- GET `/t/{token}` — voucher 유효성 확인. 미응시면 안내 + 이름/연령 입력 폼. 이미 완료면 결과 링크(공개 시).
- POST `/t/{token}/start` — 이름 필수 검증 → attempt 생성(`voucher_id`, 세션 `guest_token`, in_progress) + voucher에 recipient 저장 → take로.
- GET `/t/{token}/take/{attempt}` — 세션 guest_token 소유권 확인 → `assessment.take` 뷰 재사용.
- POST `/t/{token}/take/{attempt}` — 답안 검증·저장 → submitted → ScoringService 채점 → voucher `used` 처리(used_attempt_id) → 결과로.

### 4. 결과 (ResultController 확장)
- 소유권: (a) attempt 응시자(guest_token/user) **또는** (b) 그 attempt voucher를 소유한 로그인 발급자.
- 열람 통제: 응시자 본인이 볼 때 `voucher.result_visible=false`면 "결과 준비 중" 안내. 발급자는 항상 열람.

### 라우트 요약
```
GET  /t/{token}                     link.landing   (no auth)
POST /t/{token}/start               link.start     (no auth)
GET  /t/{token}/take/{attempt}      link.take      (no auth)
POST /t/{token}/take/{attempt}      link.submit    (no auth)
(auth)
POST /my/issue                      my.issue
POST /my/vouchers/{voucher}/visibility  my.voucher.visibility
```

## 범위 밖 (다음 단계)
- 실제 문자/알림톡 발송 (해피톡/솔라피) — 명부의 "링크 복사" 자리에 추가.
- 수동 입력용 접속코드(3조각).
- 발급 시 대상자 명단 CSV 업로드 등 대량 처리.

## 구현 순서 (플랜)
1. 마이그레이션 (vouchers 컬럼 5개 추가).
2. Voucher 모델 fillable/cast, 관계(attempt) 확인.
3. VoucherService::issueLinks + 링크 응시용 소비 로직.
4. LinkController + 라우트(no auth) + 랜딩/시작 뷰.
5. MyTestController 확장 + `내 검사함` 탭 뷰(관리/이력) + 발급 폼 + 명부 + 토글.
6. ResultController 소유권·열람 통제 확장.
7. 검증: migrate, 무료 검사 발급→링크 응시→명부 완료 표시→열람 토글 동작.
