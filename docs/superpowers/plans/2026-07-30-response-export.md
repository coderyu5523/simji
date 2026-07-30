# 응답 데이터 추출 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 검사 응답을 CSV 로 내보낸다 — 연구용(관리자·비식별·SAF 포함)과 기관용(담당자·자기 발급분·SAF 문항 제외) 두 갈래로. `/admin/*` 인증 가드를 함께 넣는다.

**Architecture:** `ResponseExporter` 서비스 하나가 두 프로필의 컬럼 구성만 다르게 조립한다. 검사 종류를 모른다 — 문항은 `test->items`, 제외 대상은 채점 룰의 `safety_items` 키에서 읽는다. 스트리밍 응답으로 내보내 응시가 쌓여도 메모리에 다 올리지 않는다. 관리자는 신규 `users.is_admin` 으로 식별하고 `EnsureAdmin` 미들웨어가 `/admin/*` 전체를 막는다.

**Tech Stack:** Laravel 12, PHP 8.2, Pest (Feature 테스트는 `RefreshDatabase`), Blade, Tailwind

**설계 문서:** `docs/superpowers/specs/2026-07-30-response-export-design.md`

## Global Constraints

- **CSV 는 UTF-8 BOM** (`\xEF\xBB\xBF`) 으로 시작한다 — 엑셀에서 한글이 깨지지 않게.
- **미응답은 빈칸(`null`)이다. `0` 으로 채우지 않는다.** "답 안 함"과 "전혀 아니다(0점)"는 다른 값이고, 섞이면 절단점 계산이 틀어진다.
- **문항 값은 역채점 전 원점수**(`attempt_answers.value` 를 그대로) 를 쓴다. 채점용으로 뒤집힌 값을 내보내면 원자료가 아니게 된다.
- **SAF 제외 대상을 하드코딩하지 않는다.** `$test->scoringRule?->rules['safety_items'] ?? []` 를 읽는다. 이 키가 없는 검사는 제외 없이 전부 나간다.
- **연구용에 이름을 넣지 않는다** — `test_attempts.nickname`, `vouchers.recipient_name` 둘 다.
- 제출 완료(`test_attempts.status === 'submitted'`)만 내보낸다.
- 커밋 메시지는 한국어. 기존 관례를 따른다(`feat(export): …`, `fix: …`).
- 테스트는 Pest 의 `test('...', function () {...})` 형식. 한국어 제목.

---

### Task 1: 관리자 식별 + `/admin/*` 인증 가드

지금 `/admin/*` 은 무인증 공개다(`routes/web.php:114` 주석에도 명시). 여기에 응답 추출을 붙이면 자살·자해 응답이 공개 URL 로 나간다. **추출보다 먼저 막는다.**

관리자 식별 수단이 없다 — `users.user_type` 은 `personal | institution` 둘뿐이다. `is_admin` 을 새로 추가한다. `user_type` 에 값을 끼우지 않는 이유는 그것이 가입 유형이라는 다른 축이고 화면 분기에 이미 쓰이기 때문이다.

**Files:**
- Create: `database/migrations/2026_07_30_000001_add_is_admin_to_users_table.php`
- Modify: `app/Models/User.php` (`$fillable`, `casts()`)
- Create: `app/Http/Middleware/EnsureAdmin.php`
- Modify: `bootstrap/app.php` (미들웨어 별칭 등록)
- Modify: `routes/web.php:114-120` (관리자 그룹에 미들웨어 적용)
- Test: `tests/Feature/Admin/AdminGuardTest.php`

**Interfaces:**
- Consumes: 없음 (첫 태스크)
- Produces: `users.is_admin` (boolean, default false) · 미들웨어 별칭 `'admin'` — Task 3 이 `/admin/exports/{test:code}` 라우트에 쓴다.

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/Feature/Admin/AdminGuardTest.php`:

```php
<?php
use App\Models\User;

test('비로그인은 관리자 화면에 접근할 수 없다', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('일반 회원은 관리자 화면에 접근할 수 없다', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('관리자는 접근할 수 있다', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('관리자 하위 화면도 모두 막힌다', function () {
    $member = User::factory()->create();

    foreach (['/admin/members', '/admin/orders', '/admin/tests'] as $path) {
        $this->actingAs($member)->get($path)->assertForbidden();
    }
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test tests/Feature/Admin/AdminGuardTest.php`
Expected: FAIL — 지금은 가드가 없어 `assertForbidden` 대신 200 이 오고, `is_admin` 컬럼이 없어 factory 생성에서도 깨진다.

- [ ] **Step 3: 마이그레이션 작성**

`database/migrations/2026_07_30_000001_add_is_admin_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // 관리자 권한. user_type(personal|institution)은 가입 유형이라는 다른 축이므로
            // 거기에 값을 끼우지 않고 직교하는 플래그로 둔다.
            $t->boolean('is_admin')->default(false)->after('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('is_admin');
        });
    }
};
```

- [ ] **Step 4: User 모델에 반영**

`app/Models/User.php` — `$fillable` 에 `'is_admin'` 을 추가하고, casts 에 `'is_admin' => 'boolean'` 을 추가한다. (`'user_type'`, `'organization'` 이 이미 `$fillable` 에 있으니 그 아래에 둔다.)

- [ ] **Step 5: 미들웨어 작성**

`app/Http/Middleware/EnsureAdmin.php`:

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 관리자 전용 화면 가드.
 *
 * 비로그인은 auth 미들웨어가 로그인 화면으로 보내고, 여기서는 "로그인은 했지만
 * 관리자가 아닌" 경우만 403 으로 막는다. 404 로 숨기지 않는 이유는 관리자 화면의
 * 존재 자체가 비밀이 아니고, 403 이 운영 중 오진단을 줄이기 때문이다.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return $next($request);
    }
}
```

- [ ] **Step 6: 별칭 등록**

`bootstrap/app.php` 의 `->withMiddleware(function (Middleware $middleware): void {` 블록 안, 기존 `trustProxies` 아래에 추가:

```php
        $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class]);
```

- [ ] **Step 7: 라우트에 적용**

`routes/web.php:114-120` 을 통째로 교체한다. 기존 경고 주석도 해소한다:

```php
// 관리자 — 로그인 + is_admin 필요. 관리자 임명은 SQL/커맨드로 한다(임명 UI 는 아직 없음).
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')
    ->controller(AdminController::class)->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/members', 'members')->name('members');
        Route::get('/orders', 'orders')->name('orders');
        Route::get('/tests', 'tests')->name('tests');
    });
```

`app/Http/Controllers/AdminController.php` 상단의 `// ⚠️ 임시 관리자 — 인증 가드 없음(직접 URL 접근). 실서비스 전 반드시 auth+권한 미들웨어 적용.` 주석을 지운다. 더 이상 사실이 아니다.

- [ ] **Step 8: 통과 확인**

Run: `php artisan test tests/Feature/Admin/AdminGuardTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: 가드가 실제로 작동하는지 뮤테이션 확인**

`routes/web.php` 에서 `'admin'` 미들웨어만 잠시 빼고 다시 돌린다.

Run: `php artisan test tests/Feature/Admin/AdminGuardTest.php`
Expected: FAIL — "일반 회원은 접근할 수 없다"가 깨져야 한다. 안 깨지면 테스트가 공허한 것이니 테스트를 고친다.

확인 후 `'admin'` 을 **되돌린다.**

- [ ] **Step 10: 전체 테스트로 회귀 확인**

Run: `php artisan test`
Expected: 기존 테스트 전부 통과. 관리자 화면을 건드리던 테스트가 있으면 `is_admin` 계정으로 고친다.

- [ ] **Step 11: 커밋**

```bash
git add database/migrations/2026_07_30_000001_add_is_admin_to_users_table.php app/Models/User.php app/Http/Middleware/EnsureAdmin.php bootstrap/app.php routes/web.php app/Http/Controllers/AdminController.php tests/Feature/Admin/AdminGuardTest.php
git commit -m "feat(admin): 관리자 인증 가드 + users.is_admin

/admin/* 이 무인증 공개였다. 응답 추출을 붙이기 전에 먼저 막는다.
관리자 식별 수단이 없어 is_admin 을 추가한다 — user_type 은 가입 유형이라는
다른 축이라 거기에 값을 끼우지 않는다."
```

---

### Task 2: `ResponseExporter` — 컬럼 조립

CSV 한 줄을 만드는 서비스. 라우트·화면 없이 이것만 먼저 테스트한다. 검사 종류를 모르게 만드는 것이 핵심이다.

**Files:**
- Create: `app/Services/Export/ResponseExporter.php`
- Test: `tests/Feature/Export/ResponseExporterTest.php`

**Interfaces:**
- Consumes: Task 1 없음 (독립)
- Produces:
  - `ResponseExporter::PROFILE_RESEARCH = 'research'` / `PROFILE_INSTITUTION = 'institution'`
  - `headers(Test $test, string $profile): array<int, string>`
  - `row(TestAttempt $attempt, Test $test, string $profile): array<int, string|int|null>`
  - `filename(Test $test, string $profile): string`
  - Task 3·4 의 컨트롤러가 이 셋을 쓴다.

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/Feature/Export/ResponseExporterTest.php`:

```php
<?php
use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Export\ResponseExporter;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->exporter = new ResponseExporter();
});

/**
 * 응답이 실제로 들어 있는 제출 응시를 만든다.
 * $answers 는 item_code => value. 넘기지 않은 문항은 미응답으로 남는다.
 */
function exportAttempt(Test $test, array $answers, ?User $issuer = null, ?string $recipientName = null): TestAttempt
{
    $voucher = null;
    if ($issuer) {
        $voucher = Voucher::create([
            'user_id' => $issuer->id, 'test_id' => $test->id,
            'source' => 'issued_free', 'status' => 'used',
            'issued_at' => now(), 'assigned_at' => now(),
            'access_token' => 'tok-'.uniqid(),
            'recipient_name' => $recipientName,
        ]);
    }

    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.uniqid(),
        'test_id' => $test->id, 'voucher_id' => $voucher?->id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 16, 'gender' => 'male', 'nickname' => '민수',
        'assessment_version' => '1.0.1', 'scoring_version' => '1.0.0',
    ]);

    $itemsByCode = $test->items->keyBy('item_code');
    foreach ($answers as $code => $value) {
        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'test_item_id' => $itemsByCode[$code]->id,
            'value' => $value,
        ]);
    }

    TestResult::create([
        'attempt_id' => $attempt->id,
        'area_scores' => ['DEP' => 12.0], 'area_signals' => ['DEP' => 'red'],
        'recommendations' => [], 'overall_level' => 'high', 'overall_signal' => 'red',
        'interpretation' => '',
        'safety_level' => 'S3', 'environment_level' => 'E0',
        'general_case_code' => 'R1', 'final_case_code' => 'C3', 'score_status' => 'COMPLETE',
        'engine_result' => ['factors' => [
            'DEP' => ['raw' => 12.0, 'band' => 'RED'],
            'SAF' => ['raw' => 6.0, 'band' => 'RED'],
        ]],
    ]);

    if ($voucher) $voucher->update(['used_attempt_id' => $attempt->id, 'used_at' => now()]);

    return $attempt->fresh();
}

test('연구용 헤더에 SAF 문항이 있다', function () {
    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);

    expect($headers)->toContain('SAF06')->toContain('DEP01');
});

test('기관용 헤더에 SAF 문항이 없다', function () {
    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('SAF06')
        ->not->toContain('SAF01')
        ->toContain('DEP01');   // 다른 문항은 남아 있어야 제외가 과하지 않음을 보인다
});

test('기관용은 SAF 응답이 실제로 있어도 값이 나오지 않는다', function () {
    // 부재 단언이 공허하지 않으려면 SAF 응답이 실제로 존재해야 한다.
    $attempt = exportAttempt($this->test, ['DEP01' => 2, 'SAF06' => 3]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    expect($byColumn)->not->toHaveKey('SAF06');
    expect($byColumn['DEP01'])->toBe(2);
});

test('연구용은 SAF 응답 값을 그대로 내보낸다', function () {
    $attempt = exportAttempt($this->test, ['SAF06' => 3]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['SAF06'])->toBe(3);
});

test('미응답은 빈칸이고 0 이 아니다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 0]);   // DEP02 는 미응답

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['DEP01'])->toBe(0);      // 실제 0점 응답
    expect($byColumn['DEP02'])->toBeNull();   // 미응답 — 0 이면 안 된다
});

test('역채점 문항도 역채점 전 원점수로 나온다', function () {
    // FUT04 는 reverse=true 인 긍정 문항. 저장된 값 그대로여야 한다.
    $attempt = exportAttempt($this->test, ['FUT04' => 3]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    expect($this->test->items->firstWhere('item_code', 'FUT04')->reverse)->toBeTrue();
    expect($byColumn['FUT04'])->toBe(3);   // 역채점된 0 이 아니다
});

test('연구용에 이름이 없다', function () {
    $issuer = User::factory()->create();
    $attempt = exportAttempt($this->test, ['DEP01' => 1], $issuer, recipientName: '홍길동');

    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);

    expect($row)->not->toContain('홍길동')->not->toContain('민수');
});

test('기관용에 응시자 이름이 있다', function () {
    $issuer = User::factory()->create();
    $attempt = exportAttempt($this->test, ['DEP01' => 1], $issuer, recipientName: '홍길동');

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['응시자'])->toBe('홍길동');
});

test('연구용 영역 점수는 SAF 를 포함한다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_RESEARCH);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_RESEARCH);
    $byColumn = array_combine($headers, $row);

    // engine_result.factors 에서 읽는다 — area_scores 컬럼은 SAF 가 빠져 있어 불완전하다
    expect($byColumn['SAF_raw'])->toBe(6.0);
    expect($byColumn['DEP_raw'])->toBe(12.0);
});

test('기관용 영역 점수에는 SAF 가 없다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->not->toContain('SAF_raw')->toContain('DEP_raw');
});

test('기관용 안전등급은 즉시·당일로 표기된다', function () {
    $attempt = exportAttempt($this->test, ['DEP01' => 1]);   // safety_level = S3

    $headers = $this->exporter->headers($this->test, ResponseExporter::PROFILE_INSTITUTION);
    $row = $this->exporter->row($attempt, $this->test, ResponseExporter::PROFILE_INSTITUTION);
    $byColumn = array_combine($headers, $row);

    expect($byColumn['안전확인'])->toBe('즉시');
});

test('safety_items 키가 없는 검사는 제외 없이 전부 나간다', function () {
    $rule = $this->test->scoringRule;
    $rules = $rule->rules;
    unset($rules['safety_items']);
    $rule->update(['rules' => $rules]);

    $headers = $this->exporter->headers($this->test->fresh(), ResponseExporter::PROFILE_INSTITUTION);

    expect($headers)->toContain('SAF06');
});

test('파일명에 검사 코드와 용도가 들어간다', function () {
    $name = $this->exporter->filename($this->test, ResponseExporter::PROFILE_RESEARCH);

    expect($name)->toContain('OY_MSI')->toEndWith('.csv');
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test tests/Feature/Export/ResponseExporterTest.php`
Expected: FAIL — `Class "App\Services\Export\ResponseExporter" not found`

- [ ] **Step 3: 서비스 구현**

`app/Services/Export/ResponseExporter.php`:

```php
<?php
namespace App\Services\Export;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\OyMsi\SafetyAlert;
use InvalidArgumentException;

/**
 * 검사 응답을 CSV 행으로 조립한다.
 *
 * 이 클래스는 검사 종류를 모른다 — 문항은 test->items, 제외 대상은 채점 룰의
 * safety_items 키에서 읽는다. 검사가 늘어도 여기를 고치지 않는다.
 *
 * 두 프로필의 차이는 설계 문서(2026-07-30-response-export-design.md) 참조:
 *   연구용   — 비식별, SAF 포함, 영역 점수를 engine_result.factors 에서 읽음
 *   기관용   — 이름 포함, SAF 문항·영역 제외, 영역 점수를 area_scores 에서 읽음
 */
class ResponseExporter
{
    public const PROFILE_RESEARCH = 'research';
    public const PROFILE_INSTITUTION = 'institution';

    public function __construct(private SafetyAlert $safety = new SafetyAlert()) {}

    /** @return array<int, string> */
    public function headers(Test $test, string $profile): array
    {
        $this->assertProfile($profile);

        $itemCodes = $this->itemColumns($test, $profile);

        if ($profile === self::PROFILE_RESEARCH) {
            return array_merge(
                ['attempt_id', 'test_code', 'assessment_version', 'scoring_version',
                 'submitted_at', 'age_at_test', 'gender'],
                $itemCodes,
                $this->factorColumns($test, $profile),
                ['overall_signal', 'safety_level', 'environment_level',
                 'general_case_code', 'final_case_code', 'score_status'],
            );
        }

        return array_merge(
            ['응시자', '발급일', '제출일', '연령', '성별'],
            $itemCodes,
            $this->factorColumns($test, $profile),
            ['종합신호등', '안전확인', '환경위험'],
        );
    }

    /** @return array<int, string|int|float|null> */
    public function row(TestAttempt $attempt, Test $test, string $profile): array
    {
        $this->assertProfile($profile);

        $attempt->loadMissing('answers', 'result', 'voucher');
        $test->loadMissing('items');

        $itemsById = $test->items->keyBy('id');
        $answersByCode = [];
        foreach ($attempt->answers as $answer) {
            $item = $itemsById[$answer->test_item_id] ?? null;
            if (!$item) continue;
            // 역채점 전 원점수를 그대로 쓴다. 채점용으로 뒤집힌 값을 내보내면 원자료가 아니다.
            $answersByCode[$this->columnFor($item)] = $answer->value === null ? null : (int) $answer->value;
        }

        // 미응답은 빈칸으로 남긴다 — 0 으로 채우면 "전혀 아니다(0점)"와 구분이 사라진다.
        $itemValues = [];
        foreach ($this->itemColumns($test, $profile) as $column) {
            $itemValues[] = $answersByCode[$column] ?? null;
        }

        $result = $attempt->result;

        if ($profile === self::PROFILE_RESEARCH) {
            return array_merge(
                [
                    $attempt->id,
                    $test->code,
                    $attempt->assessment_version,
                    $attempt->scoring_version,
                    optional($attempt->submitted_at)->format('Y-m-d H:i:s'),
                    $attempt->age_at_test,
                    $attempt->gender,
                ],
                $itemValues,
                $this->factorValues($test, $profile, $result),
                [
                    $result?->overall_signal,
                    $result?->safety_level,
                    $result?->environment_level,
                    $result?->general_case_code,
                    $result?->final_case_code,
                    $result?->score_status,
                ],
            );
        }

        $tier = $this->safety->safetyTier($result);

        return array_merge(
            [
                $attempt->voucher?->recipient_name ?: $attempt->nickname,
                optional($attempt->voucher?->assigned_at)->format('Y-m-d'),
                optional($attempt->submitted_at)->format('Y-m-d'),
                $attempt->age_at_test,
                $attempt->gender,
            ],
            $itemValues,
            $this->factorValues($test, $profile, $result),
            [
                $result?->overall_signal,
                match ($tier) {
                    SafetyAlert::URGENT => '즉시',
                    SafetyAlert::SAMEDAY => '당일',
                    default => null,
                },
                $this->safety->hasEnvironmentAlert($result) ? '확인' : null,
            ],
        );
    }

    public function filename(Test $test, string $profile): string
    {
        $this->assertProfile($profile);
        $label = $profile === self::PROFILE_RESEARCH ? 'research' : 'roster';

        return sprintf('%s_%s_%s.csv', $test->code, $label, now()->format('Ymd'));
    }

    /**
     * 기관용에서 뺄 문항 코드. 채점 룰에서 읽는다 — 하드코딩하지 않는다.
     *
     * @return array<int, string>
     */
    private function excludedItemCodes(Test $test, string $profile): array
    {
        if ($profile === self::PROFILE_RESEARCH) return [];

        $test->loadMissing('scoringRule');

        return $test->scoringRule?->rules['safety_items'] ?? [];
    }

    /** @return array<int, string> */
    private function itemColumns(Test $test, string $profile): array
    {
        $test->loadMissing('items');
        $excluded = $this->excludedItemCodes($test, $profile);

        return $test->items
            ->reject(fn ($item) => in_array($item->item_code, $excluded, true))
            ->map(fn ($item) => $this->columnFor($item))
            ->values()->all();
    }

    /** item_code 가 없는 검사(레거시 샘플)도 열이 무너지지 않게 문항 번호로 대체한다. */
    private function columnFor($item): string
    {
        return $item->item_code ?: 'Q'.$item->no;
    }

    /**
     * 영역 점수 컬럼. 연구용은 engine_result.factors(SAF 포함), 기관용은 area_scores(SAF 제외).
     *
     * @return array<int, string>
     */
    private function factorColumns(Test $test, string $profile): array
    {
        $test->loadMissing('scoringRule');
        $factors = array_keys($test->scoringRule?->rules['factors'] ?? []);

        if ($profile === self::PROFILE_INSTITUTION) {
            $factors = array_values(array_filter(
                $factors,
                fn ($code) => $test->scoringRule?->rules['factors'][$code]['included_in_overall'] ?? false
            ));
        }

        $columns = [];
        foreach ($factors as $code) {
            $columns[] = $code.'_raw';
            $columns[] = $code.'_band';
        }

        return $columns;
    }

    /** @return array<int, string|float|null> */
    private function factorValues(Test $test, string $profile, $result): array
    {
        $test->loadMissing('scoringRule');
        $engineFactors = $result?->engine_result['factors'] ?? [];
        $areaScores = $result?->area_scores ?? [];
        $areaSignals = $result?->area_signals ?? [];

        $values = [];
        foreach ($this->factorColumns($test, $profile) as $column) {
            [$code, $kind] = explode('_', $column, 2);

            if ($profile === self::PROFILE_RESEARCH) {
                $values[] = $kind === 'raw'
                    ? ($engineFactors[$code]['raw'] ?? null)
                    : ($engineFactors[$code]['band'] ?? null);
                continue;
            }

            $values[] = $kind === 'raw'
                ? ($areaScores[$code] ?? null)
                : ($areaSignals[$code] ?? null);
        }

        return $values;
    }

    private function assertProfile(string $profile): void
    {
        if (!in_array($profile, [self::PROFILE_RESEARCH, self::PROFILE_INSTITUTION], true)) {
            throw new InvalidArgumentException("알 수 없는 추출 프로필: {$profile}");
        }
    }
}
```

- [ ] **Step 4: `TestAttempt` 에 관계가 있는지 확인**

`app/Models/TestAttempt.php` 에 `voucher()` 관계가 없으면 추가한다:

```php
    public function voucher() { return $this->belongsTo(\App\Models\Voucher::class); }
```

`answers()`, `result()`, `test()` 는 이미 쓰이고 있으므로 그대로 둔다.

- [ ] **Step 5: 통과 확인**

Run: `php artisan test tests/Feature/Export/ResponseExporterTest.php`
Expected: PASS (13 tests)

- [ ] **Step 6: 커밋**

```bash
git add app/Services/Export/ResponseExporter.php app/Models/TestAttempt.php tests/Feature/Export/ResponseExporterTest.php
git commit -m "feat(export): 응답 CSV 컬럼 조립 서비스

연구용은 비식별·SAF 포함, 기관용은 이름 포함·SAF 문항 제외.
제외 대상은 채점 룰의 safety_items 에서 읽어 검사 종류를 모르게 한다.
미응답은 빈칸으로 남기고(0 과 구분), 문항 값은 역채점 전 원점수를 쓴다."
```

---

### Task 3: 연구용 다운로드 (관리자)

**Files:**
- Create: `app/Http/Controllers/ExportController.php`
- Modify: `routes/web.php` (관리자 그룹에 라우트 추가)
- Modify: `resources/views/admin/tests.blade.php` (검사 행에 버튼)
- Test: `tests/Feature/Export/ResearchExportTest.php`

**Interfaces:**
- Consumes: Task 1 의 `'admin'` 미들웨어 별칭 · Task 2 의 `ResponseExporter::headers/row/filename`
- Produces: 라우트 이름 `admin.exports.research` (파라미터: `{test:code}`) · `ExportController::research()` — Task 4 가 같은 컨트롤러에 `institution()` 을 추가한다.

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/Feature/Export/ResearchExportTest.php`:

```php
<?php
use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Models\User;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->admin = User::factory()->create(['is_admin' => true]);
});

function submittedAttempt(Test $test, array $answers = ['SAF06' => 3]): TestAttempt
{
    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.uniqid(),
        'test_id' => $test->id, 'status' => 'submitted',
        'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 16, 'gender' => 'female', 'nickname' => '지은',
    ]);

    $itemsByCode = $test->items->keyBy('item_code');
    foreach ($answers as $code => $value) {
        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'test_item_id' => $itemsByCode[$code]->id,
            'value' => $value,
        ]);
    }

    TestResult::create([
        'attempt_id' => $attempt->id,
        'area_scores' => [], 'area_signals' => [], 'recommendations' => [],
        'overall_level' => 'low', 'overall_signal' => 'green', 'interpretation' => '',
        'safety_level' => 'S3', 'environment_level' => 'E0', 'score_status' => 'COMPLETE',
        'engine_result' => ['factors' => []],
    ]);

    return $attempt;
}

/** 스트리밍 응답 본문을 문자열로 받는다(Laravel 이 버퍼링해 준다). */
function csvBody($response): string
{
    return $response->streamedContent();
}

test('비관리자는 연구용 추출을 받을 수 없다', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('admin.exports.research', $this->test))
        ->assertForbidden();
});

test('비로그인은 연구용 추출을 받을 수 없다', function () {
    $this->get(route('admin.exports.research', $this->test))
        ->assertRedirect(route('login'));
});

test('관리자는 CSV 를 받는다', function () {
    submittedAttempt($this->test);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.exports.research', $this->test))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('OY_MSI');
});

test('CSV 는 BOM 으로 시작하고 SAF 문항을 담는다', function () {
    submittedAttempt($this->test, ['SAF06' => 3]);

    $response = $this->actingAs($this->admin)->get(route('admin.exports.research', $this->test));
    $body = csvBody($response);

    expect($body)->toStartWith("\xEF\xBB\xBF");
    expect($body)->toContain('SAF06');
});

test('이름이 CSV 에 없다', function () {
    submittedAttempt($this->test);

    $body = csvBody($this->actingAs($this->admin)->get(route('admin.exports.research', $this->test)));

    expect($body)->not->toContain('지은');
});

test('미제출 응시는 빠진다', function () {
    TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-progress',
        'test_id' => $this->test->id, 'status' => 'in_progress',
        'started_at' => now(), 'age_at_test' => 15,
    ]);
    $submitted = submittedAttempt($this->test);

    $body = csvBody($this->actingAs($this->admin)->get(route('admin.exports.research', $this->test)));
    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines)->toHaveCount(2);                       // 헤더 + 제출 1건
    expect($body)->toContain((string) $submitted->id);
});

test('추출이 감사 로그에 남는다', function () {
    submittedAttempt($this->test);
    \Illuminate\Support\Facades\Log::spy();

    csvBody($this->actingAs($this->admin)->get(route('admin.exports.research', $this->test)));

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) => str_contains($message, '응답 추출')
            && ($context['profile'] ?? null) === 'research'
            && ($context['actor_id'] ?? null) === $this->admin->id);
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test tests/Feature/Export/ResearchExportTest.php`
Expected: FAIL — `Route [admin.exports.research] not defined.`

- [ ] **Step 3: 컨트롤러 작성**

`app/Http/Controllers/ExportController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\Export\ResponseExporter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private ResponseExporter $exporter) {}

    /** 연구용 — 관리자. 전체 기관·전체 응시, 비식별. */
    public function research(Test $test): StreamedResponse
    {
        $attempts = TestAttempt::where('test_id', $test->id)
            ->where('status', 'submitted')
            ->with('answers', 'result', 'voucher')
            ->orderBy('id');

        return $this->stream($test, $attempts, ResponseExporter::PROFILE_RESEARCH);
    }

    /**
     * 스트리밍으로 내보낸다 — 응시가 쌓이면 메모리에 다 올릴 수 없다.
     * chunk 로 읽어 한 줄씩 흘려보낸다.
     */
    private function stream(Test $test, $query, string $profile): StreamedResponse
    {
        $test->loadMissing('items', 'scoringRule');

        $count = (clone $query)->count();

        Log::info('응답 추출', [
            'actor_id' => auth()->id(),
            'test' => $test->code,
            'profile' => $profile,
            'count' => $count,
        ]);

        $exporter = $this->exporter;

        return response()->streamDownload(function () use ($test, $query, $profile, $exporter) {
            $handle = fopen('php://output', 'w');

            // 엑셀에서 한글이 깨지지 않게 UTF-8 BOM 을 먼저 쓴다.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $exporter->headers($test, $profile));

            $query->chunk(200, function ($attempts) use ($handle, $test, $profile, $exporter) {
                foreach ($attempts as $attempt) {
                    fputcsv($handle, $exporter->row($attempt, $test, $profile));
                }
                flush();
            });

            fclose($handle);
        }, $exporter->filename($test, $profile), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
```

- [ ] **Step 4: 라우트 추가**

`routes/web.php` 의 관리자 그룹(Task 1 에서 고친 블록) 안에 추가한다. `controller(AdminController::class)` 가 걸려 있으므로 **그룹 밖에 별도로** 둔다:

```php
// 연구용 응답 추출 — 관리자 전용. 자살·자해 문항이 포함되므로 가드가 전제다.
Route::middleware(['auth', 'admin'])
    ->get('/admin/exports/{test:code}/research', [ExportController::class, 'research'])
    ->name('admin.exports.research');
```

`use App\Http\Controllers\ExportController;` 를 파일 상단 use 목록에 추가한다.

- [ ] **Step 5: 통과 확인**

Run: `php artisan test tests/Feature/Export/ResearchExportTest.php`
Expected: PASS (7 tests)

- [ ] **Step 6: 관리자 화면에 버튼 추가**

`resources/views/admin/tests.blade.php` 의 검사 목록 표에서 각 행(`{{ $t->room }}` 셀이 있는 `<tr>`) 끝에 셀을 하나 더 붙인다. 표 머리(`<thead>`)에도 같은 자리에 `<th class="px-5 py-3">응답</th>` 을 추가한다:

```blade
<td class="px-5 py-3">
  <a href="{{ route('admin.exports.research', $t) }}"
     class="text-sm font-semibold text-teal hover:text-deepgreen transition">CSV 내려받기</a>
</td>
```

- [ ] **Step 7: 화면 확인 테스트 추가**

`tests/Feature/Export/ResearchExportTest.php` 끝에 붙인다:

```php
test('관리자 검사 목록에 내려받기 링크가 있다', function () {
    $this->actingAs($this->admin)->get('/admin/tests')
        ->assertOk()
        ->assertSee(route('admin.exports.research', $this->test), escape: false);
});
```

- [ ] **Step 8: 통과 확인**

Run: `php artisan test tests/Feature/Export/ResearchExportTest.php`
Expected: PASS (8 tests)

- [ ] **Step 9: 커밋**

```bash
git add app/Http/Controllers/ExportController.php routes/web.php resources/views/admin/tests.blade.php tests/Feature/Export/ResearchExportTest.php
git commit -m "feat(export): 연구용 응답 CSV 다운로드 (관리자)

표준화 분석에 넘길 파일. 비식별·SAF 포함·제출 완료만.
스트리밍으로 내보내 응시가 쌓여도 메모리에 다 올리지 않는다.
민감 데이터라 누가·언제·몇 건을 감사 로그로 남긴다."
```

---

### Task 4: 기관용 다운로드 (담당자)

**Files:**
- Modify: `app/Http/Controllers/ExportController.php` (`institution()` 추가)
- Modify: `routes/web.php`
- Modify: `resources/views/my/index.blade.php` (명부 상단에 버튼)
- Test: `tests/Feature/Export/InstitutionExportTest.php`

**Interfaces:**
- Consumes: Task 2 의 `ResponseExporter` · Task 3 의 `ExportController::stream()` (private, 같은 클래스)
- Produces: 라우트 이름 `my.exports.institution` (파라미터 `{test:code}`)

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/Feature/Export/InstitutionExportTest.php`:

```php
<?php
use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->staff = User::factory()->create();
});

/** 발급자 소유의 제출 응시 한 건. SAF 응답을 실제로 넣는다(부재 단언을 공허하지 않게). */
function issuedAttempt(Test $test, User $issuer, string $name, array $answers = ['DEP01' => 2, 'SAF06' => 3]): Voucher
{
    $voucher = Voucher::create([
        'user_id' => $issuer->id, 'test_id' => $test->id,
        'source' => 'issued_free', 'status' => 'used',
        'issued_at' => now(), 'assigned_at' => now(),
        'access_token' => 'tok-'.uniqid(), 'recipient_name' => $name,
    ]);

    $attempt = TestAttempt::create([
        'user_id' => null, 'guest_token' => 'g-'.uniqid(),
        'test_id' => $test->id, 'voucher_id' => $voucher->id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'age_at_test' => 17, 'gender' => 'male', 'nickname' => '별명',
    ]);

    $itemsByCode = $test->items->keyBy('item_code');
    foreach ($answers as $code => $value) {
        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'test_item_id' => $itemsByCode[$code]->id, 'value' => $value,
        ]);
    }

    TestResult::create([
        'attempt_id' => $attempt->id,
        'area_scores' => ['DEP' => 12.0], 'area_signals' => ['DEP' => 'red'],
        'recommendations' => [], 'overall_level' => 'high', 'overall_signal' => 'red',
        'interpretation' => '', 'safety_level' => 'S3', 'environment_level' => 'E0',
        'score_status' => 'COMPLETE', 'engine_result' => ['factors' => ['SAF' => ['raw' => 6.0, 'band' => 'RED']]],
    ]);

    $voucher->update(['used_attempt_id' => $attempt->id, 'used_at' => now()]);

    return $voucher;
}

function rosterCsv($response): string
{
    return $response->streamedContent();
}

test('비로그인은 기관용 추출을 받을 수 없다', function () {
    $this->get(route('my.exports.institution', $this->test))
        ->assertRedirect(route('login'));
});

test('담당자는 자기 발급분을 받는다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');

    $body = rosterCsv($this->actingAs($this->staff)
        ->get(route('my.exports.institution', $this->test))->assertOk());

    expect($body)->toStartWith("\xEF\xBB\xBF");
    expect($body)->toContain('홍길동');
});

test('남의 발급분은 들어가지 않는다', function () {
    $other = User::factory()->create();
    issuedAttempt($this->test, $this->staff, '내응시자');
    issuedAttempt($this->test, $other, '남의응시자');

    $body = rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    expect($body)->toContain('내응시자')->not->toContain('남의응시자');
});

test('기관용에는 SAF 문항 열이 없다', function () {
    // SAF06 = 3 응답이 실제로 존재하는 상태에서 확인한다.
    issuedAttempt($this->test, $this->staff, '홍길동', ['SAF06' => 3]);

    $body = rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    expect($body)->not->toContain('SAF06')->not->toContain('SAF01');
    expect($body)->toContain('DEP01');   // 다른 문항은 남아 있다
});

test('기관용에도 안전등급은 즉시로 표기된다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');

    $body = rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    expect($body)->toContain('안전확인')->toContain('즉시');
});

test('명부에 내려받기 버튼이 있다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');

    $this->actingAs($this->staff)->get(route('my.index'))
        ->assertOk()
        ->assertSee(route('my.exports.institution', $this->test), escape: false);
});

test('추출이 감사 로그에 남는다', function () {
    issuedAttempt($this->test, $this->staff, '홍길동');
    \Illuminate\Support\Facades\Log::spy();

    rosterCsv($this->actingAs($this->staff)->get(route('my.exports.institution', $this->test)));

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->withArgs(fn ($message, $context = []) => str_contains($message, '응답 추출')
            && ($context['profile'] ?? null) === 'institution'
            && ($context['actor_id'] ?? null) === $this->staff->id);
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test tests/Feature/Export/InstitutionExportTest.php`
Expected: FAIL — `Route [my.exports.institution] not defined.`

- [ ] **Step 3: 컨트롤러에 메서드 추가**

`app/Http/Controllers/ExportController.php` 의 `research()` 아래에 추가:

```php
    /**
     * 기관용 — 담당자. 자기가 발급한 검사권의 응시분만.
     *
     * 인가 규칙을 새로 만들지 않고 명부(MyTestController::index)와 같은 규칙을 쓴다:
     * vouchers.user_id = 로그인 사용자.
     */
    public function institution(Test $test): StreamedResponse
    {
        $attempts = TestAttempt::where('test_id', $test->id)
            ->where('status', 'submitted')
            ->whereHas('voucher', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('answers', 'result', 'voucher')
            ->orderBy('id');

        return $this->stream($test, $attempts, ResponseExporter::PROFILE_INSTITUTION);
    }
```

- [ ] **Step 4: 라우트 추가**

`routes/web.php` 의 `auth` 그룹(`my.issue` 등이 있는 블록, 99행 근처) 안에 추가:

```php
    Route::get('/my/exports/{test:code}', [ExportController::class, 'institution'])->name('my.exports.institution');
```

- [ ] **Step 5: 통과 확인 (버튼 테스트는 아직 실패)**

Run: `php artisan test tests/Feature/Export/InstitutionExportTest.php`
Expected: "명부에 내려받기 버튼이 있다"만 FAIL, 나머지 6개 PASS

- [ ] **Step 6: 명부에 버튼 추가**

`resources/views/my/index.blade.php` — 발급 명부 제목 줄(`발급한 검사권 <span ...>({{ $issued->count() }})</span>` 이 있는 `<h2>`)을 감싸 오른쪽에 버튼을 둔다. `$issued` 가 비어 있으면 내려받을 게 없으므로 숨긴다:

```blade
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <h2 class="font-bold text-deepgreen">발급한 검사권 <span class="text-sm text-navy/40 font-normal">({{ $issued->count() }})</span></h2>
  @if($issued->isNotEmpty())
    @foreach($issued->pluck('test')->unique('id') as $issuedTest)
      <a href="{{ route('my.exports.institution', $issuedTest) }}"
         class="rounded-lg bg-teal/10 text-teal px-3 py-2 text-xs font-bold hover:bg-teal/20 transition whitespace-nowrap">
        {{ $issuedTest->title_easy }} 명부 내려받기
      </a>
    @endforeach
  @endif
</div>
```

기존 `<h2 class="font-bold text-deepgreen mb-4">발급한 검사권 …</h2>` 한 줄을 위 블록으로 **교체**한다.

- [ ] **Step 7: 통과 확인**

Run: `php artisan test tests/Feature/Export/InstitutionExportTest.php`
Expected: PASS (7 tests)

- [ ] **Step 8: 범위 가드 뮤테이션 확인**

`institution()` 에서 `->whereHas('voucher', ...)` 줄을 잠시 지우고 돌린다.

Run: `php artisan test tests/Feature/Export/InstitutionExportTest.php`
Expected: FAIL — "남의 발급분은 들어가지 않는다"가 깨져야 한다. 안 깨지면 테스트가 공허하다.

확인 후 **되돌린다.**

- [ ] **Step 9: 전체 테스트**

Run: `php artisan test`
Expected: 전부 통과 (기존 409 + 신규)

- [ ] **Step 10: 커밋**

```bash
git add app/Http/Controllers/ExportController.php routes/web.php resources/views/my/index.blade.php tests/Feature/Export/InstitutionExportTest.php
git commit -m "feat(export): 기관용 명부 CSV 다운로드 (담당자)

자기가 발급한 검사권의 응시분만. 인가는 명부와 같은 규칙을 재사용한다.
SAF 문항 원점수는 뺀다 — 화면에서 3중으로 막아둔 것을 엑셀이 우회하지
않게 한다. 대신 안전등급(즉시/당일)은 명부에 이미 보이므로 넣는다."
```

---

## 배포 전 확인 (코드 아님)

- [ ] **관리자 계정 지정** — 마이그레이션 후 `is_admin` 이 전부 false 라 아무도 `/admin` 에 못 들어간다. 배포 직후 SQL 로 지정한다: `UPDATE users SET is_admin = 1 WHERE email = '<관리자 이메일>';`
- [ ] 마이그레이션 선적용: `php artisan migrate`
- [ ] 프리뷰에서 CSV 를 실제로 내려받아 **엑셀로 열어** 한글이 깨지지 않는지, 미응답이 빈칸인지 눈으로 확인
- [ ] 설계 문서 §8 의 미결 2건은 그대로 남는다 — 연구용에 기관 정보를 넣을지, 동의 문안이 표준화 연구 활용을 포함하는지
