# 목업 화면 → 테이블 구조 설계 초안

작성 2026-07-01. **지금 만든 목업 화면(백엔드 미연결)들을 나중에 실제로 붙일 때** 필요한 테이블 초안.
아직 마이그레이션 만들지 않음 — 필요할 때 이 문서 보고 바로 생성.

전제:
- 전부 기존 16개 테이블(users/tests/orders…)과 **독립적인 추가 테이블**. 기존 구조 안 건드림.
- Laravel 마이그레이션은 가산적 → 언제든 `php artisan make:migration` 으로 추가 가능.
- 대부분 "리드 수집(문의/신청)" 또는 "콘텐츠(전문가/프로그램/FAQ)" 성격 → 관리자 페이지(`/admin`)에 목록/처리 화면으로 이어짐.
- 연령방(room)은 `App\Support\Rooms` 코드값 저장: `elem·middle·univ·worker·silver`.

---

## 1. `institution_inquiries` — 기관 도입 문의
화면: `/institution` 문의 폼

| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | bigint PK | |
| org_type | string(20) | 학교/대학교/기업/복지관/공공기관/상담센터 |
| organization | string | 기관명 |
| manager_name | string | 담당자명 |
| phone | string(20) | |
| email | string, nullable | |
| message | text, nullable | 문의 내용 |
| status | string(20), default 'new' | new / contacted / done (관리자 처리) |
| user_id | FK→users, nullable | 로그인 상태면 연결 |
| timestamps | | |

구현: migration + `InstitutionInquiry` 모델 + institution 폼 `@submit` → POST 저장 + `/admin/inquiries` 목록.

---

## 2. `experts` — 전문가 소개(디렉토리)
화면: `/experts` 전문가 카드

| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | bigint PK | |
| name | string | 이름 |
| field | string | 전문분야(아동상담·진로코칭…) |
| intro | text | 한줄 소개 |
| region | string(30) | 지역 |
| tags | json | 태그 배열 |
| photo | string, nullable | 사진 경로(없으면 이니셜) |
| is_active | bool, default true | 노출 여부 |
| sort_order | int, default 0 | 정렬 |
| timestamps | | |

구현: migration + `Expert` 모델 + 시더(초기 전문가) + experts 뷰가 배열 대신 `Expert::active()` 조회 + `/admin/experts` 관리.

---

## 3. `expert_requests` — 전문가 연결 신청
화면: `/experts` 연결 신청 폼

| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | bigint PK | |
| concern | string | 고민 카테고리 |
| region | string(30) | 희망 지역 |
| phone | string(20) | |
| name | string, nullable | |
| user_id | FK→users, nullable | 로그인 시 |
| expert_id | FK→experts, nullable | 나중에 매칭된 전문가 |
| status | string(20), default 'new' | new / matched / done |
| timestamps | | |

구현: migration + `ExpertRequest` 모델 + 폼 POST 저장 + `/admin/expert-requests` 처리(전문가 매칭).

---

## 4. `coaching_programs` — 강의·코칭 프로그램
화면: `/coaching` 프로그램 카드 (현재 Blade 배열 하드코딩)

| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | bigint PK | |
| room | string(20) | 대상 연령방 코드 |
| type | string(30) | 특강/집단상담/부모교육/1:1코칭/기관패키지 |
| name | string | 프로그램명 |
| description | text | 핵심 내용 |
| price | int, nullable | 유료화 시 |
| is_active | bool, default true | |
| sort_order | int, default 0 | |
| timestamps | | |

구현: migration + `CoachingProgram` 모델 + 시더 + coaching 뷰가 DB 조회 + `/admin/coaching` 관리. (검사 결과→코칭 연계 시 여기 참조)

---

## 5. `faqs` — 고객센터 FAQ (선택)
화면: `/support` FAQ (현재 하드코딩)

| 컬럼 | 타입 | 비고 |
|---|---|---|
| id | bigint PK | |
| category | string(30), nullable | 검사/결제/기관… |
| question | string | |
| answer | text | |
| is_active | bool, default true | |
| sort_order | int, default 0 | |
| timestamps | | |

구현: 관리자가 FAQ 편집하게 할 때만. 아니면 하드코딩 유지해도 무방(우선순위 낮음).

---

## 6. (선택) 파트너 합류 / 게시판
- **파트너 합류 문의**(`/experts` 하단 CTA): 별도 테이블 대신 `institution_inquiries`에 `inquiry_type`('institution'|'partner') 컬럼 하나 추가해 재사용 권장.
- **게시판(뉴스·FAQ·자료실·QnA)**: 도입 확정 시 `boards`+`posts`(+`attachments`) 표준 게시판 스키마 별도 설계. (별도 문서로 분리 권장)

---

## 정리 — 우선순위
| 테이블 | 성격 | 우선순위 |
|---|---|---|
| institution_inquiries | 기관 리드 수집 | 🟢 높음(기관 주고객) |
| expert_requests | 전문가 연결 리드 | 🟡 중 |
| experts | 전문가 콘텐츠 | 🟡 중 |
| coaching_programs | 콘텐츠 DB화 | 🟡 중 |
| faqs | 콘텐츠 DB화 | 🔴 낮음(하드코딩 가능) |

모두 기존 스키마와 독립적이라, **협의로 "이 기능 한다" 나온 것부터 하나씩** 이 표대로 migration 뽑아 붙이면 됨.
관리자(`/admin`)엔 각 테이블의 목록/처리 화면(문의 관리·전문가 신청 관리 등)을 탭으로 추가.
