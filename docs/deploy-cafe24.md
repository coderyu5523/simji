# 심지 카페24 웹호스팅 배포 절차서

- 작성일: 2026-07-29
- 대상: 심지 Laravel 12 (PHP ^8.2) → 카페24 **웹호스팅**(공유). 가상서버(VPS) 아님
- 전제: 서버 상시 관리 인력이 없어 OS·방화벽·SSL을 호스팅사가 맡는 구성을 택했다

이 문서는 **처음 배포 1회분 + 이후 재배포**를 순서대로 담는다. 위에서부터 그대로 따라가면 된다.

---

## 0. 지금 코드가 공유호스팅에 올라갈 수 있는 근거

사전 조사 결과다. 다시 확인할 필요 없다.

| 항목 | 결과 |
|---|---|
| 큐 워커 · 스케줄러 · 메일 발송 · 파일 업로드 | 코드 0건 → 상주 프로세스 불필요 |
| `exec` / `proc_open` / `shell_exec` / `symlink` | 0건 → 공유호스팅 함수 제한에 안 걸림 |
| 원시 SQL(`DB::raw`/`DB::statement`) · SQLite 의존 | 0건 → MySQL 전환은 `.env` 수정만 |
| `public/.htaccess` | Laravel 표준본 존재. `mod_rewrite` 만 있으면 됨 |
| `bootstrap/app.php` | `trustProxies` 외 특수 설정 없음 (§7-5 에서 재검토) |

Laravel 이 공유호스팅에서 어려워지는 전형적 원인이 이 앱에는 없다.

---

## 1. 상품 사기 전에 카페24에 확인할 것

넷 다 "안 됨"이면 배포 방식을 바꿔야 하므로 **구매 전에** 확인한다.

1. **SSH 에서 `php artisan` 실행 가능한가**
   composer 는 `.phar` 라 막히는 것으로 알려져 있지만 `artisan` 은 평범한 PHP 파일이다.
   안 되면 마이그레이션을 SQL 로 뽑아 phpMyAdmin 에서 적용해야 한다(§6-대안).
2. **웹 루트(`www/`)와 같은 레벨에 디렉터리를 만들 수 있는가**
   앱 본체를 웹 루트 밖에 두는 구성(§3)의 전제다. 안 되면 `.env` 노출 위험을 다른 방법으로 막아야 한다.
3. **MySQL / MariaDB 버전** — `json` 컬럼을 3곳에서 쓴다
   (`tests.areas`, `scoring_rules.rules`, `consent_records.meta`).
   **MySQL 5.7 이상 또는 MariaDB 10.2 이상**이어야 한다.
4. **PHP 8.2 선택 가능한가** — `composer.json` 이 `"php": "^8.2"`, Laravel 12.
   (2026-07-14 확인 시점엔 카페24가 8.4/8.2/7.4 선택 가능했다. 상품별로 다를 수 있어 재확인)

상품은 SSH 가 제공되는 계열(`10G 광아우토반 FullSSD+` 등)을 본다.

---

## 2. 로컬에서 만들어 올릴 것 — ⚠️ 가장 흔한 사고 지점

`.gitignore` 에 다음 두 줄이 있다.

```
/public/build     ← npm run build 결과물 (CSS·JS)
/vendor           ← composer 의존성
```

**둘 다 git 에 없다.** 그런데 카페24엔 node 도 composer 도 없다.
저장소만 올리면 **스타일이 전부 깨진 맨 HTML** 이 뜬다.

로컬에서 먼저 만든다:

```bash
cd C:\work\심지\simji

# 1) 프론트 에셋 빌드 → public/build/ 생성
npm ci
npm run build

# 2) 운영용 의존성 설치 → vendor/ 생성 (--no-dev 주의: 테스트·faker 제외)
composer install --no-dev --optimize-autoloader
```

`--no-dev` 를 빼면 안 된다. `fakerphp/faker` 같은 개발 전용 패키지가 운영 서버에 올라간다.

> 로컬 개발을 계속하려면 배포용 `vendor/` 를 만든 뒤 `composer install` 로 되돌려 놓는다.

---

## 3. 디렉터리 구성

카페24 웹호스팅은 FTP 홈 아래에 `www/`(웹 루트)가 있는 구조다.
**앱 본체를 웹 루트 밖에 두어 `.env` 와 소스가 웹에서 열리지 않게 한다.**

```
~/                          ← FTP 홈
├── laravel/                ← 앱 본체 (웹에서 접근 불가)
│   ├── app/  bootstrap/  config/  database/  resources/  routes/  storage/
│   ├── vendor/             ← §2 에서 만든 것
│   └── .env                ← §4
└── www/                    ← 웹 루트. public/ 의 내용만 들어간다
    ├── .htaccess
    ├── index.php           ← §3-1 로 경로 수정
    ├── favicon.ico  robots.txt
    ├── images/
    └── build/              ← §2 에서 만든 public/build/ 의 내용
```

올리지 않는 것: `tests/`, `node_modules/`, `.git/`, `docs/`, `Dockerfile`, `render.yaml`,
`mockup/`, `package*.json`, `phpunit.xml`, `vite.config.js`

> `Dockerfile` 과 `render.yaml` 은 Render 프리뷰 전용이다. 카페24 배포엔 관여하지 않는다.

### 3-1. `www/index.php` 경로 수정

`public/index.php` 는 `__DIR__.'/../'` 로 앱을 찾는데, 이제 앱이 `../laravel/` 에 있다.
**3줄**을 고친다.

```php
// 수정 전                                        // 수정 후
__DIR__.'/../storage/framework/maintenance.php'   __DIR__.'/../laravel/storage/framework/maintenance.php'
__DIR__.'/../vendor/autoload.php'                 __DIR__.'/../laravel/vendor/autoload.php'
__DIR__.'/../bootstrap/app.php'                   __DIR__.'/../laravel/bootstrap/app.php'
```

심볼릭 링크(`ln -s laravel/public www`)로 처리하는 방법도 있으나
**웹호스팅에서 심링크를 인식하지 못한다는 보고가 있어** 경로 수정 방식을 쓴다.

### 3-2. 쓰기 권한

```bash
chmod -R 775 ~/laravel/storage ~/laravel/bootstrap/cache
```

Laravel 이 로그·세션 캐시·컴파일된 뷰를 여기에 쓴다. 권한이 없으면 500 이 뜬다.

---

## 4. `.env` 작성

`~/laravel/.env` 로 새로 만든다. **저장소의 값을 그대로 복사하지 않는다.**

```ini
APP_NAME=심지
APP_ENV=production
APP_DEBUG=false                    # ★ true 면 예외 시 스택트레이스가 그대로 노출된다
APP_KEY=                           # ★ §7-2 에서 새로 발급
APP_URL=https://simji.org

LOG_CHANNEL=stack
LOG_LEVEL=error                    # production 에서 debug 는 로그가 폭증한다

DB_CONNECTION=mysql                # ★ sqlite 아님
DB_HOST=<카페24가 준 DB 호스트>     # localhost 가 아닌 경우가 많다
DB_PORT=3306
DB_DATABASE=<DB명>
DB_USERNAME=<계정>
DB_PASSWORD=<비밀번호>

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database          # 실제로 job 을 넣는 코드가 없어 워커 불필요

FILESYSTEM_DISK=local
MAIL_MAILER=log                    # 메일 발송 코드가 아직 없다

# 카카오 로그인 (§8)
KAKAO_CLIENT_ID=<REST API 키>
KAKAO_CLIENT_SECRET=<시크릿>
KAKAO_REDIRECT_URI=https://simji.org/auth/kakao/callback
```

`.env` 는 절대 git 에 올리지 않는다(이미 `.gitignore` 에 있다).

---

## 5. 데이터베이스 구축

1. 카페24 관리에서 **MySQL DB 생성**
2. **문자셋 `utf8mb4` / 정렬 `utf8mb4_unicode_ci`**
   한글 문항과 청소년이 입력하는 닉네임이 들어간다. `utf8` (3byte) 로 만들면 나중에 깨진다.
3. 접속정보를 §4 `.env` 에 기입

---

## 6. 마이그레이션 · 시더

SSH 로 접속해 `~/laravel/` 에서 실행한다.

```bash
cd ~/laravel

php artisan key:generate --force        # APP_KEY 새로 발급 (§7-2)
php artisan migrate --force             # 마이그레이션 22개 적용
```

> ### ⛔ `migrate:fresh` 를 쓰지 않는다
> 전 테이블을 DROP 한다. Render 프리뷰가 기동마다 이걸 실행해 DB를 날리던 명령이다.
> 운영에서는 `migrate` 만 쓴다.

### 시더 — 반드시 개별 실행

```bash
php artisan db:seed --class="Database\Seeders\OyMsi\TestSeeder" --force
php artisan db:seed --class="Database\Seeders\OyMsi\ScoringRuleSeeder" --force
php artisan db:seed --class="Database\Seeders\OyMsi\TemplateSeeder" --force
```

> ### ⛔ `php artisan db:seed` 를 통째로 돌리지 않는다
> `DatabaseSeeder` 는 **`test@example.com` 계정**(factory 기본 비밀번호)과
> 샘플 검사 2종(`KMSIA-SAMPLE`, `KPAID-SAMPLE`)을 만든다. 운영에 들어가면 사실상 백도어다.

위 시더 3개는 전부 멱등이다(이미 있으면 return / `updateOrCreate`). 재실행해도 중복되지 않는다.

### 6-대안. `php artisan` 이 안 될 경우

1. 로컬에서 빈 MySQL DB에 `php artisan migrate` 실행 → `mysqldump` 로 구조 추출
2. 시더 3개까지 실행한 상태를 덤프
3. phpMyAdmin 에서 import
4. `APP_KEY` 는 로컬에서 `php artisan key:generate --show` 로 뽑아 `.env` 에 붙여넣기

---

## 7. 배포 전 보안 체크리스트

프리뷰 설정 잔재를 걷어내는 단계다. **하나도 건너뛰지 않는다.**

| # | 항목 | 조치 |
|---|---|---|
| 7-1 | `APP_DEBUG` | `false`. 로그인 불필요한 보호자 링크(`/r/{token}`)에서 예외가 나면 스택트레이스와 요청 데이터가 열람자에게 렌더된다 |
| 7-2 | `APP_KEY` | `key:generate` 로 **새로 발급**. 기존 키는 저장소에 커밋돼 있어 폐기 대상 |
| 7-3 | `/admin/*` | **현재 무인증 공개**(`routes/web.php`). `auth` 미들웨어 추가 필요. 회원·주문 정보가 열린다 |
| 7-4 | HTTPS | 카페24 무료 SSL 적용. `APP_URL` 도 `https://` |
| 7-5 | `trustProxies(at: '*')` | `bootstrap/app.php` 의 Render 전용 설정. 모든 프록시를 신뢰하면 헤더 위조로 scheme·클라이언트 IP 가 조작될 수 있다. 카페24가 프록시 뒤인지 확인해 필요 없으면 제거 |
| 7-6 | `www/` 노출 확인 | `https://simji.org/.env`, `/vendor/`, `/storage/logs/` 가 **404 여야 한다** |
| 7-7 | 캐시 | `php artisan config:cache && php artisan route:cache && php artisan view:cache` |

---

## 8. 카카오 로그인 재설정

도메인이 바뀌므로 카카오 개발자 콘솔에서 다시 등록한다.

- **Redirect URI**: `https://simji.org/auth/kakao/callback`
- 사이트 도메인에 `https://simji.org` 추가
- `.env` 의 `KAKAO_*` 값 확인

---

## 9. 동작 확인 (스모크 테스트)

배포 직후 브라우저로 확인한다.

1. `https://simji.org/` — **스타일이 정상인가** (깨져 보이면 §2 의 `public/build` 누락)
2. `/up` — Laravel 헬스체크가 200
3. 회원가입 → 로그인 → 카카오 로그인
4. `/my` — 내 검사함이 열리는가
5. 검사권 발급 → 링크 복사 → 시크릿창에서 링크 응시 → 결과 표시
6. `/.env` 접근 시 **404**
7. `/admin` — 7-3 조치 후 로그인 없이는 못 들어가는가

---

## 10. ⚠️ OY_MSI(청소년 마음상태검사) 공개는 **별도 결정**

이 검사는 `status='draft'` 로 들어간다. 배포한다고 자동으로 열리지 않는다.

**중요 — `status='draft'` 는 접근 차단이 아니다.** 목록에서 숨기는 필터일 뿐이라
(`CatalogController.php:18`, `HomeController.php:11`), **로그인한 사람이 주소를 직접 입력하면
전 흐름을 완주하고 보호자 링크까지 발급할 수 있다.** 검증자 외 접근을 막으려면 라우트 가드가 별도로 필요하다.

공개(`status='active'`) 전에 결정해야 할 것:

1. **안전 대응 체계** — 003 Ⅸ 가 "안전문항 양성반응에 대응할 훈련된 담당자가 없는 환경에서는
   시행 금지"로 규정한다. 이 검사는 미성년자에게 자살 계획·자해 경험을 직접 묻는다
   (`SAF01`~`SAF06`). 양성 반응이 나왔을 때 **누가, 얼마 안에, 어떻게** 연락하는지가 정해져 있어야 한다.
   설계상 2단계(기관 경보 워크플로)가 이 역할인데 **아직 구현되지 않았다.**
2. **검사 저자 확인 6건** — `docs/oy-msi-handover.md` §2. 환경위험 문안 등 일부는 원문에 근거가
   없어 파생 작성한 것이다. 가장 위험한 상태의 청소년이 읽는 화면이다.
3. **법무 검토** — 기관 오프라인 동의의 개인정보보호법 §22-2(만 14세 미만 법정대리인 동의) 충족 여부.

**나머지 검사(기존 심지 검사)는 이 결정과 무관하게 서비스 가능하다.**

---

## 11. 백업 · 운영

심지가 저장하는 것 중:

| 테이블 | 내용 |
|---|---|
| `consent_records` | 법정대리인 동의의 **법적 증거** |
| `attempt_answers` | 미성년자의 **자살·자해 문항 응답** |
| `test_attempts` · `test_results` | 채점 결과, 안전등급 |

- **정기 백업 필수** — 카페24 백업 서비스 또는 크론 `mysqldump`
- **백업 파일 자체가 민감정보다.** 보관 위치·접근 권한·보관 기간을 정한다
- 개인정보 파기 정책(보관 기간 경과 시)도 함께 정한다

---

## 12. 이후 재배포

코드만 바뀐 경우:

```bash
# 로컬
npm run build                                    # 프론트가 바뀌었을 때만
composer install --no-dev --optimize-autoloader  # 의존성이 바뀌었을 때만
```

```
FTP 업로드 → 바뀐 파일 (+ 필요 시 public/build/, vendor/)
```

```bash
# 서버
cd ~/laravel
php artisan migrate --force        # 마이그레이션이 추가됐을 때만
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`config:cache` 를 한 상태에서 `.env` 를 고치면 **반영되지 않는다.** 고쳤으면 캐시를 다시 만든다.

---

## 관련 문서

- `docs/oy-msi-handover.md` — OY_MSI 1단계 인수인계 (§10 의 근거)
- `docs/oy-msi-manual-verification.md` — 수동 검증 체크리스트
- `docs/superpowers/specs/2026-07-29-oy-msi-guardian-confirm-design.md` — 만 13세 담당자확인 경로
