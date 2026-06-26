# 심지 결제·검사권(voucher) 레이어 설계

- 작성일: 2026-06-26
- 범위: 개인(B2C) 카드결제로 **검사권(크레딧)** 을 구매하고, 검사 응시 시 FIFO로 차감하는 커머스 레이어
- 선행: Phase 1 검사흐름(동의→안내→응시→채점→신호등결과)·5연령방·준비중카드 완료(브랜치 `feat/partial-implementation`)

## 확정된 결정 (브레인스토밍 2026-06-26)

1. **구매 모델 = 검사권(크레딧) 단디식**: 검사권 N장을 사고, 응시 때 1장씩 FIFO 차감. 무료·추천권이 유료권보다 먼저 소비.
2. **개인 카드결제 먼저**: 기관(B2B) 대량·세금계산서는 이후 Phase.
3. **포인트·추천정산(affiliate) 미룸**: 지금은 깨끗한 주문·결제·검사권만. 단디식 포인트/정산 라인은 다음 Phase.
4. **강의·코칭은 문의·신청만**: 결제 대상은 검사뿐. 코칭 온라인 결제 없음.
5. **장바구니 없음**: 검사 1개씩 단건 결제.
6. **가격은 products 테이블**: tests에 price 안 박음.
7. **무료 검사 = active product 없는 검사**: 게스트 응시 유지.
8. **검사권 유효기간 = 발급일 + 1년**.

### 비범위 (이번 설계 밖)
- 기관 대량 구매·코드 발급·세금계산서, 장바구니/다건, 포인트·추천정산, 강의코칭 결제, 관리자 환불 UI(스키마는 지원, UI는 Phase 1.5), 만료 cron(조회 필터로 대체).

## 데이터 모델 (신규 6 테이블 + test_attempts 변경)

> 가격은 원(KRW) 정수. Laravel 마이그레이션 기준.

### products — 검사권 상품
| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | id | |
| test_id | foreignId→tests, constrained, index | 어떤 검사의 검사권인가 |
| name | string | 예: "초등 마음안전선별검사 검사권" |
| price | unsignedInteger | 원(>0). 무료 검사는 product를 두지 않음(price 0 상품 만들지 않음) |
| credit_qty | unsignedSmallInteger default 1 | 1구매당 발급 검사권 장수(번들 대비) |
| valid_days | unsignedSmallInteger default 365 | 검사권 유효기간(일) |
| status | string default 'active' | active / hidden |
| timestamps | | |

- 한 검사에 product 0개 = **무료 검사**(게스트 응시). 1개 이상(price>0) = 유료.
- Phase 1은 검사당 product 0~1개, credit_qty=1.

### orders — 주문
| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | id | |
| order_no | string, unique | 표시·PG용. 예: `S20260626-AB12CD` |
| user_id | foreignId→users, constrained, index | 구매자(로그인 필수) |
| status | string default 'pending' | pending/paid/failed/canceled/refunded |
| total_amount | unsignedInteger | 주문 총액 |
| paid_at | timestamp nullable | |
| canceled_at | timestamp nullable | |
| timestamps | | |

### order_items — 주문 항목 (스냅샷)
| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | id | |
| order_id | foreignId→orders, cascadeOnDelete | |
| product_id | foreignId→products, nullOnDelete, nullable | 상품 삭제돼도 이력 유지 |
| test_id | unsignedBigInteger | 스냅샷(검사권 발급 대상) |
| product_name | string | 스냅샷 |
| unit_price | unsignedInteger | 스냅샷 단가 |
| quantity | unsignedSmallInteger default 1 | |
| credit_qty | unsignedSmallInteger default 1 | 스냅샷(항목당 발급 장수) |
| timestamps | | |

### payments — PG 결제 트랜잭션
| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | id | |
| order_id | foreignId→orders, cascadeOnDelete | |
| provider | string | inicis / kcp |
| method | string nullable | card 등 |
| pg_tid | string nullable, **unique** | PG 거래ID. 승인 후 채움. 멱등 키 |
| amount | unsignedInteger | PG 승인 금액 |
| status | string default 'ready' | ready/paid/failed/canceled/refunded |
| paid_at | timestamp nullable | |
| raw_response | json nullable | PG 원응답 보관 |
| timestamps | | |

- 1 order : N payment(재시도 대비). pg_tid unique = 동일 승인 이중 처리 차단.

### vouchers ★ — 검사권 1장 = 1행
| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | id | |
| user_id | foreignId→users, cascadeOnDelete | |
| test_id | foreignId→tests, constrained | 이 검사권으로 응시 가능한 검사 |
| order_item_id | foreignId→order_items, nullOnDelete, nullable | 무료/추천 발급은 null |
| source | string default 'purchase' | purchase/free/referral/admin |
| status | string default 'active' | active/used/expired/revoked |
| issued_at | timestamp | 발급일 |
| expires_at | timestamp nullable | issued_at + valid_days. null=무제한 |
| used_at | timestamp nullable | |
| used_attempt_id | unsignedBigInteger nullable | 소비 시 응시 연결 |
| timestamps | | |
| index | (user_id, test_id, status, issued_at) | FIFO 조회용 |

- **FIFO 소비 규칙**: `status=active AND (expires_at IS NULL OR expires_at > now)` 중 `issued_at ASC` 첫 장. source 무료/추천이 더 일찍 발급되면 자연히 먼저 소비.

### test_attempts — 변경(컬럼 추가)
- `voucher_id` foreignId→vouchers, nullOnDelete, nullable, after `test_id`. 무료/게스트 응시는 null.

## 모델 관계
- `Test` hasMany products, hasMany vouchers; 헬퍼 `activeProduct()`(최저가 active product) / `isPaid()`(active product price>0 존재).
- `Product` belongsTo test, hasMany orderItems.
- `Order` belongsTo user, hasMany orderItems, hasMany payments.
- `OrderItem` belongsTo order, belongsTo product, hasMany vouchers.
- `Payment` belongsTo order.
- `Voucher` belongsTo user/test/orderItem; belongsTo attempt(used).
- `User` hasMany orders, hasMany vouchers.
- `TestAttempt` belongsTo voucher.

## 서비스 (도메인 로직 격리)
- **PaymentGateway 인터페이스**: `begin(Order $o): array`(PG 결제창 파라미터/리다이렉트), `approve(array $return): PaymentResult`(인증결과→승인 호출, 금액·위변조 검증). 구현: **`InicisGateway`(KG이니시스, 1순위)** + 테스트용 `FakeGateway`. `KcpGateway`는 인터페이스 자리만 두고 향후. `config/services.php`에서 provider 선택.
- **CheckoutService**: `createOrder(User, Product, int $qty): Order` — order(pending)+order_item 스냅샷 생성, total 계산.
- **VoucherService**:
  - `issueForOrder(Order): void` — **멱등**. order=paid일 때 각 order_item마다 `credit_qty*quantity`장 발급. 이미 발급된 order_item이면 skip(이중 발급 차단).
  - `consume(User, Test, TestAttempt): Voucher` — FIFO 1장 used 처리 + attempt 연결. 없으면 예외.
  - `availableCount(User, Test): int` / `firstActive(User, Test): ?Voucher`.

## 화면·흐름 (개인)

### 검사 상세(`/tests/{code}`) 버튼 분기
| 상태 | 버튼 | 이동 |
|---|---|---|
| 비로그인 + 유료 | `로그인하고 구매` | login |
| 로그인 + 검사권 보유 | `검사 시작` | assessment.consent |
| 로그인 + 검사권 없음(유료) | `₩{price} 구매하고 응시` | checkout.show |
| 무료(active product 없음) | `검사 시작` | assessment.consent (게스트 OK) |

검사 카드/상세에 가격(또는 "무료") 표시.

### 결제 동선
```
/tests/{code} →[구매하고 응시]→ checkout.show(/checkout/{product})
   상품·금액·약관 동의
 →[결제하기 POST /checkout/{product}]→ CheckoutService.createOrder → order(pending)
 → PaymentGateway.begin → PG 결제창(INICIS/KCP)
 → 인증 완료 → return URL (/payment/return)
 → PaymentGateway.approve(금액·위변조 검증) → payment=paid, order=paid
 → VoucherService.issueForOrder (검사권 N장 active 발급, expires=now+valid_days)
 → /payment/complete/{order} : "검사 시작하기" → assessment.consent
취소/실패 → /payment/fail
```

### 응시 시 차감 — `AssessmentController::start` 재작성
1. 무료 검사(active product 없음) → 기존대로 attempt 생성(voucher_id null).
2. 유료 검사:
   - 비로그인 → login.
   - `VoucherService.firstActive` 없음 → `checkout.show`로.
   - 있음 → attempt 생성 후 `VoucherService.consume`(used + attempt 연결).
3. **보호자 동의 서버 강제 동시 해결**: `agree()`가 동의 통과 시 세션 플래그 `consent_ok:{code}`를 세팅하고, `start()`는 `requires_guardian_consent` 검사일 때 이 플래그를 확인 → 없으면 consent로 되돌림. 기존 하드 선결조건(start 직접호출로 동의 우회) 해소.

### 내 검사함(`/my`)
- 보유 검사권(active, 만료일) 목록 + 응시 이력 + "추가 구매" 링크.

## 엣지 케이스 (단디 교훈)
- **게스트**: 유료 검사 구매·응시 불가 → 로그인 유도. 무료 검사만 게스트 응시(현행 유지).
- **멱등성**: PG return 중복 호출 — `pg_tid` unique + order.status!=paid 가드 + `issueForOrder` 멱등(order_item 기발급 skip). (단디 gd_id 멱등 교훈)
- **금액 위변조**: 서버 order.total ↔ PG 승인 amount 일치 검증, 불일치 시 승인취소·실패.
- **만료**: `expires_at` 지난 검사권은 FIFO 조회에서 제외. 상태를 expired로 바꾸는 배치는 Phase 1 미포함(조회 필터로 충분, 단디 cron 보류와 일관).
- **환불**: payment=refunded → 해당 order의 **미사용(active) 검사권 revoke**. 한 트랜잭션·멱등. 관리자 UI는 Phase 1.5(스키마는 지원). (단디 환불 중첩BEGIN 교훈 — 단일 트랜잭션)
- **동시성**: `consume`은 트랜잭션 + 행 잠금(`lockForUpdate`)으로 같은 검사권 이중 차감 방지.

## PG 통합 메모
- **KG이니시스 1순위.** 단디 INICIS PHP 라이브러리를 Laravel Service로 래핑. 인증→승인 2단계.
- 개발·검증은 **테스트 상점아이디(`INIpayTest`)** 로 진행 — 실결제 MID 없이 전 흐름 구현 가능.
- 키/상점ID(MID)·signKey는 `.env`(`config/services.php` 경유). 테스트/운영 분리.
- 테스트(자동화)는 `FakeGateway`로 PG 미접속(실결제 호출 금지).
- **[외부 선결조건 — 코드와 무관]** 실결제는 심지 운영 도메인에 묶인 **별도 MID**가 필요. 심지가 단디와 동일 사업자번호면 기존 가맹점에 **추가 상점(MID) 등록 + 도메인 등록 + 카드사 재심사**, 다른 사업자면 신규 계약. 심사 시 이용약관·환불정책·개인정보처리방침·사업자정보 페이지 필요. 디지털상품(검사권) 구매안전(에스크로) 처리는 KG이니시스 가맹 담당 확인. → 도메인 오픈 후 `.env` MID 교체만 하면 전환.

## 테스트 전략 (Pest)
- **Unit**: VoucherService FIFO(무료→유료 순서/만료 제외/없으면 예외), issueForOrder 멱등, 금액 검증.
- **Feature**: 결제확인 렌더 / order(pending) 생성 / FakeGateway return→payment paid+검사권 발급 / **return 2회 호출 멱등**(검사권 중복 발급 0) / start가 검사권 차감 / 검사권 없이 start→checkout 리다이렉트 / 게스트 유료검사→login / 무료검사 게스트 응시 유지 / 보호자 동의 미통과 start→consent.
- 회귀: 기존 무료 샘플(KMSIA-SAMPLE) 흐름 — product 안 붙이면 그대로 무료.

## 마이그레이션/배포 영향
- 신규 6 테이블 + test_attempts 컬럼 추가. 운영 반영 시 `php artisan migrate`.
- `.env`에 PG 키 추가 필요(운영). 시더에 유료 product 예시 1개(샘플 검사용) 추가해 흐름 시연 가능.
- 기존 무료 응시 회귀 없음(product 없는 검사 = 무료 경로).

## 향후 Phase (의식만, 이번 비범위)
- 기관 B2B(대량 구매·코드 발급·세금계산서·집단 리포트), 장바구니/다건, 포인트·추천정산, 강의코칭 결제, 만료 cron, 관리자 환불·매출 통계.
