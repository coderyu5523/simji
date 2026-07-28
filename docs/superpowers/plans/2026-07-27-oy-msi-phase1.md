# 학교 밖 청소년 마음상태검사(OY_MSI) 1단계 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 학교 밖 청소년 마음상태검사(60문항·10요인·4점 척도)를 심지 Laravel에 구현한다 — 응시 → 채점(요인/안전/환경/사례코드) → 청소년용 결과 → 보호자 공유까지, 비공개(`status != active`) 상태로.

**Architecture:** 기존 `tests`/`test_items`/`test_attempts`/`attempt_answers`/`test_results` 스키마를 가산 확장하고, `ScoringService`를 디스패처로 바꿔 검사별 엔진을 고른다. 이 검사는 `OyMsiScoringEngine`이 8개 소단위 클래스를 조립해 채점한다. 모든 임계값은 `scoring_rules.rules` JSON에 데이터로 존재하고 코드에는 없다.

**Tech Stack:** Laravel 11 / PHP 8.2 (XAMPP) / SQLite(로컬·테스트) / Pest / Blade + Tailwind

## Global Constraints

- **설계 문서**: `docs/superpowers/specs/2026-07-27-oy-msi-laravel-design.md`. 충돌 시 spec이 우선.
- **PHP 실행**: 비대화형 Bash는 PATH를 못 읽는다. 모든 php/composer 명령 앞에 `export PATH="/c/xampp/php:$PATH"` 필수.
- **테스트 실행**: `export PATH="/c/xampp/php:$PATH" && php artisan test`
- **테스트 문법**: Pest. `tests/Pest.php`가 `Feature/`에 `RefreshDatabase`를 자동 적용한다. 클래스 기반 PHPUnit 금지.
- **회귀 금지**: 기존 55 pass를 깨뜨리지 않는다. 5점 척도 검사(`KMSIA-SAMPLE`)의 채점 결과는 한 글자도 바뀌면 안 된다.
- **기존 컬럼**: 삭제 0건. 변경은 `attempt_answers.value` nullable 1건만.
- **채점 기준은 `item_code`**: `DEP01`·`SAF04` 같은 영구 ID. 화면 순서(`no`)로 채점하지 않는다.
- **임계값 하드코딩 금지**: 밴드 경계, S/E 조건, tie-break 가중치는 전부 `scoring_rules.rules`에서 읽는다.
- **안전등급은 003 기준**: SAF04≥1 / SAF01=3 / SAF02=3 / SAF05≥2 는 **S3**. (007의 S2 아님)
- **로그인 정책**: 개인 직접 응시는 로그인 필수(`assessment/*`의 `auth` 미들웨어 유지). 비로그인은 `/t/{token}` 링크 경로만.
- **`tests.status`는 `active`로 올리지 않는다.** 1단계는 내부·시연용.
- **금지 표현**(005 부록1 §3): 문제가 심각하다 / 비정상이다 / 정신적으로 이상하다 / 위험한 청소년이다 / 부모의 말을 듣지 않는다 / 의지가 부족하다 / 게으르다 / 부적응자이다 / 자살성향이 있다 / 치료가 반드시 필요하다
- **커밋**: 각 Task 끝에서 커밋한다. push는 하지 않는다.

---

## File Structure

| 파일 | 책임 |
|---|---|
| `database/migrations/2026_07_28_000001_extend_tests_for_oy_msi.php` | 기존 6개 테이블 컬럼 추가 + `attempt_answers.value` nullable |
| `database/migrations/2026_07_28_000002_create_oy_msi_support_tables.php` | `interpretation_templates` · `report_shares` · `consent_records` |
| `app/Models/InterpretationTemplate.php` `ReportShare.php` `ConsentRecord.php` | 신규 모델 |
| `database/seeders/OyMsi/TestSeeder.php` | 검사 + 60문항(교정 순서) |
| `database/seeders/OyMsi/ScoringRuleSeeder.php` | `scoring_rules.rules` 전체 |
| `database/seeders/OyMsi/TemplateSeeder.php` | `interpretation_templates` ~174건 |
| `app/Services/Scoring/ScoringEngine.php` | 인터페이스 |
| `app/Services/Scoring/SignalScoringEngine.php` | 기존 로직 이전 (무변경) |
| `app/Services/Scoring/OyMsi/ItemScorer.php` | 역채점·PREFER_NOT |
| `app/Services/Scoring/OyMsi/FactorScorer.php` | 요인점수·환산·위험지수·밴드 |
| `app/Services/Scoring/OyMsi/SafetyEvaluator.php` | S0~S3 |
| `app/Services/Scoring/OyMsi/EnvironmentEvaluator.php` | E0~E3 |
| `app/Services/Scoring/OyMsi/CaseClassifier.php` | 일반코드 → 최종코드 |
| `app/Services/Scoring/OyMsi/PriorityRanker.php` | 상위 3영역 |
| `app/Services/Scoring/OyMsi/StrengthExtractor.php` | 강점 |
| `app/Services/Scoring/OyMsi/SolutionRecommender.php` | 솔루션·dedupe |
| `app/Services/Scoring/OyMsi/OyMsiScoringEngine.php` | 위 8개 조립 |
| `app/Services/ScoringService.php` | 디스패처 (경로 유지, 내용 교체) |
| `app/Rules/AnswerValue.php` | 문항 `options` 기반 응답값 검증 |
| `app/Services/OyMsi/ReportComposer.php` | 템플릿 → 결과 섹션 조립 |
| `app/Http/Controllers/OyMsi/*` · Blade 뷰 | 연령게이트·기본정보·결과·공유 |

---

## Task 1: 스키마 확장

**Files:**
- Create: `database/migrations/2026_07_28_000001_extend_tests_for_oy_msi.php`
- Create: `database/migrations/2026_07_28_000002_create_oy_msi_support_tables.php`
- Create: `app/Models/InterpretationTemplate.php`, `app/Models/ReportShare.php`, `app/Models/ConsentRecord.php`
- Modify: `app/Models/Test.php`, `app/Models/TestItem.php`, `app/Models/TestAttempt.php`, `app/Models/TestResult.php`
- Test: `tests/Feature/OyMsi/SchemaTest.php`

**Interfaces:**
- Consumes: 없음 (첫 태스크)
- Produces: 컬럼 `tests.scoring_engine|assessment_version|min_age|max_age|guardian_consent_below_age`, `test_items.item_code|scale_code|timeframe_code`, `attempt_answers.missing_code` + `value` nullable, `test_attempts.nickname|age_at_test|gender|assessment_version|scoring_version`, `test_results.general_case_code|final_case_code|safety_level|environment_level|score_status|engine_result`, `scoring_rules.version`, `vouchers.guardian_consent_confirmed_at|guardian_consent_confirmed_by`. 모델 `InterpretationTemplate`(`template_key`,`locale`,`version`,`text`,`active`), `ReportShare`(`attempt_id`,`audience`,`token`,`source`,`created_by`,`expires_at`,`revoked_at`,`viewed_at`), `ConsentRecord`(`attempt_id`,`consent_type`,`granted`,`granted_at`,`actor`,`actor_user_id`,`meta`)

- [ ] **Step 1: 스키마 테스트를 먼저 작성**

`tests/Feature/OyMsi/SchemaTest.php`:

```php
<?php
use Illuminate\Support\Facades\Schema;

test('oy_msi 확장 컬럼이 존재한다', function () {
    expect(Schema::hasColumns('tests', [
        'scoring_engine', 'assessment_version', 'min_age', 'max_age', 'guardian_consent_below_age',
    ]))->toBeTrue();
    expect(Schema::hasColumns('test_items', ['item_code', 'scale_code', 'timeframe_code']))->toBeTrue();
    expect(Schema::hasColumn('attempt_answers', 'missing_code'))->toBeTrue();
    expect(Schema::hasColumns('test_attempts', [
        'nickname', 'age_at_test', 'gender', 'assessment_version', 'scoring_version',
    ]))->toBeTrue();
    expect(Schema::hasColumns('test_results', [
        'general_case_code', 'final_case_code', 'safety_level', 'environment_level',
        'score_status', 'engine_result',
    ]))->toBeTrue();
    expect(Schema::hasColumn('scoring_rules', 'version'))->toBeTrue();
    expect(Schema::hasColumns('vouchers', [
        'guardian_consent_confirmed_at', 'guardian_consent_confirmed_by',
    ]))->toBeTrue();
});

test('신규 테이블 3개가 존재한다', function () {
    expect(Schema::hasTable('interpretation_templates'))->toBeTrue();
    expect(Schema::hasTable('report_shares'))->toBeTrue();
    expect(Schema::hasTable('consent_records'))->toBeTrue();
});

test('응답값은 null 을 허용한다 (응답거부)', function () {
    $t = \App\Models\Test::create([
        'code' => 'NULLCHK', 'room' => 'middle', 'title_easy' => 'x', 'title_pro' => 'X',
        'target' => 't', 'duration_min' => 1, 'item_count' => 1, 'areas' => ['A'],
        'result_type' => 'signal', 'description' => 'd', 'status' => 'draft',
    ]);
    $i = $t->items()->create(['no' => 1, 'text' => 'q', 'type' => 'likert4', 'reverse' => false, 'area' => 'A']);
    $a = \App\Models\TestAttempt::create([
        'test_id' => $t->id, 'guest_token' => 'g', 'status' => 'in_progress', 'started_at' => now(),
    ]);

    $ans = $a->answers()->create(['test_item_id' => $i->id, 'value' => null, 'missing_code' => 'PREFER_NOT']);

    expect($ans->fresh()->value)->toBeNull();
    expect($ans->fresh()->missing_code)->toBe('PREFER_NOT');
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=SchemaTest`
Expected: FAIL — 컬럼/테이블 없음

- [ ] **Step 3: 기존 테이블 확장 마이그레이션 작성**

`database/migrations/2026_07_28_000001_extend_tests_for_oy_msi.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $t) {
            $t->string('scoring_engine', 30)->default('signal')->after('result_type');
            $t->string('assessment_version', 30)->default('1.0.0')->after('scoring_engine');
            $t->unsignedSmallInteger('min_age')->nullable()->after('target');
            $t->unsignedSmallInteger('max_age')->nullable()->after('min_age');
            $t->unsignedSmallInteger('guardian_consent_below_age')->nullable()->after('max_age');
        });

        Schema::table('test_items', function (Blueprint $t) {
            $t->string('item_code', 20)->nullable()->after('no');
            $t->string('scale_code', 30)->nullable()->after('type');
            $t->string('timeframe_code', 30)->nullable()->after('scale_code');
            $t->unique(['test_id', 'item_code']);
        });

        Schema::table('attempt_answers', function (Blueprint $t) {
            $t->unsignedTinyInteger('value')->nullable()->change();
            $t->string('missing_code', 30)->nullable()->after('value');
        });

        Schema::table('test_attempts', function (Blueprint $t) {
            $t->string('nickname', 50)->nullable()->after('guest_token');
            $t->unsignedSmallInteger('age_at_test')->nullable()->after('nickname');
            $t->string('gender', 20)->nullable()->after('age_at_test');
            $t->string('assessment_version', 30)->nullable()->after('status');
            $t->string('scoring_version', 30)->nullable()->after('assessment_version');
        });

        Schema::table('test_results', function (Blueprint $t) {
            $t->string('general_case_code', 5)->nullable()->after('overall_signal');
            $t->string('final_case_code', 5)->nullable()->after('general_case_code');
            $t->string('safety_level', 2)->nullable()->after('final_case_code');
            $t->string('environment_level', 2)->nullable()->after('safety_level');
            $t->string('score_status', 20)->default('COMPLETE')->after('environment_level');
            $t->json('engine_result')->nullable()->after('recommendations');
        });

        Schema::table('scoring_rules', function (Blueprint $t) {
            $t->string('version', 30)->default('1.0.0')->after('test_id');
        });

        Schema::table('vouchers', function (Blueprint $t) {
            $t->timestamp('guardian_consent_confirmed_at')->nullable()->after('result_visible');
            $t->foreignId('guardian_consent_confirmed_by')->nullable()
              ->after('guardian_consent_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('guardian_consent_confirmed_by');
            $t->dropColumn('guardian_consent_confirmed_at');
        });
        Schema::table('scoring_rules', fn (Blueprint $t) => $t->dropColumn('version'));
        Schema::table('test_results', fn (Blueprint $t) => $t->dropColumn([
            'general_case_code', 'final_case_code', 'safety_level',
            'environment_level', 'score_status', 'engine_result',
        ]));
        Schema::table('test_attempts', fn (Blueprint $t) => $t->dropColumn([
            'nickname', 'age_at_test', 'gender', 'assessment_version', 'scoring_version',
        ]));
        Schema::table('attempt_answers', function (Blueprint $t) {
            $t->dropColumn('missing_code');
            $t->unsignedTinyInteger('value')->nullable(false)->change();
        });
        Schema::table('test_items', function (Blueprint $t) {
            $t->dropUnique(['test_id', 'item_code']);
            $t->dropColumn(['item_code', 'scale_code', 'timeframe_code']);
        });
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn([
            'scoring_engine', 'assessment_version', 'min_age', 'max_age', 'guardian_consent_below_age',
        ]));
    }
};
```

- [ ] **Step 4: 신규 테이블 마이그레이션 작성**

`database/migrations/2026_07_28_000002_create_oy_msi_support_tables.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interpretation_templates', function (Blueprint $t) {
            $t->id();
            $t->string('template_key', 120);
            $t->string('locale', 10)->default('ko-KR');
            $t->string('version', 30)->default('1.0.0');
            $t->text('text');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['template_key', 'locale', 'version']);
        });

        Schema::create('report_shares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained('test_attempts')->cascadeOnDelete();
            $t->string('audience', 30)->default('guardian');
            $t->string('token', 64)->unique();
            $t->string('source', 20); // youth_self | staff
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('viewed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('consent_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained('test_attempts')->cascadeOnDelete();
            $t->string('consent_type', 30); // sensitive | guardian_offline
            $t->boolean('granted')->default(true);
            $t->timestamp('granted_at');
            $t->string('actor', 20); // youth | staff
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['attempt_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('report_shares');
        Schema::dropIfExists('interpretation_templates');
    }
};
```

- [ ] **Step 5: 신규 모델 3개 작성**

`app/Models/InterpretationTemplate.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InterpretationTemplate extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];
}
```

`app/Models/ReportShare.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReportShare extends Model
{
    protected $guarded = [];
    protected $casts = [
        'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'viewed_at' => 'datetime',
    ];
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'attempt_id'); }
    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
```

`app/Models/ConsentRecord.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ConsentRecord extends Model
{
    protected $guarded = [];
    protected $casts = ['granted' => 'boolean', 'granted_at' => 'datetime', 'meta' => 'array'];
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'attempt_id'); }
}
```

- [ ] **Step 6: 기존 모델에 캐스트·관계 추가**

`app/Models/TestResult.php` — `$casts`를 다음으로 교체:

```php
    protected $casts = [
        'area_scores' => 'array', 'area_signals' => 'array',
        'recommendations' => 'array', 'engine_result' => 'array',
    ];
```

`app/Models/TestAttempt.php` — 클래스 안에 관계 2개 추가:

```php
    public function consents() { return $this->hasMany(ConsentRecord::class, 'attempt_id'); }
    public function shares() { return $this->hasMany(ReportShare::class, 'attempt_id'); }
```

`app/Models/Test.php` — `$casts`를 다음으로 교체하고 헬퍼 추가:

```php
    protected $casts = [
        'areas' => 'array', 'requires_guardian_consent' => 'boolean',
        'min_age' => 'integer', 'max_age' => 'integer',
        'guardian_consent_below_age' => 'integer',
    ];

    /** 이 연령이 법정대리인 동의 대상인가 (PIPA §22-2) */
    public function needsGuardianConsentFor(?int $age): bool
    {
        if ($this->guardian_consent_below_age === null || $age === null) return false;
        return $age < $this->guardian_consent_below_age;
    }
```

- [ ] **Step 7: 마이그레이션 실행 + 테스트 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan migrate && php artisan test --filter=SchemaTest`
Expected: PASS 3건

- [ ] **Step 8: 전체 회귀 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 기존 55 pass 유지 + 신규 3 pass

- [ ] **Step 9: 커밋**

```bash
git add database/migrations/2026_07_28_000001_extend_tests_for_oy_msi.php \
        database/migrations/2026_07_28_000002_create_oy_msi_support_tables.php \
        app/Models/InterpretationTemplate.php app/Models/ReportShare.php app/Models/ConsentRecord.php \
        app/Models/Test.php app/Models/TestAttempt.php app/Models/TestResult.php \
        tests/Feature/OyMsi/SchemaTest.php
git commit -m "feat(oy-msi): 스키마 확장 — 검사/문항/응답/결과 컬럼 + 템플릿·공유·동의 테이블"
```

---

## Task 2: 검사·60문항 시더 + 배치 규칙 테스트

**Files:**
- Create: `database/seeders/OyMsi/TestSeeder.php`
- Test: `tests/Feature/OyMsi/ItemIntegrityTest.php`

**Interfaces:**
- Consumes: Task 1의 `test_items.item_code|scale_code|timeframe_code`, `tests.scoring_engine|assessment_version|min_age|max_age|guardian_consent_below_age`
- Produces: `Test` 레코드 `code='OY_MSI'`(`scoring_engine='oy_msi'`, `assessment_version='1.0.1'`, `status='draft'`) + `test_items` 60건. 요인 코드는 `area` 컬럼에 `DEP|ANX|IMP|TRM|ISO|FAM|LIF|RSK|FUT|SAF`로 저장.

- [ ] **Step 1: 배치 규칙 테스트를 먼저 작성**

`tests/Feature/OyMsi/ItemIntegrityTest.php`:

```php
<?php
use App\Models\Test;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->items = $this->test->items()->orderBy('no')->get();
});

test('60문항이고 표시순서가 1~60 연속이다', function () {
    expect($this->items)->toHaveCount(60);
    expect($this->items->pluck('no')->all())->toBe(range(1, 60));
});

test('item_code 가 유일하고 요인마다 정확히 6문항이다', function () {
    expect($this->items->pluck('item_code')->unique())->toHaveCount(60);

    $byFactor = $this->items->groupBy('area');
    expect($byFactor->keys()->sort()->values()->all())
        ->toBe(['ANX', 'DEP', 'FAM', 'FUT', 'IMP', 'ISO', 'LIF', 'RSK', 'SAF', 'TRM']);
    foreach ($byFactor as $factor => $group) {
        expect($group)->toHaveCount(6, "요인 {$factor} 문항 수");
    }
});

test('역채점은 FUT04·FUT05·FUT06 셋뿐이다', function () {
    $reversed = $this->items->where('reverse', true)->pluck('item_code')->sort()->values()->all();
    expect($reversed)->toBe(['FUT04', 'FUT05', 'FUT06']);
});

test('척도 배정이 GEN 54 · SAF-T 4 · SAF-B 2 이다', function () {
    $counts = $this->items->countBy('scale_code');
    expect($counts['GEN_4PT'])->toBe(54);
    expect($counts['SAF_THOUGHT_4PT'])->toBe(4);
    expect($counts['SAF_BEHAVIOR_4PT'])->toBe(2);
});

test('12개월 기준 문항은 SAF05·SAF06 둘뿐이다', function () {
    $yearly = $this->items->where('timeframe_code', 'PAST_12_MONTHS')
        ->pluck('item_code')->sort()->values()->all();
    expect($yearly)->toBe(['SAF05', 'SAF06']);
});

test('동일 요인이 연속 배치되지 않는다 (007 §4.1)', function () {
    $factors = $this->items->pluck('area')->all();
    for ($i = 1; $i < count($factors); $i++) {
        expect($factors[$i])->not->toBe(
            $factors[$i - 1],
            sprintf('Q%03d 와 Q%03d 가 같은 요인(%s)', $i, $i + 1, $factors[$i])
        );
    }
});

test('안전문항이 Q010·Q018·Q026·Q034·Q042·Q060 에 위치한다', function () {
    $safPositions = $this->items->where('area', 'SAF')->pluck('no')->sort()->values()->all();
    expect($safPositions)->toBe([10, 18, 26, 34, 42, 60]);
});

test('10문항 사이클마다 10개 요인이 정확히 1회씩 나온다', function () {
    foreach (range(0, 5) as $cycle) {
        $slice = $this->items->slice($cycle * 10, 10)->pluck('area');
        expect($slice->unique())->toHaveCount(10, "사이클 " . ($cycle + 1));
    }
});

test('역채점 문항은 후반(Q031 이후)에 분산된다', function () {
    $positions = $this->items->where('reverse', true)->pluck('no')->sort()->values()->all();
    expect($positions)->toBe([31, 44, 57]);
});

test('검사 메타가 spec 과 일치한다', function () {
    expect($this->test->scoring_engine)->toBe('oy_msi');
    expect($this->test->assessment_version)->toBe('1.0.1');
    expect($this->test->min_age)->toBe(13);
    expect($this->test->max_age)->toBe(18);
    expect($this->test->guardian_consent_below_age)->toBe(14);
    expect($this->test->status)->not->toBe('active'); // 1단계는 비공개
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ItemIntegrityTest`
Expected: FAIL — `Database\Seeders\OyMsi\TestSeeder` 없음

- [ ] **Step 3: 시더 작성 — 검사 레코드와 척도 정의**

`database/seeders/OyMsi/TestSeeder.php` (앞부분):

```php
<?php
namespace Database\Seeders\OyMsi;

use App\Models\Test;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    private const SCALES = [
        'GEN_4PT' => ['전혀 그렇지 않다', '가끔 그렇다', '자주 그렇다', '거의 항상 그렇다'],
        'SAF_THOUGHT_4PT' => ['전혀 없었다', '한두 번 있었다', '여러 번 있었다', '자주 있었거나 지금도 그렇다'],
        'SAF_BEHAVIOR_4PT' => ['없었다', '1회 있었다', '2~3회 있었다', '4회 이상 또는 최근 1개월 안에 있었다'],
    ];

    public function run(): void
    {
        if (Test::where('code', 'OY_MSI')->exists()) return;

        $test = Test::create([
            'code' => 'OY_MSI',
            'room' => 'middle',
            'title_easy' => '마음상태검사',
            'title_pro' => '학교 밖 청소년용 마음상태검사',
            'target' => '만 13~18세 청소년',
            'min_age' => 13,
            'max_age' => 18,
            'guardian_consent_below_age' => 14,
            'duration_min' => 15,
            'item_count' => 60,
            'areas' => ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT', 'SAF'],
            'result_type' => 'signal',
            'scoring_engine' => 'oy_msi',
            'assessment_version' => '1.0.1',
            'description' => '최근 마음상태와 생활기능을 확인하는 선별검사입니다. 정신질환을 진단하는 검사가 아닙니다.',
            'status' => 'draft', // 1단계는 비공개
            'thumbnail' => null,
        ]);

        foreach ($this->items() as $index => [$code, $factor, $scale, $timeframe, $reverse, $text]) {
            $test->items()->create([
                'no' => $index + 1,
                'item_code' => $code,
                'area' => $factor,
                'type' => 'likert4',
                'scale_code' => $scale,
                'timeframe_code' => $timeframe,
                'options' => self::SCALES[$scale],
                'reverse' => $reverse,
                'text' => $text,
            ]);
        }
    }
```

- [ ] **Step 4: 시더에 60문항 배열 추가 (교정된 순서)**

같은 파일에 이어서. 배열 순서가 곧 `display_order` Q001~Q060이다. **Q021↔Q022, Q041↔Q043 교정이 이미 반영된 순서다.**

```php
    /** @return array<int, array{0:string,1:string,2:string,3:string,4:bool,5:string}> */
    private function items(): array
    {
        $G = 'GEN_4PT'; $T = 'SAF_THOUGHT_4PT'; $B = 'SAF_BEHAVIOR_4PT';
        $W = 'PAST_2_WEEKS'; $Y = 'PAST_12_MONTHS';

        return [
            // ── 사이클 1 (Q001~Q010)
            ['DEP01','DEP',$G,$W,false,'요즘 특별한 이유가 없어도 마음이 가라앉거나 슬프다.'],
            ['LIF01','LIF',$G,$W,false,'자는 시간과 일어나는 시간이 일정하지 않다.'],
            ['ANX01','ANX',$G,$W,false,'별일이 없어도 나쁜 일이 생길 것 같아 걱정된다.'],
            ['ISO01','ISO',$G,$W,false,'내 마음을 솔직하게 이야기할 사람이 없다고 느낀다.'],
            ['FUT01','FUT',$G,$W,false,'앞으로 내 삶이 나아질 가능성이 거의 없다고 느낀다.'],
            ['IMP01','IMP',$G,$W,false,'작은 일에도 쉽게 짜증이 나거나 화가 난다.'],
            ['FAM01','FAM',$G,$W,false,'가족이나 보호자와 이야기하면 갈등이 더 심해지는 경우가 많다.'],
            ['TRM01','TRM',$G,$W,false,'과거의 힘든 일이 갑자기 떠올라 괴로울 때가 있다.'],
            ['RSK01','RSK',$G,$W,false,'스마트폰이나 게임 때문에 해야 할 일을 하지 못한다.'],
            ['SAF01','SAF',$T,$W,false,'차라리 내가 없어지는 것이 낫겠다고 생각한 적이 있다.'],

            // ── 사이클 2 (Q011~Q020)
            ['ANX02','ANX',$G,$W,false,'앞으로 무엇을 해야 할지 생각하면 마음이 답답해진다.'],
            ['FUT02','FUT',$G,$W,false,'내가 무엇을 잘하고 어떤 일을 하고 싶은지 잘 모르겠다.'],
            ['FAM02','FAM',$G,$W,false,'가족이나 보호자가 내 마음과 상황을 이해하지 못한다고 느낀다.'],
            ['DEP02','DEP',$G,$W,false,'예전에 좋아했던 일도 별로 즐겁지 않다.'],
            ['RSK02','RSK',$G,$W,false,'스마트폰이나 게임을 줄이려고 해도 잘되지 않는다.'],
            ['TRM02','TRM',$G,$W,false,'힘든 일을 떠올리게 하는 사람이나 장소를 피한다.'],
            ['LIF02','LIF',$G,$W,false,'밤에 깨어 있고 낮에 자는 생활이 반복된다.'],
            ['SAF02','SAF',$T,$W,false,'내 몸을 일부러 다치게 하고 싶다는 생각이 든 적이 있다.'],
            ['IMP02','IMP',$G,$W,false,'화가 나면 심한 말을 하거나 물건을 던지고 싶어진다.'],
            ['ISO02','ISO',$G,$W,false,'다른 사람을 만나거나 연락하는 것이 부담스럽다.'],

            // ── 사이클 3 (Q021~Q030)  ※ IMP03↔ISO03 교정
            ['IMP03','IMP',$G,$W,false,'감정이 올라오면 내 말과 행동을 멈추기 어렵다.'],
            ['ISO03','ISO',$G,$W,false,'며칠 동안 집이나 방 밖으로 거의 나가지 않을 때가 있다.'],
            ['LIF03','LIF',$G,$W,false,'식사를 거르거나 한꺼번에 많이 먹는 경우가 많다.'],
            ['ANX03','ANX',$G,$W,false,'다른 사람이 나를 어떻게 생각할지 지나치게 신경 쓰인다.'],
            ['TRM03','TRM',$G,$W,false,'작은 소리나 움직임에도 깜짝 놀라거나 긴장한다.'],
            ['SAF03','SAF',$T,$W,false,'스스로 목숨을 끊고 싶다는 생각을 한 적이 있다.'],
            ['RSK03','RSK',$G,$W,false,'온라인 활동을 하지 못하면 불안하거나 짜증이 심해진다.'],
            ['DEP03','DEP',$G,$W,false,'아무것도 시작하고 싶지 않고 가만히 있고 싶다.'],
            ['FUT03','FUT',$G,$W,false,'공부나 일을 시작해도 끝까지 해내지 못할 것 같다.'],
            ['FAM03','FAM',$G,$W,false,'집에서 무시당하거나 모욕적인 말을 듣는 경우가 있다.'],

            // ── 사이클 4 (Q031~Q040)
            ['FUT04','FUT',$G,$W,true, '나에게 맞는 공부나 일을 찾기 위해 새로운 것을 시도할 생각이 있다.'],
            ['DEP04','DEP',$G,$W,false,'내가 쓸모없거나 다른 사람에게 짐이 되는 것처럼 느껴진다.'],
            ['RSK04','RSK',$G,$W,false,'기분을 풀거나 힘든 생각을 잊기 위해 술이나 담배를 사용한다.'],
            ['SAF04','SAF',$T,$W,false,'목숨을 끊을 구체적인 방법, 장소 또는 도구를 생각하거나 준비한 적이 있다.'],
            ['FAM04','FAM',$G,$W,false,'힘든 일이 생겨도 가족이나 보호자에게 도움을 기대하기 어렵다.'],
            ['ANX04','ANX',$G,$W,false,'여러 생각이 계속 떠올라 마음을 편하게 하기 어렵다.'],
            ['ISO04','ISO',$G,$W,false,'다른 사람은 나를 이해하지 못할 것이라고 생각한다.'],
            ['IMP04','IMP',$G,$W,false,'결과를 충분히 생각하지 않고 행동할 때가 있다.'],
            ['LIF04','LIF',$G,$W,false,'씻기, 옷 갈아입기, 방 정리 같은 일상관리가 귀찮고 어렵다.'],
            ['TRM04','TRM',$G,$W,false,'너무 힘들 때 현실이 아닌 것 같거나 멍해질 때가 있다.'],

            // ── 사이클 5 (Q041~Q050)  ※ LIF05↔TRM05 교정 (SAF05는 Q042 고정)
            ['LIF05','LIF',$G,$W,false,'하루 동안 거의 움직이지 않거나 밖에 나가지 않는다.'],
            ['SAF05','SAF',$B,$Y,false,'최근 12개월 동안 내 몸을 일부러 다치게 한 적이 있다.'],
            ['TRM05','TRM',$G,$W,false,'다른 사람이 나를 해치거나 위협할 것 같아 주변을 계속 살핀다.'],
            ['FUT05','FUT',$G,$W,true, '작은 목표를 세우면 조금씩 실천할 수 있다.'],
            ['IMP05','IMP',$G,$W,false,'화가 난 뒤 내가 한 말이나 행동을 후회하는 경우가 많다.'],
            ['DEP05','DEP',$G,$W,false,'충분히 쉬어도 몸과 마음이 지쳐 있다.'],
            ['FAM05','FAM',$G,$W,false,'가족이나 보호자가 나를 때리거나 심하게 위협하는 경우가 있다.'],
            ['ANX05','ANX',$G,$W,false,'긴장하면 심장이 빨리 뛰거나 숨이 답답해질 때가 있다.'],
            ['RSK05','RSK',$G,$W,false,'인터넷 도박, 위험한 거래 또는 큰돈을 잃을 수 있는 활동을 한 적이 있다.'],
            ['ISO05','ISO',$G,$W,false,'상처받을까 봐 사람을 믿거나 가까이하기 어렵다.'],

            // ── 사이클 6 (Q051~Q060)
            ['RSK06','RSK',$G,$W,false,'온라인에서 돈, 개인정보, 사진 또는 영상을 보내라는 압박을 받은 적이 있다.'],
            ['ISO06','ISO',$G,$W,false,'힘들어도 다른 사람에게 도움을 요청하지 않고 혼자 참는다.'],
            ['DEP06','DEP',$G,$W,false,'앞으로 내 상황이 좋아질 것이라는 생각이 잘 들지 않는다.'],
            ['TRM06','TRM',$G,$W,false,'지금 머무는 곳에서 폭력이나 원하지 않는 신체접촉을 당할까 두렵다.'],
            ['FAM06','FAM',$G,$W,false,'가족과의 갈등 때문에 집을 나가고 싶거나 돌아가기 싫을 때가 있다.'],
            ['ANX06','ANX',$G,$W,false,'새로운 장소에 가거나 낯선 사람을 만나는 것이 두렵다.'],
            ['FUT06','FUT',$G,$W,true, '적절한 도움을 받으면 다시 시작할 수 있다고 생각한다.'],
            ['IMP06','IMP',$G,$W,false,'기분이 나쁘면 위험하거나 무모한 행동을 하고 싶어진다.'],
            ['LIF06','LIF',$G,$W,false,'해야 할 일을 계획하고 정해진 시간에 시작하기 어렵다.'],
            ['SAF06','SAF',$B,$Y,false,'최근 12개월 동안 실제로 목숨을 끊으려는 행동을 한 적이 있다.'],
        ];
    }
}
```

- [ ] **Step 5: 테스트 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ItemIntegrityTest`
Expected: PASS 10건. 특히 "동일 요인이 연속 배치되지 않는다"가 통과해야 한다 — 실패하면 Q021/Q022 또는 Q041/Q043 교정이 배열에 반영되지 않은 것이다.

- [ ] **Step 6: 커밋**

```bash
git add database/seeders/OyMsi/TestSeeder.php tests/Feature/OyMsi/ItemIntegrityTest.php
git commit -m "feat(oy-msi): 검사·60문항 시더 (배치 교정 반영, assessment_version 1.0.1)"
```

---

## Task 3: 채점 규칙 시더

**Files:**
- Create: `database/seeders/OyMsi/ScoringRuleSeeder.php`
- Test: `tests/Feature/OyMsi/ScoringRuleSeederTest.php`

**Interfaces:**
- Consumes: Task 2의 `Test code='OY_MSI'`
- Produces: `scoring_rules` 1건 (`version='1.0.0'`). `rules` 최상위 키 — `factors`, `bands`, `overall_bands`, `safety`, `environment`, `case_codes`, `priority`, `strengths`, `solutions`, `recheck`. 이후 모든 엔진 클래스가 이 배열을 인자로 받는다.

- [ ] **Step 1: 규칙 구조 테스트 작성**

`tests/Feature/OyMsi/ScoringRuleSeederTest.php`:

```php
<?php
use App\Models\Test;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
});

test('최상위 키가 모두 존재한다', function () {
    expect(array_keys($this->rules))->toContain(
        'factors', 'bands', 'overall_bands', 'safety', 'environment',
        'case_codes', 'priority', 'strengths', 'solutions', 'recheck'
    );
});

test('요인 10개 · SAF 만 총점 제외', function () {
    expect($this->rules['factors'])->toHaveCount(10);
    $excluded = collect($this->rules['factors'])
        ->reject(fn ($f) => $f['included_in_overall'])->keys()->all();
    expect($excluded)->toBe(['SAF']);
});

test('밴드 경계가 5/10 이다', function () {
    expect($this->rules['bands']['GREEN']['max'])->toBe(5);
    expect($this->rules['bands']['YELLOW']['min'])->toBe(6);
    expect($this->rules['bands']['YELLOW']['max'])->toBe(10);
    expect($this->rules['bands']['RED']['min'])->toBe(11);
});

test('S3 조건이 003 기준이다 (SAF04>=1, SAF01=3, SAF02=3, SAF05>=2 포함)', function () {
    $s3 = $this->rules['safety']['S3'];
    expect($s3)->toContain(['item' => 'SAF04', 'op' => '>=', 'value' => 1]);
    expect($s3)->toContain(['item' => 'SAF01', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF02', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF05', 'op' => '>=', 'value' => 2]);
    expect($s3)->toContain(['item' => 'SAF03', 'op' => '=',  'value' => 3]);
    expect($s3)->toContain(['item' => 'SAF06', 'op' => '>=', 'value' => 1]);
});

test('S2 에는 007 잔여분만 남는다', function () {
    $s2Items = collect($this->rules['safety']['S2'])->pluck('item')->unique()->sort()->values()->all();
    expect($s2Items)->toBe(['SAF01', 'SAF02', 'SAF03']);
});

test('tie_break 우선순위가 DEP 9 … FUT 1 이다', function () {
    $tb = collect($this->rules['factors'])->map(fn ($f) => $f['tie_break']);
    expect($tb['DEP'])->toBe(9);
    expect($tb['TRM'])->toBe(8);
    expect($tb['FAM'])->toBe(7);
    expect($tb['RSK'])->toBe(6);
    expect($tb['IMP'])->toBe(5);
    expect($tb['ISO'])->toBe(4);
    expect($tb['LIF'])->toBe(3);
    expect($tb['ANX'])->toBe(2);
    expect($tb['FUT'])->toBe(1);
});

test('솔루션 10종에 dedupe_group 이 있다', function () {
    expect($this->rules['solutions'])->toHaveCount(10);
    foreach ($this->rules['solutions'] as $code => $sol) {
        expect($sol)->toHaveKeys(['factor', 'title', 'dedupe_group']);
    }
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ScoringRuleSeederTest`
Expected: FAIL — `ScoringRuleSeeder` 없음

- [ ] **Step 3: 시더 작성**

`database/seeders/OyMsi/ScoringRuleSeeder.php`:

```php
<?php
namespace Database\Seeders\OyMsi;

use App\Models\Test;
use Illuminate\Database\Seeder;

class ScoringRuleSeeder extends Seeder
{
    public function run(): void
    {
        $test = Test::where('code', 'OY_MSI')->firstOrFail();
        if ($test->scoringRule) return;

        $test->scoringRule()->create(['version' => '1.0.0', 'rules' => $this->rules()]);
    }

    private function rules(): array
    {
        return [
            'factors' => [
                'DEP' => ['name' => '우울·무기력',        'included_in_overall' => true,  'tie_break' => 9],
                'TRM' => ['name' => '외상반응·안전감',    'included_in_overall' => true,  'tie_break' => 8],
                'FAM' => ['name' => '가족·보호환경',      'included_in_overall' => true,  'tie_break' => 7],
                'RSK' => ['name' => '디지털·물질·위험행동','included_in_overall' => true,  'tie_break' => 6],
                'IMP' => ['name' => '분노·충동조절',      'included_in_overall' => true,  'tie_break' => 5],
                'ISO' => ['name' => '고립·대인관계',      'included_in_overall' => true,  'tie_break' => 4],
                'LIF' => ['name' => '생활리듬·신체기능',  'included_in_overall' => true,  'tie_break' => 3],
                'ANX' => ['name' => '불안·긴장',          'included_in_overall' => true,  'tie_break' => 2],
                'FUT' => ['name' => '미래희망·학업진로 적응','included_in_overall' => true,'tie_break' => 1],
                'SAF' => ['name' => '자해·자살 안전',     'included_in_overall' => false, 'tie_break' => 0],
            ],

            // 요인 원점수(0~18) 기준. 위험지수 환산과 동치 (raw5=27.8 / raw6=33.3 / raw10=55.6 / raw11=61.1)
            'bands' => [
                'GREEN'  => ['min' => 0,  'max' => 5],
                'YELLOW' => ['min' => 6,  'max' => 10],
                'RED'    => ['min' => 11, 'max' => 18],
            ],

            // 전체 위험지수(0~100) 기준
            'overall_bands' => [
                'GREEN'  => ['min' => 0,    'max' => 29.9],
                'YELLOW' => ['min' => 30,   'max' => 59.9],
                'RED'    => ['min' => 60,   'max' => 100],
            ],

            // 003 예비안 기준 (spec §1.1). 위에서부터 먼저 맞는 등급을 채택한다.
            'safety' => [
                'S3' => [
                    ['item' => 'SAF03', 'op' => '=',  'value' => 3],
                    ['item' => 'SAF04', 'op' => '>=', 'value' => 1],
                    ['item' => 'SAF06', 'op' => '>=', 'value' => 1],
                    ['item' => 'SAF01', 'op' => '=',  'value' => 3],
                    ['item' => 'SAF02', 'op' => '=',  'value' => 3],
                    ['item' => 'SAF05', 'op' => '>=', 'value' => 2],
                ],
                'S2' => [
                    ['item' => 'SAF01', 'op' => '=', 'value' => 2],
                    ['item' => 'SAF02', 'op' => '=', 'value' => 2],
                    ['item' => 'SAF03', 'op' => '=', 'value' => 2],
                ],
                'S1' => [
                    ['item' => 'SAF01', 'op' => '=', 'value' => 1],
                    ['item' => 'SAF02', 'op' => '=', 'value' => 1],
                    ['item' => 'SAF03', 'op' => '=', 'value' => 1],
                    ['item' => 'SAF05', 'op' => '=', 'value' => 1],
                ],
            ],
            // SAF 문항 중 하나라도 무응답이면 최소 S1 (003 / 007 §5.3)
            'safety_missing_min_level' => 'S1',
            'safety_items' => ['SAF01', 'SAF02', 'SAF03', 'SAF04', 'SAF05', 'SAF06'],

            // 'factor' — 007 §7.3 의 환경 문항→요인 1:1 매핑(TRM06→TRM, FAM05→FAM,
            // RSK04/05/06→RSK). PriorityRanker 의 alert_bonus(§9.5)가 "해당 요인에만"
            // 붙어야 하므로(TRM/FAM/RSK 셋 다에 일괄 팬아웃하지 않음), 조건을 만족한
            // 문항이 실제로 속한 요인을 데이터로 명시한다.
            // (2026-07-28 리뷰 라운드 1 수정 — 최초 계획에는 factor 키가 없었고,
            // OyMsiScoringEngine::alertFactors() 가 environment_level>=2 일 때 TRM/FAM/RSK
            // 세 요인 모두에 +1000 을 뿌리는 버그가 있었다. 아래 alertFactors 도 함께 수정.)
            'environment' => [
                'E3' => [
                    ['item' => 'TRM06', 'op' => '=', 'value' => 3, 'factor' => 'TRM'],
                    ['item' => 'FAM05', 'op' => '=', 'value' => 3, 'factor' => 'FAM'],
                    ['item' => 'RSK06', 'op' => '=', 'value' => 3, 'factor' => 'RSK'],
                ],
                'E2' => [
                    ['item' => 'TRM06', 'op' => '=',  'value' => 2, 'factor' => 'TRM'],
                    ['item' => 'FAM05', 'op' => '=',  'value' => 2, 'factor' => 'FAM'],
                    ['item' => 'RSK06', 'op' => '=',  'value' => 2, 'factor' => 'RSK'],
                    ['item' => 'RSK04', 'op' => '>=', 'value' => 2, 'factor' => 'RSK'],
                    ['item' => 'RSK05', 'op' => '>=', 'value' => 2, 'factor' => 'RSK'],
                ],
                'E1' => [
                    ['item' => 'TRM06', 'op' => '=', 'value' => 1, 'factor' => 'TRM'],
                    ['item' => 'FAM05', 'op' => '=', 'value' => 1, 'factor' => 'FAM'],
                    ['item' => 'RSK06', 'op' => '=', 'value' => 1, 'factor' => 'RSK'],
                    ['item' => 'RSK05', 'op' => '=', 'value' => 1, 'factor' => 'RSK'],
                ],
            ],

            'case_codes' => [
                'general' => [
                    ['code' => 'R2', 'when' => ['red_count',    '>=', 2]],
                    ['code' => 'R1', 'when' => ['red_count',    '>=', 1]],
                    ['code' => 'Y2', 'when' => ['yellow_count', '>=', 3]],
                    ['code' => 'Y1', 'when' => ['yellow_count', '>=', 1]],
                    ['code' => 'G0', 'when' => null],
                ],
                'escalation' => [1 => 'C1', 2 => 'C2', 3 => 'C3'],
            ],

            'priority' => [
                'severity_weight' => ['GREEN' => 0, 'YELLOW' => 100, 'RED' => 200],
                'alert_bonus' => 1000,
                'limit' => 3,
            ],

            'strengths' => [
                'TRY_NEW'         => ['item' => 'FUT04', 'op' => '>=', 'value' => 2],
                'SMALL_GOAL'      => ['item' => 'FUT05', 'op' => '>=', 'value' => 2],
                'RECOVERY_HOPE'   => ['item' => 'FUT06', 'op' => '>=', 'value' => 2],
                'HONEST_RESPONSE' => ['always' => true],
            ],

            'solutions' => [
                'SOL_SAF_PLAN'        => ['factor' => 'SAF', 'title' => '개인 안전계획·즉시 연결',   'dedupe_group' => '안전'],
                'SOL_TRM_SAFETY'      => ['factor' => 'TRM', 'title' => '현재 안전확인·감각접지',     'dedupe_group' => '안전'],
                'SOL_DEP_ACTIVATION'  => ['factor' => 'DEP', 'title' => '행동활성화·작은 활동 시작',  'dedupe_group' => '생활회복'],
                'SOL_LIF_7DAY'        => ['factor' => 'LIF', 'title' => '7일 생활리듬 회복',          'dedupe_group' => '생활회복'],
                'SOL_ANX_BREATHING'   => ['factor' => 'ANX', 'title' => '호흡·이완·불안기록',         'dedupe_group' => '정서조절'],
                'SOL_IMP_STOP'        => ['factor' => 'IMP', 'title' => '멈춤-거리두기-재대화',       'dedupe_group' => '충동조절'],
                'SOL_ISO_CONNECT'     => ['factor' => 'ISO', 'title' => '부담 낮은 1인 연결·비대면 상담','dedupe_group' => '관계'],
                'SOL_FAM_PROTECT'     => ['factor' => 'FAM', 'title' => '보호자 안전성 평가·중재',    'dedupe_group' => '가족/보호'],
                'SOL_RSK_DIGITAL'     => ['factor' => 'RSK', 'title' => '디지털·물질·도박 위험 점검', 'dedupe_group' => '위험행동'],
                'SOL_FUT_3MONTH'      => ['factor' => 'FUT', 'title' => '3개월 진로탐색·작은 목표',   'dedupe_group' => '진로'],
            ],

            // 위에서부터 먼저 맞는 것을 채택
            'recheck' => [
                ['days' => 14, 'when_case_in' => ['C1', 'C2', 'C3', 'R1', 'R2'], 'reason' => 'RED_FACTOR_OR_SAFETY'],
                ['days' => 28, 'when_case_in' => ['Y1', 'Y2'], 'reason' => 'YELLOW_FACTOR'],
                ['days' => 90, 'when_case_in' => ['G0'],       'reason' => 'STABLE'],
            ],
        ];
    }
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ScoringRuleSeederTest`
Expected: PASS 7건

- [ ] **Step 5: 커밋**

```bash
git add database/seeders/OyMsi/ScoringRuleSeeder.php tests/Feature/OyMsi/ScoringRuleSeederTest.php
git commit -m "feat(oy-msi): 채점 규칙 시더 — 밴드·S/E·사례코드·우선순위·솔루션 전부 데이터화"
```

---

## Task 4: ScoringEngine 인터페이스 + 디스패처 (회귀 0)

**Files:**
- Create: `app/Services/Scoring/ScoringEngine.php`
- Create: `app/Services/Scoring/SignalScoringEngine.php`
- Modify: `app/Services/ScoringService.php` (전체 교체)
- Test: `tests/Feature/OyMsi/EngineDispatchTest.php`

**Interfaces:**
- Consumes: Task 1의 `tests.scoring_engine`
- Produces: `App\Services\Scoring\ScoringEngine` 인터페이스 — `score(TestAttempt $attempt): TestResult`. `ScoringService::score(TestAttempt): TestResult` 시그니처 유지(기존 컨트롤러 2곳이 호출 중). 엔진 등록 상수 `ScoringService::ENGINES`.

**주의:** `SignalScoringEngine`은 기존 `ScoringService` 본문을 **한 글자도 바꾸지 않고** 옮기기만 한다. 기존 `tests/Feature/ScoringServiceTest.php`가 그대로 통과해야 한다.

- [ ] **Step 1: 디스패치 테스트 작성**

`tests/Feature/OyMsi/EngineDispatchTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\ScoringEngine;
use App\Services\Scoring\SignalScoringEngine;
use App\Services\ScoringService;

function makeTest(string $code, ?string $engine = null): Test
{
    return Test::create([
        'code' => $code, 'room' => 'worker', 'title_easy' => 'x', 'title_pro' => 'X',
        'target' => 't', 'duration_min' => 1, 'item_count' => 1, 'areas' => ['A'],
        'result_type' => 'signal', 'description' => 'd', 'status' => 'draft',
    ] + ($engine ? ['scoring_engine' => $engine] : []));
}

test('scoring_engine 기본값이면 SignalScoringEngine 을 고른다', function () {
    expect(app(ScoringService::class)->engineFor(makeTest('DISPATCH1')))
        ->toBeInstanceOf(SignalScoringEngine::class);
});

test('알 수 없는 엔진 이름이면 예외를 던진다', function () {
    expect(fn () => app(ScoringService::class)->engineFor(makeTest('DISPATCH2', 'nope')))
        ->toThrow(InvalidArgumentException::class);
});

test('등록된 모든 엔진이 인터페이스를 구현한다', function () {
    foreach (ScoringService::ENGINES as $class) {
        expect(app($class))->toBeInstanceOf(ScoringEngine::class);
    }
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=EngineDispatchTest`
Expected: FAIL — `engineFor` 없음

- [ ] **Step 3: 인터페이스 작성**

`app/Services/Scoring/ScoringEngine.php`:

```php
<?php
namespace App\Services\Scoring;

use App\Models\TestAttempt;
use App\Models\TestResult;

interface ScoringEngine
{
    public function score(TestAttempt $attempt): TestResult;
}
```

- [ ] **Step 4: 기존 로직을 SignalScoringEngine 으로 이전**

`app/Services/Scoring/SignalScoringEngine.php` — 기존 `ScoringService` 본문 그대로 옮긴다:

```php
<?php
namespace App\Services\Scoring;

use App\Models\TestAttempt;
use App\Models\TestResult;

class SignalScoringEngine implements ScoringEngine
{
    private array $order = ['green' => 0, 'yellow' => 1, 'red' => 2];

    public function score(TestAttempt $attempt): TestResult
    {
        $attempt->loadMissing('test.items', 'test.scoringRule', 'answers');
        $rules = $attempt->test->scoringRule->rules;
        $itemsById = $attempt->test->items->keyBy('id');

        $areaScores = [];
        foreach ($attempt->answers as $ans) {
            if (!isset($itemsById[$ans->test_item_id])) continue;
            $item = $itemsById[$ans->test_item_id];
            $val = $item->reverse ? (6 - $ans->value) : $ans->value;
            $areaScores[$item->area] = ($areaScores[$item->area] ?? 0) + $val;
        }

        $areaSignals = [];
        foreach ($areaScores as $area => $sum) {
            $th = $rules['areas'][$area] ?? ['yellow' => PHP_INT_MAX, 'red' => PHP_INT_MAX];
            $areaSignals[$area] = $sum >= $th['red'] ? 'red' : ($sum >= $th['yellow'] ? 'yellow' : 'green');
        }

        $overall = 'green';
        foreach ($areaSignals as $sig) {
            if ($this->order[$sig] > $this->order[$overall]) $overall = $sig;
        }

        $levelText = [
            'green' => '양호한 단계',
            'yellow' => '관심과 조기지원이 필요한 단계',
            'red' => '적극적 지원이 필요한 단계',
        ];

        return TestResult::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                'area_scores' => $areaScores,
                'area_signals' => $areaSignals,
                'overall_signal' => $overall,
                'overall_level' => $levelText[$overall],
                'interpretation' => $rules['interpretation'][$overall] ?? '',
                'recommendations' => $rules['recommendations'][$overall] ?? [],
            ]
        );
    }
}
```

- [ ] **Step 5: ScoringService 를 디스패처로 교체**

`app/Services/ScoringService.php` — 전체 교체:

```php
<?php
namespace App\Services;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Services\Scoring\ScoringEngine;
use App\Services\Scoring\SignalScoringEngine;
use InvalidArgumentException;

class ScoringService
{
    public const ENGINES = [
        'signal' => SignalScoringEngine::class,
        // 'oy_msi' => OyMsiScoringEngine::class,   ← Task 9 에서 활성화
    ];

    public function engineFor(Test $test): ScoringEngine
    {
        $key = $test->scoring_engine ?: 'signal';
        if (!isset(self::ENGINES[$key])) {
            throw new InvalidArgumentException("알 수 없는 채점 엔진: {$key}");
        }
        return app(self::ENGINES[$key]);
    }

    public function score(TestAttempt $attempt): TestResult
    {
        $attempt->loadMissing('test');
        return $this->engineFor($attempt->test)->score($attempt);
    }
}
```

- [ ] **Step 6: 디스패치 + 기존 채점 테스트 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter="EngineDispatchTest|ScoringServiceTest"`
Expected: PASS 전부. `ScoringServiceTest`가 깨지면 이전 과정에서 로직이 변형된 것이다.

- [ ] **Step 7: 전체 회귀 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 기존 pass 전부 유지

- [ ] **Step 8: 커밋**

```bash
git add app/Services/Scoring/ScoringEngine.php app/Services/Scoring/SignalScoringEngine.php \
        app/Services/ScoringService.php tests/Feature/OyMsi/EngineDispatchTest.php
git commit -m "refactor(scoring): ScoringService 를 검사별 엔진 디스패처로 분리 (기존 로직 무변경 이전)"
```

---

## Task 5: ItemScorer + FactorScorer

**Files:**
- Create: `app/Services/Scoring/OyMsi/ItemScorer.php`
- Create: `app/Services/Scoring/OyMsi/FactorScorer.php`
- Test: `tests/Feature/OyMsi/ItemScorerTest.php`, `tests/Feature/OyMsi/FactorScorerTest.php`

**Interfaces:**
- Consumes: Task 3의 `rules['factors']`, `rules['bands']`, `rules['overall_bands']`
- Produces:
  - `ItemScorer::score(array $rawByItemCode, array $reverseItemCodes): array` — `item_code => int|null`
  - `FactorScorer::scoreAll(array $scoredByItemCode, array $itemCodesByFactor, array $rules): array` — 요인코드 => `['raw'=>float|null, 'answered_count'=>int, 'risk_index'=>float|null, 'band'=>string|null, 'score_status'=>string]`
  - `FactorScorer::overall(array $factorScores, array $rules): array` — `['raw'=>float, 'max'=>int, 'risk_index'=>float, 'band'=>string]`

- [ ] **Step 1: ItemScorer 테스트 작성**

`tests/Feature/OyMsi/ItemScorerTest.php`:

```php
<?php
use App\Services\Scoring\OyMsi\ItemScorer;

test('일반 문항은 원점수를 그대로 쓴다', function () {
    expect((new ItemScorer())->score(['DEP01' => 0, 'DEP02' => 3], []))
        ->toBe(['DEP01' => 0, 'DEP02' => 3]);
});

test('역채점 문항은 3 - raw 로 뒤집는다 (007 T06·T07)', function () {
    $scored = (new ItemScorer())->score(
        ['FUT04' => 3, 'FUT05' => 0, 'FUT06' => 1],
        ['FUT04', 'FUT05', 'FUT06']
    );
    expect($scored['FUT04'])->toBe(0); // T06
    expect($scored['FUT05'])->toBe(3); // T07
    expect($scored['FUT06'])->toBe(2);
});

test('응답거부는 null 로 남고 역채점하지 않는다', function () {
    $scored = (new ItemScorer())->score(['FUT04' => null, 'DEP01' => null], ['FUT04']);
    expect($scored['FUT04'])->toBeNull();
    expect($scored['DEP01'])->toBeNull();
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ItemScorerTest`
Expected: FAIL — 클래스 없음

- [ ] **Step 3: ItemScorer 구현**

`app/Services/Scoring/OyMsi/ItemScorer.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class ItemScorer
{
    private const MAX = 3;

    /**
     * @param  array<string, int|null>  $rawByItemCode
     * @param  list<string>             $reverseItemCodes
     * @return array<string, int|null>
     */
    public function score(array $rawByItemCode, array $reverseItemCodes): array
    {
        $reverse = array_flip($reverseItemCodes);
        $out = [];
        foreach ($rawByItemCode as $code => $raw) {
            if ($raw === null) { $out[$code] = null; continue; }
            $out[$code] = isset($reverse[$code]) ? self::MAX - (int) $raw : (int) $raw;
        }
        return $out;
    }
}
```

- [ ] **Step 4: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ItemScorerTest`
Expected: PASS 3건

- [ ] **Step 5: FactorScorer 테스트 작성**

`tests/Feature/OyMsi/FactorScorerTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\FactorScorer;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->scorer = new FactorScorer();
});

/** DEP 6문항만 담은 최소 입력 */
function depScores(array $values): array
{
    $out = [];
    foreach ($values as $i => $v) $out[sprintf('DEP%02d', $i + 1)] = $v;
    return $out;
}
function depMap(): array
{
    return ['DEP' => ['DEP01', 'DEP02', 'DEP03', 'DEP04', 'DEP05', 'DEP06']];
}

test('전부 0이면 GREEN 이고 위험지수 0 이다 (T01)', function () {
    $r = $this->scorer->scoreAll(depScores([0, 0, 0, 0, 0, 0]), depMap(), $this->rules);
    expect($r['DEP']['raw'])->toBe(0.0);
    expect($r['DEP']['risk_index'])->toBe(0.0);
    expect($r['DEP']['band'])->toBe('GREEN');
    expect($r['DEP']['score_status'])->toBe('COMPLETE');
});

test('경계값 5 GREEN / 6 YELLOW / 10 YELLOW / 11 RED (T02~T05)', function () {
    $band = fn (array $v) => $this->scorer->scoreAll(depScores($v), depMap(), $this->rules)['DEP']['band'];
    expect($band([3, 2, 0, 0, 0, 0]))->toBe('GREEN');   // 5
    expect($band([3, 3, 0, 0, 0, 0]))->toBe('YELLOW');  // 6
    expect($band([3, 3, 3, 1, 0, 0]))->toBe('YELLOW');  // 10
    expect($band([3, 3, 3, 2, 0, 0]))->toBe('RED');     // 11
});

test('위험지수는 raw/18*100 을 소수 1자리로 반올림한다', function () {
    $r = $this->scorer->scoreAll(depScores([3, 3, 3, 3, 1, 0]), depMap(), $this->rules); // 13
    expect($r['DEP']['risk_index'])->toBe(72.2);
});

test('5문항만 응답하면 PARTIAL 로 6/5 환산한다 (T16)', function () {
    $r = $this->scorer->scoreAll(depScores([2, 2, 2, 2, 2, null]), depMap(), $this->rules); // 10 → 12.0
    expect($r['DEP']['score_status'])->toBe('PARTIAL');
    expect($r['DEP']['answered_count'])->toBe(5);
    expect($r['DEP']['raw'])->toBe(12.0);
    expect($r['DEP']['band'])->toBe('RED');
});

test('4문항 이하면 UNSCORABLE 이고 점수를 내지 않는다 (T17)', function () {
    $r = $this->scorer->scoreAll(depScores([2, 2, 2, 2, null, null]), depMap(), $this->rules);
    expect($r['DEP']['score_status'])->toBe('UNSCORABLE');
    expect($r['DEP']['raw'])->toBeNull();
    expect($r['DEP']['risk_index'])->toBeNull();
    expect($r['DEP']['band'])->toBeNull();
});

test('전체 지수는 SAF 를 빼고 9요인 162점 만점으로 계산한다', function () {
    $scored = [];
    $factors = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT'];
    foreach ($factors as $f) {
        foreach (range(1, 6) as $i) $scored[sprintf('%s%02d', $f, $i)] = 1; // 요인당 6점
    }
    foreach (range(1, 6) as $i) $scored[sprintf('SAF%02d', $i)] = 3; // SAF 는 총점 제외

    $map = [];
    foreach ([...$factors, 'SAF'] as $f) {
        $map[$f] = array_map(fn ($i) => sprintf('%s%02d', $f, $i), range(1, 6));
    }

    $overall = $this->scorer->overall(
        $this->scorer->scoreAll($scored, $map, $this->rules),
        $this->rules
    );

    expect($overall['raw'])->toBe(54.0);
    expect($overall['max'])->toBe(162);
    expect($overall['risk_index'])->toBe(33.3);
    expect($overall['band'])->toBe('YELLOW');
});
```

- [ ] **Step 6: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=FactorScorerTest`
Expected: FAIL — 클래스 없음

- [ ] **Step 7: FactorScorer 구현**

`app/Services/Scoring/OyMsi/FactorScorer.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class FactorScorer
{
    private const ITEMS_PER_FACTOR = 6;
    private const MAX_PER_FACTOR = 18;

    /**
     * @param  array<string, int|null>     $scoredByItemCode
     * @param  array<string, list<string>> $itemCodesByFactor
     */
    public function scoreAll(array $scoredByItemCode, array $itemCodesByFactor, array $rules): array
    {
        $out = [];
        foreach ($itemCodesByFactor as $factor => $codes) {
            $values = [];
            foreach ($codes as $code) {
                $v = $scoredByItemCode[$code] ?? null;
                if ($v !== null) $values[] = $v;
            }
            $count = count($values);

            if ($count === self::ITEMS_PER_FACTOR) {
                $raw = (float) array_sum($values);
                $status = 'COMPLETE';
            } elseif ($count === self::ITEMS_PER_FACTOR - 1) {
                // 007 §5.3 — 5문항 응답 시 6/5 환산
                $raw = round(array_sum($values) * self::ITEMS_PER_FACTOR / ($count), 1);
                $status = 'PARTIAL';
            } else {
                $raw = null;
                $status = 'UNSCORABLE';
            }

            $out[$factor] = [
                'raw' => $raw,
                'answered_count' => $count,
                'risk_index' => $raw === null ? null : round($raw / self::MAX_PER_FACTOR * 100, 1),
                'band' => $raw === null ? null : $this->pickBand($raw, $rules['bands']),
                'score_status' => $status,
            ];
        }
        return $out;
    }

    public function overall(array $factorScores, array $rules): array
    {
        $included = array_keys(array_filter(
            $rules['factors'],
            fn ($f) => $f['included_in_overall']
        ));

        $raw = 0.0;
        foreach ($included as $factor) {
            $raw += $factorScores[$factor]['raw'] ?? 0.0;
        }
        $max = count($included) * self::MAX_PER_FACTOR;
        $index = round($raw / $max * 100, 1);

        return [
            'raw' => round($raw, 1),
            'max' => $max,
            'risk_index' => $index,
            'band' => $this->pickBand($index, $rules['overall_bands']),
        ];
    }

    /** RED → YELLOW → GREEN 순으로 min 을 만족하는 첫 밴드 */
    private function pickBand(float $value, array $bands): string
    {
        foreach (['RED', 'YELLOW', 'GREEN'] as $name) {
            if ($value >= $bands[$name]['min']) return $name;
        }
        return 'GREEN';
    }
}
```

- [ ] **Step 8: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=FactorScorerTest`
Expected: PASS 6건

- [ ] **Step 9: 커밋**

```bash
git add app/Services/Scoring/OyMsi/ItemScorer.php app/Services/Scoring/OyMsi/FactorScorer.php \
        tests/Feature/OyMsi/ItemScorerTest.php tests/Feature/OyMsi/FactorScorerTest.php
git commit -m "feat(oy-msi): ItemScorer·FactorScorer — 역채점·결측환산·위험지수·밴드"
```

---

## Task 6: ConditionMatcher + SafetyEvaluator + EnvironmentEvaluator

**Files:**
- Create: `app/Services/Scoring/OyMsi/ConditionMatcher.php`
- Create: `app/Services/Scoring/OyMsi/SafetyEvaluator.php`
- Create: `app/Services/Scoring/OyMsi/EnvironmentEvaluator.php`
- Test: `tests/Feature/OyMsi/SafetyEvaluatorTest.php`, `tests/Feature/OyMsi/EnvironmentEvaluatorTest.php`

**Interfaces:**
- Consumes: Task 3의 `rules['safety']`, `rules['safety_missing_min_level']`, `rules['safety_items']`, `rules['environment']`
- Produces:
  - `ConditionMatcher::anyMatches(array $conditions, array $rawByItemCode): bool` — 조건 `['item'=>string,'op'=>'='|'>='|'<=','value'=>int]` 중 하나라도 참이면 true. 해당 문항이 null(응답거부)이면 그 조건은 거짓.
  - `SafetyEvaluator::evaluate(array $rawByItemCode, array $rules): string` — `'S0'|'S1'|'S2'|'S3'`
  - `EnvironmentEvaluator::evaluate(array $rawByItemCode, array $rules): string` — `'E0'|'E1'|'E2'|'E3'`

**입력은 `raw`(원점수)다.** 역채점된 `scored` 값이 아니다 — SAF·경보 문항은 역채점 대상이 아니므로 둘이 같지만, 규칙이 문서의 원응답 기준이라는 점을 코드에서 분명히 한다.

- [ ] **Step 1: SafetyEvaluator 테스트 작성**

`tests/Feature/OyMsi/SafetyEvaluatorTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\SafetyEvaluator;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->eval = new SafetyEvaluator();
});

/** SAF 6문항 전부 0 인 기본 응답에 덮어쓰기 */
function saf(array $overrides = []): array
{
    $base = ['SAF01' => 0, 'SAF02' => 0, 'SAF03' => 0, 'SAF04' => 0, 'SAF05' => 0, 'SAF06' => 0];
    return array_merge($base, $overrides);
}

test('SAF 전부 0 이면 S0 이다', function () {
    expect($this->eval->evaluate(saf(), $this->rules))->toBe('S0');
});

test('SAF03=1 이면 S1 이다 (T08)', function () {
    expect($this->eval->evaluate(saf(['SAF03' => 1]), $this->rules))->toBe('S1');
});

test('SAF01=2 이면 S2 이다', function () {
    expect($this->eval->evaluate(saf(['SAF01' => 2]), $this->rules))->toBe('S2');
});

test('SAF04=2 이면 S3 이다 (T09)', function () {
    expect($this->eval->evaluate(saf(['SAF04' => 2]), $this->rules))->toBe('S3');
});

test('SAF06=1 이면 S3 이다 (T10)', function () {
    expect($this->eval->evaluate(saf(['SAF06' => 1]), $this->rules))->toBe('S3');
});

test('003 기준 — SAF04=1 은 S2 가 아니라 S3 이다', function () {
    expect($this->eval->evaluate(saf(['SAF04' => 1]), $this->rules))->toBe('S3');
});

test('003 기준 — SAF01=3 · SAF02=3 · SAF05=2 도 S3 이다', function () {
    expect($this->eval->evaluate(saf(['SAF01' => 3]), $this->rules))->toBe('S3');
    expect($this->eval->evaluate(saf(['SAF02' => 3]), $this->rules))->toBe('S3');
    expect($this->eval->evaluate(saf(['SAF05' => 2]), $this->rules))->toBe('S3');
});

test('SAF 문항 무응답이면 최소 S1 이다 (T11)', function () {
    expect($this->eval->evaluate(saf(['SAF02' => null]), $this->rules))->toBe('S1');
});

test('무응답이 있어도 더 높은 등급이 나오면 그 등급을 쓴다', function () {
    expect($this->eval->evaluate(saf(['SAF02' => null, 'SAF04' => 3]), $this->rules))->toBe('S3');
});

test('높은 등급이 낮은 등급보다 우선한다', function () {
    // SAF03=3(S3) 와 SAF01=1(S1) 이 동시에 있으면 S3
    expect($this->eval->evaluate(saf(['SAF03' => 3, 'SAF01' => 1]), $this->rules))->toBe('S3');
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=SafetyEvaluatorTest`
Expected: FAIL — 클래스 없음

- [ ] **Step 3: ConditionMatcher 구현**

`app/Services/Scoring/OyMsi/ConditionMatcher.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

use InvalidArgumentException;

class ConditionMatcher
{
    /**
     * @param  list<array{item:string, op:string, value:int}>  $conditions
     * @param  array<string, int|null>                          $rawByItemCode
     */
    public function anyMatches(array $conditions, array $rawByItemCode): bool
    {
        foreach ($conditions as $c) {
            if ($this->matches($c, $rawByItemCode)) return true;
        }
        return false;
    }

    /** @param array{item:string, op:string, value:int} $condition */
    public function matches(array $condition, array $rawByItemCode): bool
    {
        $raw = $rawByItemCode[$condition['item']] ?? null;
        if ($raw === null) return false; // 무응답은 조건 불성립 (별도 규칙으로 처리)

        return match ($condition['op']) {
            '='  => $raw === $condition['value'],
            '>=' => $raw >= $condition['value'],
            '<=' => $raw <= $condition['value'],
            default => throw new InvalidArgumentException("알 수 없는 연산자: {$condition['op']}"),
        };
    }
}
```

- [ ] **Step 4: SafetyEvaluator 구현**

`app/Services/Scoring/OyMsi/SafetyEvaluator.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class SafetyEvaluator
{
    /** 높은 등급부터 검사한다 */
    private const LEVELS = ['S3', 'S2', 'S1'];

    public function __construct(private ConditionMatcher $matcher = new ConditionMatcher()) {}

    /** @param array<string, int|null> $rawByItemCode */
    public function evaluate(array $rawByItemCode, array $rules): string
    {
        foreach (self::LEVELS as $level) {
            $conditions = $rules['safety'][$level] ?? [];
            if ($this->matcher->anyMatches($conditions, $rawByItemCode)) {
                return $level;
            }
        }

        // 007 §5.3 / 003 — 안전문항 응답거부·무응답은 0점 처리 금지, 최소 S1
        // 폴백 리터럴을 두지 않는다. 키가 없으면 조용히 옛 동작으로 돌아가는 대신 시끄럽게 죽는다
        // (§1.1 이 안전등급 기준 교체를 예고하므로, 그 작업 중 키 오타가 묻히면 안 된다).
        if ($this->hasMissingSafetyItem($rawByItemCode, $rules)) {
            return $rules['safety_missing_min_level']
                ?? throw new \InvalidArgumentException(
                    'scoring_rules 에 safety_missing_min_level 이 없습니다 — 안전문항 무응답 처리 기준이 정의되지 않았습니다.'
                );
        }

        return 'S0';
    }

    private function hasMissingSafetyItem(array $rawByItemCode, array $rules): bool
    {
        foreach ($rules['safety_items'] ?? [] as $code) {
            if (!array_key_exists($code, $rawByItemCode) || $rawByItemCode[$code] === null) {
                return true;
            }
        }
        return false;
    }
}
```

- [ ] **Step 5: SafetyEvaluator 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=SafetyEvaluatorTest`
Expected: PASS 10건

- [ ] **Step 6: EnvironmentEvaluator 테스트 작성**

`tests/Feature/OyMsi/EnvironmentEvaluatorTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\EnvironmentEvaluator;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->eval = new EnvironmentEvaluator();
});

function envAnswers(array $overrides = []): array
{
    $base = ['TRM06' => 0, 'FAM05' => 0, 'RSK04' => 0, 'RSK05' => 0, 'RSK06' => 0];
    return array_merge($base, $overrides);
}

test('경보 문항 전부 0 이면 E0 이다', function () {
    expect($this->eval->evaluate(envAnswers(), $this->rules))->toBe('E0');
});

test('FAM05=3 이면 E3 이다 (T12)', function () {
    expect($this->eval->evaluate(envAnswers(['FAM05' => 3]), $this->rules))->toBe('E3');
});

test('TRM06=3 · RSK06=3 도 E3 이다', function () {
    expect($this->eval->evaluate(envAnswers(['TRM06' => 3]), $this->rules))->toBe('E3');
    expect($this->eval->evaluate(envAnswers(['RSK06' => 3]), $this->rules))->toBe('E3');
});

test('RSK04>=2 · RSK05>=2 는 E2 이다', function () {
    expect($this->eval->evaluate(envAnswers(['RSK04' => 2]), $this->rules))->toBe('E2');
    expect($this->eval->evaluate(envAnswers(['RSK05' => 3]), $this->rules))->toBe('E2');
});

test('TRM06=1 은 E1 이다', function () {
    expect($this->eval->evaluate(envAnswers(['TRM06' => 1]), $this->rules))->toBe('E1');
});

test('RSK04=1 만으로는 E0 이다 (E1 조건에 RSK04 없음)', function () {
    expect($this->eval->evaluate(envAnswers(['RSK04' => 1]), $this->rules))->toBe('E0');
});

test('높은 등급이 우선한다', function () {
    expect($this->eval->evaluate(envAnswers(['TRM06' => 1, 'FAM05' => 3]), $this->rules))->toBe('E3');
});
```

- [ ] **Step 7: EnvironmentEvaluator 구현**

`app/Services/Scoring/OyMsi/EnvironmentEvaluator.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class EnvironmentEvaluator
{
    private const LEVELS = ['E3', 'E2', 'E1'];

    // 007 §9.5 — alert_bonus 는 "해당 요인에 HIGH/CRITICAL 경보가 있으면" 붙는다.
    // E2=HIGH, E3=CRITICAL 로 본다(003/007 의 S2=HIGH·S3=CRITICAL 관례와 동일).
    // E1 은 WARN 수준으로 간주해 alert_bonus 대상에서 제외한다.
    // (2026-07-28 리뷰 라운드 1 추가 — alertedFactors() 없이는 OyMsiScoringEngine 이
    // "환경등급이 E2/E3 다" 라고 TRM/FAM/RSK 세 요인 모두에 뿌리는 팬아웃 버그가 있었다.)
    private const ALERT_BONUS_LEVELS = ['E3', 'E2'];

    public function __construct(private ConditionMatcher $matcher = new ConditionMatcher()) {}

    /** @param array<string, int|null> $rawByItemCode */
    public function evaluate(array $rawByItemCode, array $rules): string
    {
        foreach (self::LEVELS as $level) {
            if ($this->matcher->anyMatches($rules['environment'][$level] ?? [], $rawByItemCode)) {
                return $level;
            }
        }
        return 'E0';
    }

    /**
     * 007 §7.3(환경 문항→요인 1:1 매핑) + §9.5(alert_bonus 는 "해당 요인"에만) —
     * E2/E3 조건 중 실제로 만족된 것만, 그 조건에 데이터로 명시된 factor 에 귀속시킨다.
     *
     * @param  array<string, int|null>  $rawByItemCode
     * @return list<string>  경보가 걸린 요인 코드(중복 제거)
     */
    public function alertedFactors(array $rawByItemCode, array $rules): array
    {
        $factors = [];
        foreach (self::ALERT_BONUS_LEVELS as $level) {
            foreach ($rules['environment'][$level] ?? [] as $condition) {
                if (!$this->matcher->matches($condition, $rawByItemCode)) continue;
                $factor = $condition['factor'] ?? throw new \InvalidArgumentException(
                    "scoring_rules.environment.{$level} 조건에 factor 가 없습니다 — "
                    . "alert_bonus 를 어느 요인에 줄지 정의되지 않았습니다: " . json_encode($condition)
                );
                $factors[$factor] = true;
            }
        }
        return array_keys($factors);
    }
}
```

- [ ] **Step 8: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter="SafetyEvaluatorTest|EnvironmentEvaluatorTest"`
Expected: PASS 17건

- [ ] **Step 9: 커밋**

```bash
git add app/Services/Scoring/OyMsi/ConditionMatcher.php \
        app/Services/Scoring/OyMsi/SafetyEvaluator.php \
        app/Services/Scoring/OyMsi/EnvironmentEvaluator.php \
        tests/Feature/OyMsi/SafetyEvaluatorTest.php \
        tests/Feature/OyMsi/EnvironmentEvaluatorTest.php
git commit -m "feat(oy-msi): 안전등급 S0~S3(003 기준)·환경경보 E0~E3 평가기"
```

---

## Task 7: CaseClassifier + PriorityRanker

**Files:**
- Create: `app/Services/Scoring/OyMsi/CaseClassifier.php`
- Create: `app/Services/Scoring/OyMsi/PriorityRanker.php`
- Test: `tests/Feature/OyMsi/CaseClassifierTest.php`, `tests/Feature/OyMsi/PriorityRankerTest.php`

**Interfaces:**
- Consumes: Task 3의 `rules['case_codes']`, `rules['priority']`, `rules['factors']`; Task 5의 `FactorScorer::scoreAll()` 반환 구조; Task 6의 S/E 등급 문자열
- Produces:
  - `CaseClassifier::general(array $factorScores, array $rules): array` — `['code'=>string, 'red_count'=>int, 'yellow_count'=>int]`. SAF 는 세지 않는다.
  - `CaseClassifier::final(string $generalCode, string $safetyLevel, string $environmentLevel, array $rules): string`
  - `PriorityRanker::rank(array $factorScores, array $rules, array $alertFactors = []): array` — 상위 N개 `[['factor'=>string,'band'=>string,'risk_index'=>float,'score'=>float,'rank'=>int], ...]`

- [ ] **Step 1: CaseClassifier 테스트 작성**

`tests/Feature/OyMsi/CaseClassifierTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\CaseClassifier;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->classifier = new CaseClassifier();
});

/**
 * 9개 일반요인 밴드를 지정해 factorScores 형태로 만든다.
 * @param array<string,string> $bands 예: ['DEP'=>'RED']  나머지는 GREEN
 */
function factorsWithBands(array $bands): array
{
    $all = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT', 'SAF'];
    $out = [];
    foreach ($all as $f) {
        $band = $bands[$f] ?? 'GREEN';
        $raw = match ($band) { 'RED' => 12.0, 'YELLOW' => 8.0, default => 2.0 };
        $out[$f] = [
            'raw' => $raw, 'answered_count' => 6,
            'risk_index' => round($raw / 18 * 100, 1),
            'band' => $band, 'score_status' => 'COMPLETE',
        ];
    }
    return $out;
}

test('빨강 0 노랑 0 이면 G0 이다', function () {
    expect($this->classifier->general(factorsWithBands([]), $this->rules)['code'])->toBe('G0');
});

test('노랑 1개면 Y1, 3개면 Y2 이다 (T15)', function () {
    expect($this->classifier->general(factorsWithBands(['DEP' => 'YELLOW']), $this->rules)['code'])->toBe('Y1');
    expect($this->classifier->general(
        factorsWithBands(['DEP' => 'YELLOW', 'ANX' => 'YELLOW', 'IMP' => 'YELLOW']), $this->rules
    )['code'])->toBe('Y2');
});

test('빨강 1개면 R1, 2개면 R2 이다 (T13·T14)', function () {
    expect($this->classifier->general(factorsWithBands(['DEP' => 'RED']), $this->rules)['code'])->toBe('R1');
    expect($this->classifier->general(
        factorsWithBands(['DEP' => 'RED', 'ANX' => 'RED']), $this->rules
    )['code'])->toBe('R2');
});

test('SAF 밴드는 일반 사례코드 계산에서 제외한다', function () {
    $r = $this->classifier->general(factorsWithBands(['SAF' => 'RED']), $this->rules);
    expect($r['code'])->toBe('G0');
    expect($r['red_count'])->toBe(0);
});

test('S/E 가 0 이면 일반코드를 그대로 쓴다', function () {
    expect($this->classifier->final('R1', 'S0', 'E0', $this->rules))->toBe('R1');
});

test('max(S,E) 만큼 C 코드로 격상한다', function () {
    expect($this->classifier->final('G0', 'S1', 'E0', $this->rules))->toBe('C1');
    expect($this->classifier->final('G0', 'S0', 'E2', $this->rules))->toBe('C2');
    expect($this->classifier->final('R2', 'S2', 'E3', $this->rules))->toBe('C3');
});

test('FAM05=3 시나리오는 E3 → C3 이다 (T12)', function () {
    expect($this->classifier->final('G0', 'S0', 'E3', $this->rules))->toBe('C3');
});

test('SAF03=1 시나리오는 S1 → C1 이다 (T08)', function () {
    expect($this->classifier->final('G0', 'S1', 'E0', $this->rules))->toBe('C1');
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=CaseClassifierTest`
Expected: FAIL — 클래스 없음

- [ ] **Step 3: CaseClassifier 구현**

`app/Services/Scoring/OyMsi/CaseClassifier.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class CaseClassifier
{
    /** @return array{code:string, red_count:int, yellow_count:int} */
    public function general(array $factorScores, array $rules): array
    {
        $red = 0; $yellow = 0;
        foreach ($factorScores as $factor => $score) {
            if (!($rules['factors'][$factor]['included_in_overall'] ?? false)) continue;
            if ($score['band'] === 'RED') $red++;
            if ($score['band'] === 'YELLOW') $yellow++;
        }

        $counts = ['red_count' => $red, 'yellow_count' => $yellow];
        // 폴백 리터럴을 두지 않는다 — 규칙에 포괄 항목이 없으면 조용히 G0(안정·예방군)로
        // 분류되는 대신 시끄럽게 죽는다.
        $code = null;
        foreach ($rules['case_codes']['general'] as $entry) {
            if ($entry['when'] === null) { $code = $entry['code']; break; }
            [$field, $op, $value] = $entry['when'];
            $actual = $counts[$field];
            $hit = $op === '>=' ? $actual >= $value : $actual === $value;
            if ($hit) { $code = $entry['code']; break; }
        }
        if ($code === null) {
            throw new \InvalidArgumentException(
                'scoring_rules.case_codes.general 에 일치하는 항목이 없습니다 — 마지막 항목의 when 이 null(포괄 규칙)인지 확인하십시오.'
            );
        }

        return ['code' => $code] + $counts;
    }

    public function final(
        string $generalCode,
        string $safetyLevel,
        string $environmentLevel,
        array $rules
    ): string {
        $highest = max($this->rank($safetyLevel), $this->rank($environmentLevel));
        if ($highest === 0) return $generalCode;

        // 격상 경로에서 조용한 완화 금지 — 매핑이 없으면 경보가 사례코드에 반영되지 않는다.
        if (!array_key_exists($highest, $rules['case_codes']['escalation'])) {
            throw new \InvalidArgumentException(
                "scoring_rules.case_codes.escalation 에 등급 {$highest} 매핑이 없습니다 — 안전·환경 경보가 사례코드로 반영되지 않습니다."
            );
        }
        return $rules['case_codes']['escalation'][$highest];
    }

    /** 'S2' → 2, 'E0' → 0 */
    private function rank(string $level): int
    {
        return (int) substr($level, 1);
    }
}
```

- [ ] **Step 4: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=CaseClassifierTest`
Expected: PASS 8건

- [ ] **Step 5: PriorityRanker 테스트 작성**

`tests/Feature/OyMsi/PriorityRankerTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\PriorityRanker;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->ranker = new PriorityRanker();
});

/** @param array<string,float> $raws 요인별 원점수. 나머지는 0 */
function factorsWithRaw(array $raws): array
{
    $all = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT', 'SAF'];
    $out = [];
    foreach ($all as $f) {
        $raw = $raws[$f] ?? 0.0;
        $band = $raw >= 11 ? 'RED' : ($raw >= 6 ? 'YELLOW' : 'GREEN');
        $out[$f] = [
            'raw' => $raw, 'answered_count' => 6,
            'risk_index' => round($raw / 18 * 100, 1),
            'band' => $band, 'score_status' => 'COMPLETE',
        ];
    }
    return $out;
}

test('상위 3개만 돌려주고 rank 를 1부터 매긴다', function () {
    $top = $this->ranker->rank(factorsWithRaw(['DEP' => 14, 'LIF' => 12, 'FUT' => 8, 'ANX' => 7]), $this->rules);
    expect($top)->toHaveCount(3);
    expect(array_column($top, 'factor'))->toBe(['DEP', 'LIF', 'FUT']);
    expect(array_column($top, 'rank'))->toBe([1, 2, 3]);
});

test('SAF 는 순위에 들어가지 않는다', function () {
    $top = $this->ranker->rank(factorsWithRaw(['SAF' => 18, 'DEP' => 7]), $this->rules);
    expect(array_column($top, 'factor'))->not->toContain('SAF');
});

test('밴드가 위험지수보다 우선한다 (severity_weight)', function () {
    // ANX RED(11) vs DEP YELLOW(10) → RED 가 위
    $top = $this->ranker->rank(factorsWithRaw(['ANX' => 11, 'DEP' => 10]), $this->rules);
    expect($top[0]['factor'])->toBe('ANX');
});

test('점수가 같으면 tie_break 가 높은 요인이 앞선다', function () {
    // DEP(9) vs ANX(2) 동점 → DEP
    $top = $this->ranker->rank(factorsWithRaw(['DEP' => 8, 'ANX' => 8]), $this->rules);
    expect($top[0]['factor'])->toBe('DEP');
});

test('경보가 걸린 요인은 alert_bonus 로 최상단에 온다', function () {
    // FAM 은 GREEN(2) 이지만 경보가 있으면 RED 인 DEP 보다 위
    $top = $this->ranker->rank(factorsWithRaw(['DEP' => 14, 'FAM' => 2]), $this->rules, ['FAM']);
    expect($top[0]['factor'])->toBe('FAM');
});

test('UNSCORABLE 요인은 순위에서 제외한다', function () {
    $factors = factorsWithRaw(['DEP' => 14, 'LIF' => 12, 'FUT' => 8]);
    $factors['LIF'] = ['raw' => null, 'answered_count' => 3, 'risk_index' => null,
                       'band' => null, 'score_status' => 'UNSCORABLE'];
    $top = $this->ranker->rank($factors, $this->rules);
    expect(array_column($top, 'factor'))->not->toContain('LIF');
});
```

- [ ] **Step 6: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=PriorityRankerTest`
Expected: FAIL — 클래스 없음

- [ ] **Step 7: PriorityRanker 구현**

`app/Services/Scoring/OyMsi/PriorityRanker.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class PriorityRanker
{
    /**
     * 007 §9.5 — severity_weight + risk_index + alert_bonus + tie_break
     *
     * @param  list<string>  $alertFactors  HIGH/CRITICAL 경보가 걸린 요인 코드
     * @return list<array{factor:string, band:string, risk_index:float, score:float, rank:int}>
     */
    public function rank(array $factorScores, array $rules, array $alertFactors = []): array
    {
        $weights = $rules['priority']['severity_weight'];
        $bonus = $rules['priority']['alert_bonus'];
        $limit = $rules['priority']['limit'];
        $alerts = array_flip($alertFactors);

        $rows = [];
        foreach ($factorScores as $factor => $score) {
            if (!($rules['factors'][$factor]['included_in_overall'] ?? false)) continue;
            if ($score['score_status'] === 'UNSCORABLE') continue;

            $rows[] = [
                'factor' => $factor,
                'band' => $score['band'],
                'risk_index' => $score['risk_index'],
                'score' => $weights[$score['band']]
                    + $score['risk_index']
                    + (isset($alerts[$factor]) ? $bonus : 0)
                    + ($rules['factors'][$factor]['tie_break'] / 100),
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        $top = array_slice($rows, 0, $limit);
        foreach ($top as $i => &$row) $row['rank'] = $i + 1;

        return $top;
    }
}
```

- [ ] **Step 8: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=PriorityRankerTest`
Expected: PASS 6건

- [ ] **Step 9: 커밋**

```bash
git add app/Services/Scoring/OyMsi/CaseClassifier.php app/Services/Scoring/OyMsi/PriorityRanker.php \
        tests/Feature/OyMsi/CaseClassifierTest.php tests/Feature/OyMsi/PriorityRankerTest.php
git commit -m "feat(oy-msi): 사례코드 분류(일반+C격상)·상위 3영역 우선순위"
```

---

## Task 8: StrengthExtractor + SolutionRecommender

**Files:**
- Create: `app/Services/Scoring/OyMsi/StrengthExtractor.php`
- Create: `app/Services/Scoring/OyMsi/SolutionRecommender.php`
- Test: `tests/Feature/OyMsi/StrengthExtractorTest.php`, `tests/Feature/OyMsi/SolutionRecommenderTest.php`

**Interfaces:**
- Consumes: Task 3의 `rules['strengths']`, `rules['solutions']`, `rules['recheck']`; Task 7의 `PriorityRanker::rank()` 반환값
- Produces:
  - `StrengthExtractor::extract(array $rawByItemCode, array $rules): list<string>` — 강점 코드 목록. **항상 1개 이상**(005 §9.3 "보호요인 또는 강점을 최소 1개 포함").
  - `SolutionRecommender::recommend(array $topFactors, string $safetyLevel, string $environmentLevel, array $rules): list<string>` — 솔루션 코드 3개 이하
  - `SolutionRecommender::recheckDays(string $finalCaseCode, array $rules): array` — `['days'=>int, 'reason'=>string]`

- [ ] **Step 1: StrengthExtractor 테스트 작성**

`tests/Feature/OyMsi/StrengthExtractorTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\StrengthExtractor;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->extractor = new StrengthExtractor();
});

test('FUT04 원점수 2 이상이면 TRY_NEW 를 준다', function () {
    // 주의: 강점은 역채점 전 원점수(raw) 기준이다
    expect($this->extractor->extract(['FUT04' => 2], $this->rules))->toContain('TRY_NEW');
});

test('FUT05·FUT06 도 각각 SMALL_GOAL·RECOVERY_HOPE 를 준다', function () {
    $s = $this->extractor->extract(['FUT05' => 3, 'FUT06' => 2], $this->rules);
    expect($s)->toContain('SMALL_GOAL');
    expect($s)->toContain('RECOVERY_HOPE');
});

test('조건을 못 채워도 HONEST_RESPONSE 로 최소 1개는 나온다', function () {
    $s = $this->extractor->extract(['FUT04' => 0, 'FUT05' => 0, 'FUT06' => 0], $this->rules);
    expect($s)->toHaveCount(1);
    expect($s)->toBe(['HONEST_RESPONSE']);
});

test('조건을 채우면 HONEST_RESPONSE 는 붙지 않는다', function () {
    $s = $this->extractor->extract(['FUT04' => 3], $this->rules);
    expect($s)->toBe(['TRY_NEW']);
});
```

- [ ] **Step 2: StrengthExtractor 구현**

`app/Services/Scoring/OyMsi/StrengthExtractor.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class StrengthExtractor
{
    public function __construct(private ConditionMatcher $matcher = new ConditionMatcher()) {}

    /**
     * 강점은 역채점 전 원점수(raw) 기준이다 — FUT04~06 은 "높을수록 긍정" 문항.
     * ⚠️ ItemScorer 출력을 넘기면 의미가 뒤집혀 "희망 없음"이 강점으로 보고된다.
     * @return list<string>
     */
    public function extract(array $rawByItemCode, array $rules): array
    {
        $found = [];
        $fallbackCode = null;
        foreach ($rules['strengths'] as $code => $rule) {
            if ($rule['always'] ?? false) { $fallbackCode = $code; continue; }
            if ($this->matcher->matches($rule, $rawByItemCode)) $found[] = $code;
        }

        if ($found) return $found;

        // 005 §9.3 — 강점 최소 1개 보장. 폴백 코드도 규칙에서 가져온다.
        if ($fallbackCode === null) {
            throw new \InvalidArgumentException(
                'scoring_rules.strengths 에 always=true 인 기본 강점이 없습니다 — 강점 최소 1개 보장을 지킬 수 없습니다.'
            );
        }
        return [$fallbackCode];
    }
}
```

- [ ] **Step 3: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=StrengthExtractorTest`
Expected: PASS 4건

- [ ] **Step 4: SolutionRecommender 테스트 작성**

`tests/Feature/OyMsi/SolutionRecommenderTest.php`:

```php
<?php
use App\Models\Test;
use App\Services\Scoring\OyMsi\SolutionRecommender;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->rules = Test::where('code', 'OY_MSI')->firstOrFail()->scoringRule->rules;
    $this->rec = new SolutionRecommender();
});

function top(array $factors): array
{
    $out = [];
    foreach ($factors as $i => $f) {
        $out[] = ['factor' => $f, 'band' => 'RED', 'risk_index' => 70.0, 'score' => 270.0, 'rank' => $i + 1];
    }
    return $out;
}

test('상위 요인에 대응하는 솔루션을 순서대로 준다', function () {
    expect($this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S0', 'E0', $this->rules))
        ->toBe(['SOL_DEP_ACTIVATION', 'SOL_ANX_BREATHING', 'SOL_ISO_CONNECT']);
});

test('S2 이상이면 안전 솔루션이 첫 번째로 고정된다', function () {
    $sols = $this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S2', 'E0', $this->rules);
    expect($sols[0])->toBe('SOL_SAF_PLAN');
    expect($sols)->toHaveCount(3);
});

test('E2 이상이어도 안전 솔루션이 앞에 붙는다', function () {
    expect($this->rec->recommend(top(['DEP']), 'S0', 'E3', $this->rules)[0])->toBe('SOL_SAF_PLAN');
});

test('dedupe_group 이 같으면 하나만 남긴다', function () {
    // DEP(생활회복) 와 LIF(생활회복) 는 같은 그룹
    $sols = $this->rec->recommend(top(['DEP', 'LIF', 'ANX']), 'S0', 'E0', $this->rules);
    expect($sols)->toBe(['SOL_DEP_ACTIVATION', 'SOL_ANX_BREATHING']);
});

test('최대 3개를 넘지 않는다', function () {
    expect($this->rec->recommend(top(['DEP', 'ANX', 'ISO']), 'S3', 'E3', $this->rules))
        ->toHaveCount(3);
});

test('재검 시점은 사례코드로 정한다', function () {
    expect($this->rec->recheckDays('C3', $this->rules)['days'])->toBe(14);
    expect($this->rec->recheckDays('R1', $this->rules)['days'])->toBe(14);
    expect($this->rec->recheckDays('Y1', $this->rules)['days'])->toBe(28);
    expect($this->rec->recheckDays('G0', $this->rules)['days'])->toBe(90);
});
```

- [ ] **Step 5: SolutionRecommender 구현**

`app/Services/Scoring/OyMsi/SolutionRecommender.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

class SolutionRecommender
{
    private const LIMIT = 3;
    private const SAFETY_SOLUTION = 'SOL_SAF_PLAN';

    /**
     * 007 §10.2 — 안전 우선 고정 → 상위 요인 순 → dedupe_group 중복 제거 → 3개 이하
     * @param  list<array{factor:string,...}>  $topFactors
     * @return list<string>
     */
    public function recommend(
        array $topFactors,
        string $safetyLevel,
        string $environmentLevel,
        array $rules
    ): array {
        $catalog = $rules['solutions'];
        $byFactor = [];
        foreach ($catalog as $code => $sol) $byFactor[$sol['factor']] = $code;

        $candidates = [];
        if ($this->needsSafetyFirst($safetyLevel, $environmentLevel)) {
            $candidates[] = self::SAFETY_SOLUTION;
        }
        foreach ($topFactors as $row) {
            if (isset($byFactor[$row['factor']])) $candidates[] = $byFactor[$row['factor']];
        }

        $picked = [];
        $usedGroups = [];
        foreach ($candidates as $code) {
            if (count($picked) >= self::LIMIT) break;
            if (in_array($code, $picked, true)) continue;
            $group = $catalog[$code]['dedupe_group'];
            if (isset($usedGroups[$group])) continue;
            $usedGroups[$group] = true;
            $picked[] = $code;
        }

        return $picked;
    }

    /** @return array{days:int, reason:string} */
    public function recheckDays(string $finalCaseCode, array $rules): array
    {
        foreach ($rules['recheck'] as $entry) {
            if (in_array($finalCaseCode, $entry['when_case_in'], true)) {
                return ['days' => $entry['days'], 'reason' => $entry['reason']];
            }
        }
        return ['days' => 90, 'reason' => 'DEFAULT'];
    }

    private function needsSafetyFirst(string $safetyLevel, string $environmentLevel): bool
    {
        return max((int) substr($safetyLevel, 1), (int) substr($environmentLevel, 1)) >= 2;
    }
}
```

- [ ] **Step 6: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=SolutionRecommenderTest`
Expected: PASS 6건.
`dedupe_group` 테스트가 실패하면 SAF·TRM 이 같은 '안전' 그룹, DEP·LIF 가 같은 '생활회복' 그룹이라는 시더 값을 확인한다.

- [ ] **Step 7: 커밋**

```bash
git add app/Services/Scoring/OyMsi/StrengthExtractor.php \
        app/Services/Scoring/OyMsi/SolutionRecommender.php \
        tests/Feature/OyMsi/StrengthExtractorTest.php \
        tests/Feature/OyMsi/SolutionRecommenderTest.php
git commit -m "feat(oy-msi): 강점 추출(최소 1개 보장)·솔루션 추천(안전우선·dedupe)·재검 시점"
```

---

## Task 9: OyMsiScoringEngine 조립

**Files:**
- Create: `app/Services/Scoring/OyMsi/OyMsiScoringEngine.php`
- Modify: `app/Services/ScoringService.php` (ENGINES 에 `oy_msi` 등록)
- Test: `tests/Feature/OyMsi/OyMsiScoringEngineTest.php`

**Interfaces:**
- Consumes: Task 5~8의 8개 클래스 전부
- Produces: `TestResult` 레코드. `engine_result` json 구조는 007 §11.4를 따른다:

```
{
  "versions": {"assessment": "1.0.1", "scoring": "1.0.0"},
  "score_status": "COMPLETE",
  "overall": {"raw": 78.0, "max": 162, "risk_index": 48.1, "band": "YELLOW"},
  "profile": {"general_case_code": "R1", "final_case_code": "C1",
              "red_count": 1, "yellow_count": 4},
  "safety": {"suicide_level": "S1", "environment_level": "E0"},
  "factors": {"DEP": {"raw":12.0,"max":18,"risk_index":66.7,"band":"RED",
                      "answered_count":6,"score_status":"COMPLETE","rank":1}, ...},
  "priority": [{"factor":"DEP","band":"RED","risk_index":66.7,"score":..., "rank":1}, ...],
  "strengths": ["TRY_NEW"],
  "solutions": ["SOL_DEP_ACTIVATION", ...],
  "recheck": {"days": 14, "reason": "RED_FACTOR_OR_SAFETY"}
}
```

기존 컬럼도 함께 채운다 — `area_scores`(요인별 raw), `area_signals`(요인별 소문자 밴드 `red|yellow|green`), `overall_signal`, `overall_level`.

- [ ] **Step 1: 엔진 통합 테스트 작성**

`tests/Feature/OyMsi/OyMsiScoringEngineTest.php`:

```php
<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ScoringService;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
});

/**
 * item_code => raw 매핑으로 응시·응답을 만들고 채점한다.
 * 지정하지 않은 문항은 $default 로 채운다. null 을 주면 응답거부로 저장한다.
 */
function scoreWith(array $overrides, ?int $default = 0): App\Models\TestResult
{
    $test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'guest_token' => 'g', 'status' => 'in_progress',
        'started_at' => now(), 'assessment_version' => $test->assessment_version,
        'scoring_version' => $test->scoringRule->version,
    ]);

    foreach ($test->items as $item) {
        $raw = array_key_exists($item->item_code, $overrides)
            ? $overrides[$item->item_code]
            : $default;
        $attempt->answers()->create([
            'test_item_id' => $item->id,
            'value' => $raw,
            'missing_code' => $raw === null ? 'PREFER_NOT' : null,
        ]);
    }

    return app(ScoringService::class)->score($attempt);
}

test('전부 0 이면 G0 · S0 · E0 · GREEN 이다 (T01)', function () {
    $r = scoreWith([]);
    expect($r->general_case_code)->toBe('G0');
    expect($r->final_case_code)->toBe('G0');
    expect($r->safety_level)->toBe('S0');
    expect($r->environment_level)->toBe('E0');
    expect($r->score_status)->toBe('COMPLETE');
    expect($r->engine_result['overall']['band'])->toBe('GREEN');
});

test('역채점 문항이 0 이면 요인 점수를 올린다', function () {
    // FUT04~06 raw 0 → scored 3 씩 → FUT raw 9 → YELLOW
    $r = scoreWith(['FUT04' => 0, 'FUT05' => 0, 'FUT06' => 0]);
    expect($r->engine_result['factors']['FUT']['raw'])->toBe(9.0);
    expect($r->engine_result['factors']['FUT']['band'])->toBe('YELLOW');
});

test('DEP 6문항 전부 3 이면 RED · R1 이다 (T13)', function () {
    $r = scoreWith(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    expect($r->engine_result['factors']['DEP']['band'])->toBe('RED');
    expect($r->general_case_code)->toBe('R1');
    expect($r->final_case_code)->toBe('R1');
});

test('SAF04=2 면 S3 이고 최종 C3 로 격상된다 (T09)', function () {
    $r = scoreWith(['SAF04' => 2]);
    expect($r->safety_level)->toBe('S3');
    expect($r->general_case_code)->toBe('G0');
    expect($r->final_case_code)->toBe('C3'); // 일반코드가 G0 여도 안전이 우선
});

test('FAM05=3 이면 E3 → C3 이다 (T12)', function () {
    $r = scoreWith(['FAM05' => 3]);
    expect($r->environment_level)->toBe('E3');
    expect($r->final_case_code)->toBe('C3');
});

test('일반코드와 최종코드를 둘 다 저장한다 (기관 통계 왜곡 방지)', function () {
    // TRM06=1 → E1 → C1. 하지만 일반 프로파일은 G0 라는 정보가 남아야 한다.
    $r = scoreWith(['TRM06' => 1]);
    expect($r->environment_level)->toBe('E1');
    expect($r->final_case_code)->toBe('C1');
    expect($r->general_case_code)->toBe('G0');
});

test('SAF 는 전체 위험지수에 포함되지 않는다', function () {
    $allSafMax = ['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3, 'SAF04' => 3, 'SAF05' => 3, 'SAF06' => 3];
    expect(scoreWith($allSafMax)->engine_result['overall']['raw'])->toBe(0.0);
});

test('응답거부가 있으면 PARTIAL 이 되고 SAF 무응답은 최소 S1 이다 (T11)', function () {
    $r = scoreWith(['SAF02' => null]);
    expect($r->safety_level)->toBe('S1');
    expect($r->final_case_code)->toBe('C1');
});

test('기존 컬럼도 함께 채운다 (결과 화면 호환)', function () {
    $r = scoreWith(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    expect($r->area_scores['DEP'])->toBe(18.0);
    expect($r->area_signals['DEP'])->toBe('red');
    expect($r->overall_signal)->toBeIn(['green', 'yellow', 'red']);
    expect($r->overall_level)->not->toBeEmpty();
});

test('버전을 결과에 기록한다', function () {
    $r = scoreWith([]);
    expect($r->engine_result['versions']['assessment'])->toBe('1.0.1');
    expect($r->engine_result['versions']['scoring'])->toBe('1.0.0');
});

test('강점과 솔루션과 재검일이 들어 있다', function () {
    $r = scoreWith(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    expect($r->engine_result['strengths'])->not->toBeEmpty();
    expect($r->engine_result['solutions'])->toContain('SOL_DEP_ACTIVATION');
    expect($r->engine_result['recheck']['days'])->toBe(14);
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=OyMsiScoringEngineTest`
Expected: FAIL — `oy_msi` 엔진 미등록

- [ ] **Step 3: 엔진 구현**

`app/Services/Scoring/OyMsi/OyMsiScoringEngine.php`:

```php
<?php
namespace App\Services\Scoring\OyMsi;

use App\Models\TestAttempt;
use App\Models\TestResult;
use App\Services\Scoring\ScoringEngine;

class OyMsiScoringEngine implements ScoringEngine
{
    public function __construct(
        private ItemScorer $itemScorer = new ItemScorer(),
        private FactorScorer $factorScorer = new FactorScorer(),
        private SafetyEvaluator $safety = new SafetyEvaluator(),
        private EnvironmentEvaluator $environment = new EnvironmentEvaluator(),
        private CaseClassifier $classifier = new CaseClassifier(),
        private PriorityRanker $ranker = new PriorityRanker(),
        private StrengthExtractor $strengths = new StrengthExtractor(),
        private SolutionRecommender $solutions = new SolutionRecommender(),
    ) {}

    public function score(TestAttempt $attempt): TestResult
    {
        $attempt->loadMissing('test.items', 'test.scoringRule', 'answers');
        $test = $attempt->test;
        $rules = $test->scoringRule->rules;

        // 1. item_code 기준 원점수 맵
        $itemsById = $test->items->keyBy('id');
        $raw = [];
        foreach ($test->items as $item) $raw[$item->item_code] = null; // 미응답 기본값
        foreach ($attempt->answers as $ans) {
            $item = $itemsById[$ans->test_item_id] ?? null;
            if (!$item) continue;
            $raw[$item->item_code] = $ans->value === null ? null : (int) $ans->value;
        }

        // 2. 문항 점수 (역채점)
        $reverseCodes = $test->items->where('reverse', true)->pluck('item_code')->all();
        $scored = $this->itemScorer->score($raw, $reverseCodes);

        // 3. 요인 점수
        $codesByFactor = $test->items->groupBy('area')
            ->map(fn ($group) => $group->pluck('item_code')->values()->all())->all();
        $factors = $this->factorScorer->scoreAll($scored, $codesByFactor, $rules);
        $overall = $this->factorScorer->overall($factors, $rules);

        // 4. 안전·환경
        $safetyLevel = $this->safety->evaluate($raw, $rules);
        $environmentLevel = $this->environment->evaluate($raw, $rules);

        // 5. 사례코드
        $general = $this->classifier->general($factors, $rules);
        $finalCode = $this->classifier->final($general['code'], $safetyLevel, $environmentLevel, $rules);

        // 6. 우선순위·강점·솔루션·재검
        $alertFactors = $this->alertFactors($safetyLevel, $raw, $rules);
        $priority = $this->ranker->rank($factors, $rules, $alertFactors);
        $strengthCodes = $this->strengths->extract($raw, $rules);
        $solutionCodes = $this->solutions->recommend($priority, $safetyLevel, $environmentLevel, $rules);
        $recheck = $this->solutions->recheckDays($finalCode, $rules);

        // 7. 요인에 rank 병합
        foreach ($priority as $row) $factors[$row['factor']]['rank'] = $row['rank'];
        foreach ($factors as $code => &$f) {
            $f['max'] = 18;
            $f['rank'] = $f['rank'] ?? null;
        }
        unset($f);

        $scoreStatus = $this->overallStatus($factors, $rules);

        return TestResult::updateOrCreate(
            ['attempt_id' => $attempt->id],
            [
                // 기존 컬럼 (결과 화면 호환)
                'area_scores' => array_map(fn ($f) => $f['raw'], $factors),
                'area_signals' => array_map(
                    fn ($f) => $f['band'] === null ? null : strtolower($f['band']),
                    $factors
                ),
                'overall_signal' => strtolower($overall['band']),
                'overall_level' => $this->overallLevelText($overall['band']),
                'interpretation' => '',   // 문안은 ReportComposer 가 템플릿에서 조립한다
                'recommendations' => $solutionCodes,

                // 신규 컬럼
                'general_case_code' => $general['code'],
                'final_case_code' => $finalCode,
                'safety_level' => $safetyLevel,
                'environment_level' => $environmentLevel,
                'score_status' => $scoreStatus,
                'engine_result' => [
                    'versions' => [
                        'assessment' => $attempt->assessment_version ?: $test->assessment_version,
                        'scoring' => $attempt->scoring_version ?: $test->scoringRule->version,
                    ],
                    'score_status' => $scoreStatus,
                    'overall' => $overall,
                    'profile' => [
                        'general_case_code' => $general['code'],
                        'final_case_code' => $finalCode,
                        'red_count' => $general['red_count'],
                        'yellow_count' => $general['yellow_count'],
                    ],
                    'safety' => [
                        'suicide_level' => $safetyLevel,
                        'environment_level' => $environmentLevel,
                    ],
                    'factors' => $factors,
                    'priority' => $priority,
                    'strengths' => $strengthCodes,
                    'solutions' => $solutionCodes,
                    'recheck' => $recheck,
                ],
            ]
        );
    }

    /**
     * 경보가 걸린 요인 — 우선순위 alert_bonus(007 §9.5) 대상.
     *
     * (2026-07-28 리뷰 라운드 1 수정) 환경 쪽은 EnvironmentEvaluator::alertedFactors()
     * 에 위임한다 — "환경등급이 E2/E3 다" 라고 TRM/FAM/RSK 세 요인 모두에 붙이는 것은
     * 스펙에 없다(007 §7.3 은 문항→요인 1:1 매핑이다). 실제로 임계값을 넘은 문항이
     * 속한 요인에만 준다. 예전 버전(아래에서 삭제된 한 줄)은 environment_level 문자열
     * 만 보고 TRM/FAM/RSK 를 일괄로 넣었는데, 이러면 RSK05 하나만 걸려도 트라우마·
     * 가족까지 부당하게 상위 3에 끼어 우울/불안 RED 인 청소년의 진짜 고위험 요인이
     * 결과지에서 밀려난다(무작위 3000건 대조에서 2860건이 이 팬아웃으로 상위3이
     * {FAM,RSK,TRM} 으로 고정됨 — Task 10 리뷰에서 발견).
     *
     * 'SAF' 분기는 현재 사문(死文)이다 — PriorityRanker::rank() 가
     * included_in_overall=false 인 SAF 를 순위 계산에서 먼저 걸러내므로 SAF 가
     * alertFactors 에 들어 있어도 실제 우선순위에 영향을 주지 않는다. 이 라운드는
     * 환경 쪽 팬아웃 버그만 고치는 것이 범위이므로 이 분기는 그대로 둔다(YAGNI).
     */
    private function alertFactors(string $safetyLevel, array $raw, array $rules): array
    {
        $out = [];
        if ((int) substr($safetyLevel, 1) >= 2) $out[] = 'SAF';
        return array_merge($out, $this->environment->alertedFactors($raw, $rules));
    }

    private function overallStatus(array $factors, array $rules): string
    {
        $statuses = [];
        foreach ($factors as $code => $f) {
            if (!($rules['factors'][$code]['included_in_overall'] ?? false)) continue;
            $statuses[] = $f['score_status'];
        }
        if (in_array('UNSCORABLE', $statuses, true)) return 'INCOMPLETE';
        if (in_array('PARTIAL', $statuses, true)) return 'PARTIAL';
        return 'COMPLETE';
    }

    private function overallLevelText(string $band): string
    {
        return match ($band) {
            'RED' => '적극적 지원이 필요한 단계',
            'YELLOW' => '관심과 조기지원이 필요한 단계',
            default => '양호한 단계',
        };
    }
}
```

- [ ] **Step 4: ScoringService 에 엔진 등록**

`app/Services/ScoringService.php` — `use` 추가 및 `ENGINES` 수정:

```php
use App\Services\Scoring\OyMsi\OyMsiScoringEngine;
```

```php
    public const ENGINES = [
        'signal' => SignalScoringEngine::class,
        'oy_msi' => OyMsiScoringEngine::class,
    ];
```

- [ ] **Step 5: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=OyMsiScoringEngineTest`
Expected: PASS 11건

- [ ] **Step 6: 전체 회귀 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 기존 pass 유지 + OyMsi 테스트 전부 통과

- [ ] **Step 7: 커밋**

```bash
git add app/Services/Scoring/OyMsi/OyMsiScoringEngine.php app/Services/ScoringService.php \
        tests/Feature/OyMsi/OyMsiScoringEngineTest.php
git commit -m "feat(oy-msi): 채점 엔진 조립 — 요인·안전·환경·사례코드·우선순위·강점·솔루션 통합"
```

---

## Task 10: JS 참조 구현과 0 diff 대조

**Files:**
- Create: `tools/oy-msi-reference/extract.md` (추출 절차 기록)
- Create: `tools/oy-msi-reference/reference.js` (원본 HTML에서 떼어낸 채점 함수)
- Create: `tools/oy-msi-reference/generate-cases.js` (무작위 케이스 + 기대값 생성)
- Create: `tests/Feature/OyMsi/ReferenceParityTest.php`
- Create: `tests/fixtures/oy-msi-reference-cases.json` (생성물, 커밋함)

**Interfaces:**
- Consumes: Task 9의 `ScoringService::score()`
- Produces: 고정 fixture `tests/fixtures/oy-msi-reference-cases.json` — `[{"answers": {"DEP01": 2, ...}, "expected": {"factors": {...}, "overall_index": 48.1, "general_case_code": "R1", "safety_level": "S1", "environment_level": "E0", "priority": ["DEP","LIF","FUT"]}}, ...]`

**대조 범위**: 요인 raw·위험지수·밴드, 전체 위험지수, **일반** 사례코드, 환경등급, 상위 3영역.
**안전등급(S)은 대조에서 제외한다** — 003 기준을 채택해 JS(007 기준)와 의도적으로 다르다. 대신 별도 테스트에서 "JS 기준으로는 S2인데 우리는 S3"인 케이스가 실제로 그렇게 갈리는지 확인한다.

> **(2026-07-28 리뷰 라운드 1 수정 — 이 Task 의 최초 계획에 결함 2건이 있었다.)**
> 1. **상위 3영역 대조가 애초에 성립하지 않는다.** 아래 Step 3/5 원안은 `priorityFactors(scored).slice(0,3)` 로 JS 도 top-3, PHP 도 top-3 를 그대로 비교하지만, PHP `PriorityRanker` 의 alert_bonus(007 §9.5) 계산에는 원본 JS `priorityFactors()` 에 없는 항이 있어 경보가 걸리면 순서가 달라지는 게 정상이다. 최초 구현(Task 10 1차 시도)은 이를 "환경등급 E2/E3 면 TRM/FAM/RSK 세 요인 모두에 +1000" 으로 구현했는데, 이는 007 §7.3(환경 문항→요인 1:1 매핑)·§9.5("해당 요인에" 붙는다) 에 없는 팬아웃이었다 — 무작위 3000건의 97.5%(2926건)에서 상위 3영역이 {FAM,RSK,TRM} 으로 고정되는 임상적 결함으로 이어졌다. **Task 7/9 의 `OyMsiScoringEngine::alertFactors()`/`EnvironmentEvaluator` 를 수정**해 실제로 임계값을 넘은 문항의 요인에만 bonus 를 준다(§7.3/§9.5 관련 코드 블록에 수정 반영됨).
> 2. **대조 테스트 자체도 top-3 로 미리 자른 값끼리 비교해서는 안 된다.** alert_bonus 로 순서가 바뀌는 케이스에서 "그래도 이 정도는 같아야 한다"를 검증하려면 JS 의 9요인 **전체** 순서가 필요하다(top-3 로 자르면 복원 불가). 그래서 `reference.js` 에 원본에 없는 테스트 하네스 전용 함수 `priorityFactorsFull()`(`priorityFactors()` 와 정렬공식 동일, `slice(0,3)` 만 없음)을 추가하고, `generate-cases.js` 는 `priority`(top-3) 대신 `priority_full`(9개 전체)을 저장한다. `ReferenceParityTest.php` 는 "JS 전체순서를 경보요인 우선으로 재배열한 것"을 기대값으로 삼아 3000건 전부에서 순서까지 대조한다(집합 비교로 완화하지 않음). 아래 Step 2/3/5 코드 블록은 이 수정을 반영해 갱신했다 — 최초 원안 그대로 구현하면 위 팬아웃 버그를 놓친다.

- [ ] **Step 1: 추출 절차를 문서로 남긴다**

`tools/oy-msi-reference/extract.md`:

```markdown
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
```

- [ ] **Step 2: 참조 JS 추출**

원본 HTML에서 위 심볼을 `tools/oy-msi-reference/reference.js` 로 옮긴다. 파일 끝:

```js
module.exports = {
  ITEMS, FACTOR_META,
  scoreAnswers, getSafetyLevel, getEnvironmentLevel, getFinalCaseCode, priorityFactors,
};
```

**(라운드 1 수정)** 실제로는 `getFinalCaseCode` 가 `generalCode` 를 인자로만 받고 계산하지 않아 `getGeneralCode`(원본 2190~2199행)도 함께 옮겨야 했고, `priorityFactors()` 의 top-3 절단을 우회할 `priorityFactorsFull()`(원본에 없는 하네스 전용 함수 — 정렬공식은 100% 동일, `slice(0,3)` 만 제거)도 추가했다:

```js
module.exports = {
  ITEMS, FACTOR_META,
  scoreAnswers, getSafetyLevel, getEnvironmentLevel, getGeneralCode, getFinalCaseCode, priorityFactors,
  priorityFactorsFull,
};
```

- [ ] **Step 3: 케이스 생성기 작성**

`tools/oy-msi-reference/generate-cases.js`:

**(라운드 1 수정 — 최종 버전)** `priority` 를 top-3 로 잘라 저장하면 alert_bonus 재배열 대조가
불가능해, `priority_full`(9요인 전체 JS 순서)을 대신 저장한다. `scoreAnswers()` 의 실제 반환
키는 `factorScores`/`overallIndex` 뿐이고 `generalCode` 는 없어(별도 함수) `ref.getGeneralCode()`
를 따로 호출한다.

```js
const fs = require('fs');
const path = require('path');
const ref = require('./reference.js');

// 재현 가능한 난수 (mulberry32)
function rng(seed) {
  return function () {
    seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const rand = rng(20260727);
const CASE_COUNT = 3000;
const cases = [];

for (let n = 0; n < CASE_COUNT; n++) {
  const answers = {};
  for (const item of ref.ITEMS) {
    answers[item.itemId] = Math.floor(rand() * 4); // 0~3
  }

  const scored = ref.scoreAnswers(answers);
  const generalCode = ref.getGeneralCode(scored.factorScores);

  cases.push({
    answers,
    expected: {
      factors: scored.factorScores,       // {code: {raw, count, riskIndex, band}}
      overall_index: scored.overallIndex,
      general_case_code: generalCode,
      environment_level: ref.getEnvironmentLevel(answers),
      // 9개 요인 전체의 JS 상대순서(top-3 로 자르지 않음) — alert_bonus 재배열 대조용
      priority_full: ref.priorityFactorsFull(scored.factorScores).map((f) => f.code),
      // 참고용 — 대조하지 않음 (우리는 003 기준)
      js_safety_level: ref.getSafetyLevel(answers),
    },
  });
}

const outPath = path.join(__dirname, '..', '..', 'tests', 'fixtures', 'oy-msi-reference-cases.json');
fs.writeFileSync(outPath, JSON.stringify(cases, null, 0));
console.log(`generated ${cases.length} cases -> ${outPath}`);
```

- [ ] **Step 4: 케이스 생성 실행**

Run: `cd tools/oy-msi-reference && node generate-cases.js`
Expected: `generated 3000 cases`, `tests/fixtures/oy-msi-reference-cases.json` 생성

- [ ] **Step 5: 대조 테스트 작성**

`tests/Feature/OyMsi/ReferenceParityTest.php`:

**(라운드 1 수정 — 최종 버전)** 상위 3영역은 더 이상 top-3 끼리 직접 비교하지 않는다.
`alertedFactorsFromAnswers()` 가 007 §7.3(TRM06/FAM05/RSK04/RSK05/RSK06 ≥ 2, E1=WARN 은 제외)
을 원응답에서 **독립적으로** 재도출하고, `expectedTop3()` 가 "JS 9요인 전체 순서를 경보 요인
우선으로 재배열"한 값을 기대값으로 만든다 — PHP 랭킹 공식(weight+riskIndex+tieBreak+bonus)을
테스트 안에서 재계산하지 않는다(그러면 엔진을 엔진으로 검증하는 셈이 된다). alert_bonus 가
모든 경보 요인에 동일하게 +1000 이고 tie_break 가 JS 와 같기 때문에 이 재배열은 실제
PHP 결과와 **순서까지** 완전히 일치해야 한다(집합 비교로 완화하지 않음).

```php
<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ScoringService;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items', 'scoringRule')->firstOrFail();
    $this->itemsByCode = $this->test->items->keyBy('item_code');
});

/** 007 §7.3 매핑을 fixture 원응답에서 독립적으로 재도출(PHP 엔진 코드를 베끼지 않음). */
function alertedFactorsFromAnswers(array $answers): array
{
    $alerted = [];
    if (($answers['TRM06'] ?? 0) >= 2) $alerted['TRM'] = true;
    if (($answers['FAM05'] ?? 0) >= 2) $alerted['FAM'] = true;
    if (($answers['RSK06'] ?? 0) >= 2 || ($answers['RSK04'] ?? 0) >= 2 || ($answers['RSK05'] ?? 0) >= 2) {
        $alerted['RSK'] = true;
    }
    return array_keys($alerted);
}

/** JS 전체순서를 "경보 요인 우선, 각 그룹 내부는 JS 상대순서 유지"로 재배열해 상위 3을 뽑는다. */
function expectedTop3(array $jsFullOrder, array $alertedFactors): array
{
    $alertedSet = array_flip($alertedFactors);
    $alerted = array_values(array_filter($jsFullOrder, fn ($f) => isset($alertedSet[$f])));
    $rest = array_values(array_filter($jsFullOrder, fn ($f) => !isset($alertedSet[$f])));
    return array_slice(array_merge($alerted, $rest), 0, 3);
}

test('JS 참조 구현과 0 diff (요인·전체지수·일반코드·환경등급·상위3)', function () {
    $path = base_path('tests/fixtures/oy-msi-reference-cases.json');
    expect(file_exists($path))->toBeTrue('먼저 tools/oy-msi-reference/generate-cases.js 를 실행하라');

    $cases = json_decode(file_get_contents($path), true);
    expect(count($cases))->toBeGreaterThanOrEqual(1000);

    $mismatches = [];

    foreach ($cases as $index => $case) {
        $attempt = TestAttempt::create([
            'test_id' => $this->test->id, 'guest_token' => 'parity', 'status' => 'in_progress',
            'started_at' => now(),
            'assessment_version' => $this->test->assessment_version,
            'scoring_version' => $this->test->scoringRule->version,
        ]);
        $rows = [];
        foreach ($case['answers'] as $code => $value) {
            $rows[] = [
                'attempt_id' => $attempt->id,
                'test_item_id' => $this->itemsByCode[$code]->id,
                'value' => $value, 'missing_code' => null,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        App\Models\AttemptAnswer::insert($rows);

        $result = app(ScoringService::class)->score($attempt);
        $engine = $result->engine_result;
        $exp = $case['expected'];

        foreach ($exp['factors'] as $factor => $expected) {
            $actual = $engine['factors'][$factor];
            if (abs($actual['raw'] - $expected['raw']) > 0.001
                || abs($actual['risk_index'] - $expected['riskIndex']) > 0.05
                || $actual['band'] !== $expected['band']) {
                $mismatches[] = "case {$index} factor {$factor}: "
                    . json_encode($actual) . ' vs ' . json_encode($expected);
            }
        }

        if (abs($engine['overall']['risk_index'] - $exp['overall_index']) > 0.05) {
            $mismatches[] = "case {$index} overall_index: "
                . $engine['overall']['risk_index'] . ' vs ' . $exp['overall_index'];
        }
        if ($result->general_case_code !== $exp['general_case_code']) {
            $mismatches[] = "case {$index} general_case_code: "
                . $result->general_case_code . ' vs ' . $exp['general_case_code'];
        }
        if ($result->environment_level !== $exp['environment_level']) {
            $mismatches[] = "case {$index} environment_level: "
                . $result->environment_level . ' vs ' . $exp['environment_level'];
        }

        $alertedFactors = alertedFactorsFromAnswers($case['answers']);
        $expected = expectedTop3($exp['priority_full'], $alertedFactors);
        $actual = array_column($engine['priority'], 'factor');
        if ($actual !== $expected) {
            $mismatches[] = "case {$index} priority: "
                . implode(',', $actual) . ' vs ' . implode(',', $expected)
                . ' (alerted=' . implode(',', $alertedFactors) . ')';
        }

        if (count($mismatches) > 20) break; // 로그 폭주 방지
    }

    expect($mismatches)->toBe([], "불일치 " . count($mismatches) . "건:\n" . implode("\n", array_slice($mismatches, 0, 20)));
})->group('parity');

test('003 기준 채택 때문에 안전등급만 JS 와 갈린다', function () {
    $cases = json_decode(file_get_contents(base_path('tests/fixtures/oy-msi-reference-cases.json')), true);

    $promoted = 0;
    foreach ($cases as $case) {
        // JS(007) 기준 S2 인데 003 상향 조건에 걸리는 케이스
        $a = $case['answers'];
        $isPromoted = ($a['SAF04'] ?? 0) >= 1 || ($a['SAF01'] ?? 0) === 3
                   || ($a['SAF02'] ?? 0) === 3 || ($a['SAF05'] ?? 0) >= 2;
        if ($case['expected']['js_safety_level'] === 'S2' && $isPromoted) $promoted++;
    }

    // 무작위 3000건이면 이런 케이스가 반드시 다수 존재한다.
    // 0 이면 fixture 생성이 잘못됐거나 참조 구현이 003 기준으로 오염된 것이다.
    expect($promoted)->toBeGreaterThan(0);
})->group('parity');

test('환경경보로 인한 alert_bonus 상위3 재배열이 실제로 다수 발생한다', function () {
    // 경보 있는/없는 케이스가 각각 다수 있어야 하고, 재배열로 실제 순서가 바뀌는
    // 케이스도 있어야 한다 — 0-diff 테스트가 눈속임(항상 같은 분기만 타는 것)이
    // 아님을 확인한다.
    $cases = json_decode(file_get_contents(base_path('tests/fixtures/oy-msi-reference-cases.json')), true);

    $alertedCount = 0; $noAlertCount = 0; $reordered = 0;
    foreach ($cases as $case) {
        $alertedFactors = alertedFactorsFromAnswers($case['answers']);
        if ($alertedFactors === []) { $noAlertCount++; continue; }
        $alertedCount++;
        $jsTop3 = array_slice($case['expected']['priority_full'], 0, 3);
        $expected = expectedTop3($case['expected']['priority_full'], $alertedFactors);
        if ($expected !== $jsTop3) $reordered++;
    }

    expect($alertedCount)->toBeGreaterThan(0);
    expect($noAlertCount)->toBeGreaterThan(0);
    expect($reordered)->toBeGreaterThan(0);
})->group('parity');
```

- [ ] **Step 6: 대조 실행**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ReferenceParityTest`
Expected: PASS 3건(라운드 1 이후 — 재배열 sanity 테스트 1건 추가).
불일치가 나오면 메시지에 어느 케이스·어느 요인이 어긋났는지 최대 20건이 찍힌다. 우선 확인할 것 — ① 반올림 자리수(`round(x, 1)`) ② 역채점 대상이 FUT04~06 셋뿐인지 ③ alert_bonus 가 007 §7.3 매핑대로 "해당 요인에만" 붙는지(TRM/FAM/RSK 일괄 팬아웃 금지).

- [ ] **Step 7: 커밋**

```bash
git add tools/oy-msi-reference/ tests/fixtures/oy-msi-reference-cases.json \
        tests/Feature/OyMsi/ReferenceParityTest.php
git commit -m "test(oy-msi): JS 참조 구현과 3000건 0 diff 대조 (안전등급은 003 기준으로 의도적 분기)"
```

---

## Task 11: 응답값 검증 규칙 — 0점 거부 버그 수정

**Files:**
- Create: `app/Rules/AnswerValue.php`
- Modify: `app/Http/Controllers/AssessmentController.php:88-99` (submit 의 validate + 저장 루프)
- Modify: `app/Http/Controllers/LinkController.php:89-100` (동일)
- Test: `tests/Feature/OyMsi/AnswerValidationTest.php`

**Interfaces:**
- Consumes: Task 1의 `attempt_answers.missing_code`, Task 2의 `test_items.options`
- Produces: `App\Rules\AnswerValue` — 생성자 `__construct(array $itemsById)`. 5점 문항(`options` 없음 또는 `type=likert5`)은 1~5, `options` 배열이 있으면 `0 .. count-1`, 문자열 `'PREFER_NOT'` 허용. 컨트롤러 저장 루프는 `'PREFER_NOT'`을 `value=null, missing_code='PREFER_NOT'`로 저장한다.

**이 버그가 왜 치명적인가:** 현재 `'answers.*' => 'integer|min:1|max:5'` 때문에 4점 척도의 **"전혀 그렇지 않다"(0점) 응답이 서버에서 거부된다.** 60문항 중 하나라도 0이면 검사 제출 자체가 실패한다.

- [ ] **Step 1: 검증 테스트 작성**

`tests/Feature/OyMsi/AnswerValidationTest.php`:

```php
<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

function submitPayload(Test $test, array $overrides = [], $default = 0): array
{
    $answers = [];
    foreach ($test->items as $item) {
        $answers[$item->id] = array_key_exists($item->item_code, $overrides)
            ? $overrides[$item->item_code] : $default;
    }
    return ['answers' => $answers];
}

test('4점 척도에서 0점 응답이 통과한다 (기존 min:1 버그)', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), submitPayload($this->test, [], 0))
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe('submitted');
    expect($attempt->answers()->count())->toBe(60);
    expect($attempt->answers()->where('value', 0)->count())->toBe(60);
});

test('4점 척도에서 4 이상은 거부한다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), submitPayload($this->test, ['DEP01' => 4]))
        ->assertSessionHasErrors();
});

test('PREFER_NOT 은 value=null · missing_code 로 저장된다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]),
               submitPayload($this->test, ['SAF03' => 'PREFER_NOT']))
        ->assertRedirect();

    $item = $this->test->items->firstWhere('item_code', 'SAF03');
    $answer = $attempt->answers()->where('test_item_id', $item->id)->first();
    expect($answer->value)->toBeNull();
    expect($answer->missing_code)->toBe('PREFER_NOT');
});

test('기존 5점 척도 검사는 1~5 를 그대로 받는다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $answers = [];
    foreach ($sample->items as $item) $answers[$item->id] = 3;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['KMSIA-SAMPLE', $attempt->id]), ['answers' => $answers])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe('submitted');
});

test('기존 5점 척도 검사에서 0 은 여전히 거부된다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $answers = [];
    foreach ($sample->items as $item) $answers[$item->id] = 0;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['KMSIA-SAMPLE', $attempt->id]), ['answers' => $answers])
        ->assertSessionHasErrors();
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=AnswerValidationTest`
Expected: FAIL — 0점 응답이 `min:1` 에 걸려 422/redirect-with-errors

- [ ] **Step 3: AnswerValue 규칙 구현**

`app/Rules/AnswerValue.php`:

```php
<?php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;

class AnswerValue implements ValidationRule
{
    public const PREFER_NOT = 'PREFER_NOT';

    /** @param Collection<int, \App\Models\TestItem> $itemsById */
    public function __construct(private Collection $itemsById) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $itemId = (int) str_replace('answers.', '', $attribute);
        $item = $this->itemsById[$itemId] ?? null;
        if (!$item) { $fail('존재하지 않는 문항입니다.'); return; }

        if ($value === self::PREFER_NOT) return; // 응답거부 허용

        if (!is_numeric($value) || (int) $value != $value) {
            $fail('응답값이 올바르지 않습니다.'); return;
        }
        $value = (int) $value;

        [$min, $max] = $this->range($item);
        if ($value < $min || $value > $max) {
            $fail("응답값은 {$min}~{$max} 범위여야 합니다.");
        }
    }

    /** options 가 있으면 0..count-1, 없으면 레거시 5점(1..5) */
    private function range($item): array
    {
        $options = $item->options;
        if (is_array($options) && count($options) > 0) {
            return [0, count($options) - 1];
        }
        return [1, 5];
    }
}
```

- [ ] **Step 4: AssessmentController::submit 수정**

`app/Http/Controllers/AssessmentController.php` — `submit()` 본문의 validate + 저장 루프를 다음으로 교체:

```php
    public function submit(Request $request, string $code, TestAttempt $attempt, ScoringService $scoring)
    {
        $this->authorizeAttempt($request, $attempt);
        abort_if($attempt->status === 'submitted', 409);

        $itemsById = $attempt->test->items()->get()->keyBy('id');
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => [new \App\Rules\AnswerValue($itemsById)],
        ]);

        foreach ($request->input('answers') as $itemId => $value) {
            if (!isset($itemsById[(int) $itemId])) continue;
            $prefersNot = $value === \App\Rules\AnswerValue::PREFER_NOT;
            $attempt->answers()->updateOrCreate(
                ['test_item_id' => (int) $itemId],
                [
                    'value' => $prefersNot ? null : (int) $value,
                    'missing_code' => $prefersNot ? \App\Rules\AnswerValue::PREFER_NOT : null,
                ]
            );
        }

        $attempt->update(['status' => 'submitted', 'submitted_at' => now()]);
        $scoring->score($attempt);
        return redirect()->route('result.show', $attempt->id);
    }
```

- [ ] **Step 5: LinkController::submit 에 동일 적용**

`app/Http/Controllers/LinkController.php` — `submit()` 의 validate + 저장 루프를 위와 같은 방식으로 교체한다. `$vouchers->markUsedByAttempt($voucher, $attempt);` 호출은 유지한다:

```php
        $itemsById = $attempt->test->items()->get()->keyBy('id');
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => [new \App\Rules\AnswerValue($itemsById)],
        ]);

        foreach ($request->input('answers') as $itemId => $value) {
            if (!isset($itemsById[(int) $itemId])) continue;
            $prefersNot = $value === \App\Rules\AnswerValue::PREFER_NOT;
            $attempt->answers()->updateOrCreate(
                ['test_item_id' => (int) $itemId],
                [
                    'value' => $prefersNot ? null : (int) $value,
                    'missing_code' => $prefersNot ? \App\Rules\AnswerValue::PREFER_NOT : null,
                ]
            );
        }
```

- [ ] **Step 6: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=AnswerValidationTest`
Expected: PASS 5건

- [ ] **Step 7: 전체 회귀 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 기존 pass 유지. 특히 `AssessmentTakeTest`·`PaidAttemptTest`·`EndToEndFlowTest` 가 5점 척도로 계속 통과해야 한다.

- [ ] **Step 8: 커밋**

```bash
git add app/Rules/AnswerValue.php app/Http/Controllers/AssessmentController.php \
        app/Http/Controllers/LinkController.php tests/Feature/OyMsi/AnswerValidationTest.php
git commit -m "fix(assessment): 0점 응답이 거부되던 버그 — 문항 options 기반 검증 + PREFER_NOT 저장"
```

---

## Task 12: 동의 기록 + 우회 차단

**Files:**
- Create: `database/migrations/2026_07_28_000003_add_consent_required_to_tests.php`
- Create: `app/Services/OyMsi/ConsentGate.php`
- Modify: `app/Http/Controllers/AssessmentController.php` (`agree`, `start`, `take`, `submit`)
- Modify: `database/seeders/OyMsi/TestSeeder.php` (`consent_required => true`)
- Test: `tests/Feature/OyMsi/ConsentGateTest.php`

**Interfaces:**
- Consumes: Task 1의 `consent_records` 테이블, Task 2의 `OY_MSI` 검사
- Produces:
  - 컬럼 `tests.consent_required` boolean default `false` — **기존 검사는 전부 false 라 영향 0**
  - `ConsentGate::record(TestAttempt $attempt, string $type, string $actor, ?int $actorUserId = null, array $meta = []): ConsentRecord`
  - `ConsentGate::has(TestAttempt $attempt, string $type): bool`
  - `ConsentGate::assertSatisfied(TestAttempt $attempt): void` — 부족하면 `abort(403)`

**왜 검사 단위 옵트인인가:** 지금 `start()`는 동의 없이도 호출 가능하고 기존 33개 테스트가 그 경로에 의존한다. 전역으로 막으면 회귀가 난다. 6/26 spec이 요구한 건 "**실제 아동 대상 검사를 active 로 올리기 전에** 우회를 막아라"이므로, 검사 속성으로 켜는 것이 요구를 충족하면서 회귀도 없다.

**2026-07-28 fix round 1 갱신 (커밋 이후 리뷰 반영, 계획서와 실제 코드가 어긋나지 않도록 기록):**
- `LinkController`(`/t/{token}` 링크 응시 경로)도 `assessment.*` 와 같은 수준으로 게이트를 걸었다 — `take`/`submit`에 `ConsentGate::assertSatisfied`, `start`에는 `abort_if($voucher->test->consent_required, 403, ...)` (링크 수신자용 동의 화면이 아직 없어 **통째로 막는다** — Task 13이 화면을 만들 때까지 fail closed 상태 유지).
- `AssessmentController::start()`: 유료 검사 자격(entitlement) 확인(`isPaid`/`firstActive`/checkout 리다이렉트)이 `consent_required` 분기보다 **먼저** 돌도록 순서를 바꿨다. 또 `consent_required` 분기에서도 `$attempt->voucher_id` 가 비어있을 때만 `consume()`을 호출해 재진입 시 검사권 중복 소비를 막았고, attempt가 이미 `submitted`면 409로 막아 재진입이 `submitted → in_progress`로 되돌리지 못하게 했다.
- `AssessmentController::agree()`: 세션에 아직 `created` 상태인 attempt가 있으면 재사용한다 — 동의 폼 재제출마다 attempt+동의기록이 새로 쌓이는 걸 막기 위함(동의 기록은 법적 증거라 중복행이 특히 나쁘다는 판단).
- 이 갱신들의 테스트는 `tests/Feature/OyMsi/ConsentGateTest.php`(추가분)와 신규 `tests/Feature/OyMsi/LinkConsentGateTest.php`에 있다.
- **Critical 2(연령 미상 fail open)** 와 **Important 3(GUARDIAN_OFFLINE 기록 경로 없음)** 은 이 fix round에서 의도적으로 고치지 않았다 — 지금 fail closed로 바꾸면 나이를 채울 경로 자체가 없어 OY_MSI 응시가 전면 403이 된다. Task 13(연령 게이트)이 나이 수집과 동시에 전환하기로 사람이 판단했다. 아래 Task 13 절의 "fix round 1 필수 요구사항"을 반드시 반영할 것.

- [ ] **Step 1: 우회 차단 테스트 작성**

`tests/Feature/OyMsi/ConsentGateTest.php`:

```php
<?php
use App\Models\ConsentRecord;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

test('OY_MSI 는 consent_required 가 켜져 있다', function () {
    expect($this->test->consent_required)->toBeTrue();
});

test('동의하면 attempt 가 created 상태로 생기고 동의 기록이 남는다', function () {
    $this->actingAs($this->user)
        ->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1'])
        ->assertRedirect();

    $attempt = TestAttempt::where('test_id', $this->test->id)->latest('id')->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe('created');
    expect($attempt->assessment_version)->toBe('1.0.1');
    expect($attempt->scoring_version)->toBe('1.0.0');

    $consent = ConsentRecord::where('attempt_id', $attempt->id)->where('consent_type', 'sensitive')->first();
    expect($consent)->not->toBeNull();
    expect($consent->granted)->toBeTrue();
    expect($consent->actor)->toBe('youth');
    expect($consent->actor_user_id)->toBe($this->user->id);
});

test('동의 없이 만든 attempt 로는 take 에 들어갈 수 없다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $attempt->id]))
        ->assertForbidden();
});

test('동의 없이 submit 을 직접 호출해도 차단된다', function () {
    $attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);
    $answers = [];
    foreach ($this->test->items as $item) $answers[$item->id] = 0;

    $this->actingAs($this->user)
        ->post(route('assessment.submit', ['OY_MSI', $attempt->id]), ['answers' => $answers])
        ->assertForbidden();

    expect($attempt->fresh()->status)->not->toBe('submitted');
});

test('동의 체크를 빠뜨리면 attempt 가 만들어지지 않는다', function () {
    $this->actingAs($this->user)
        ->post(route('assessment.agree', 'OY_MSI'), [])
        ->assertSessionHasErrors('agree');

    expect(TestAttempt::where('test_id', $this->test->id)->count())->toBe(0);
});

test('consent_required 가 꺼진 기존 검사는 영향받지 않는다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    expect($sample->consent_required)->toBeFalse();

    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]))
        ->assertOk();
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ConsentGateTest`
Expected: FAIL — `consent_required` 컬럼 없음

- [ ] **Step 3: 마이그레이션 작성**

`database/migrations/2026_07_28_000003_add_consent_required_to_tests.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $t) {
            $t->boolean('consent_required')->default(false)->after('guardian_consent_below_age');
        });
    }

    public function down(): void
    {
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn('consent_required'));
    }
};
```

`app/Models/Test.php` 의 `$casts` 에 `'consent_required' => 'boolean'` 추가.
`database/seeders/OyMsi/TestSeeder.php` 의 `Test::create([...])` 에 `'consent_required' => true,` 추가.

- [ ] **Step 4: ConsentGate 작성**

`app/Services/OyMsi/ConsentGate.php`:

```php
<?php
namespace App\Services\OyMsi;

use App\Models\ConsentRecord;
use App\Models\TestAttempt;

class ConsentGate
{
    public const SENSITIVE = 'sensitive';
    public const GUARDIAN_OFFLINE = 'guardian_offline';

    public function record(
        TestAttempt $attempt,
        string $type,
        string $actor,
        ?int $actorUserId = null,
        array $meta = []
    ): ConsentRecord {
        return ConsentRecord::create([
            'attempt_id' => $attempt->id,
            'consent_type' => $type,
            'granted' => true,
            'granted_at' => now(),
            'actor' => $actor,
            'actor_user_id' => $actorUserId,
            'meta' => $meta ?: null,
        ]);
    }

    public function has(TestAttempt $attempt, string $type): bool
    {
        return $attempt->consents()
            ->where('consent_type', $type)->where('granted', true)->exists();
    }

    /** 이 검사가 요구하는 동의가 다 있는지. 없으면 403. */
    public function assertSatisfied(TestAttempt $attempt): void
    {
        $attempt->loadMissing('test');
        if (!$attempt->test->consent_required) return;

        abort_unless($this->has($attempt, self::SENSITIVE), 403, '검사 전 동의가 확인되지 않았습니다.');

        if ($attempt->test->needsGuardianConsentFor($attempt->age_at_test)) {
            abort_unless(
                $this->has($attempt, self::GUARDIAN_OFFLINE),
                403,
                '만 14세 미만은 법정대리인 동의 확인이 필요합니다.'
            );
        }
    }
}
```

- [ ] **Step 5: AssessmentController 수정 — agree 가 attempt 를 만든다**

`app/Http/Controllers/AssessmentController.php` — `agree()` 를 교체하고 `take`/`submit` 앞에 게이트를 건다:

```php
    public function agree(Request $request, string $code, \App\Services\OyMsi\ConsentGate $gate)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $rules = ['agree' => 'accepted'];
        if ($test->requires_guardian_consent) $rules['guardian_agree'] = 'accepted';
        $request->validate($rules);

        $request->session()->put('consent_ok:'.$code, true);

        // consent_required 검사는 이 시점에 attempt 를 만들고 동의를 영속화한다.
        // (세션 플래그만으로는 start() 직접 호출로 우회 가능했다 — 2026-06-26 spec 지적)
        if ($test->consent_required) {
            $attempt = TestAttempt::create(array_merge(
                $this->actorColumns($request),
                [
                    'test_id' => $test->id,
                    'status' => 'created',
                    'assessment_version' => $test->assessment_version,
                    'scoring_version' => $test->scoringRule?->version,
                    'age_at_test' => $request->session()->get('oymsi_age:'.$code),
                ]
            ));
            $gate->record($attempt, \App\Services\OyMsi\ConsentGate::SENSITIVE, 'youth', auth()->id());
            $request->session()->put('oymsi_attempt:'.$code, $attempt->id);
        }

        return redirect()->route('assessment.intro', $code);
    }
```

`start()` 에서 — `consent_required` 검사는 **새로 만들지 않고** 세션의 attempt 를 이어쓴다. 기존 `TestAttempt::create(...)` 앞에 다음을 넣는다:

```php
        if ($test->consent_required) {
            $existingId = $request->session()->get('oymsi_attempt:'.$code);
            $attempt = $existingId ? TestAttempt::find($existingId) : null;
            abort_unless($attempt && $attempt->test_id === $test->id, 403, '검사 전 동의가 확인되지 않았습니다.');
            $attempt->update(['status' => 'in_progress', 'started_at' => now()]);
            return redirect()->route('assessment.take', [$code, $attempt->id]);
        }
```

`take()` 와 `submit()` 의 `$this->authorizeAttempt(...)` 바로 다음 줄에 각각 추가:

```php
        app(\App\Services\OyMsi\ConsentGate::class)->assertSatisfied($attempt);
```

- [ ] **Step 6: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ConsentGateTest`
Expected: PASS 6건

- [ ] **Step 7: 전체 회귀 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 기존 pass 유지. `GuardianConsentTest` 가 특히 중요하다 — `requires_guardian_consent` 흐름을 건드렸으므로.

- [ ] **Step 8: 커밋**

```bash
git add database/migrations/2026_07_28_000003_add_consent_required_to_tests.php \
        app/Services/OyMsi/ConsentGate.php app/Http/Controllers/AssessmentController.php \
        app/Models/Test.php database/seeders/OyMsi/TestSeeder.php \
        tests/Feature/OyMsi/ConsentGateTest.php
git commit -m "fix(assessment): 동의 우회 차단 — 동의 시점에 attempt 생성 + consent_records 영속화"
```

---

## Task 13: 연령 게이트

**Files:**
- Create: `app/Http/Controllers/OyMsi/AgeGateController.php`
- Create: `resources/views/oymsi/age-gate.blade.php`
- Create: `resources/views/oymsi/age-blocked.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/AssessmentController.php` (`consent` 진입 시 연령 확인 강제)
- Modify: `app/Http/Controllers/LinkController.php` (`landing`/`start` 에 연령 확인)
- Test: `tests/Feature/OyMsi/AgeGateTest.php`

**Interfaces:**
- Consumes: Task 1의 `tests.min_age|max_age|guardian_consent_below_age`, `Test::needsGuardianConsentFor()`; Task 12의 `ConsentGate`
- Produces:
  - 라우트 `GET /assessment/{code}/age` → `oymsi.age.form`, `POST /assessment/{code}/age` → `oymsi.age.submit`
  - 라우트 `GET /t/{token}/age` → `link.age.form`, `POST /t/{token}/age` → `link.age.submit`
  - 세션 키 `oymsi_age:{code}` (개인) / `oymsi_age_token:{token}` (링크) — 만 나이 정수
  - `AgeGateController::calculateAge(string $birthdate): int` — 만 나이. **생년월일은 저장하지 않는다.**

**차단 규칙**
| 상황 | 결과 |
|---|---|
| 만 14세 미만 · 개인 경로 | 차단. "기관을 통해 응시할 수 있어요" + 꿈드림 + 1388 |
| 만 14세 미만 · 링크 경로 · 담당자 확인 **있음** | 통과 + `guardian_offline` 동의 기록 |
| 만 14세 미만 · 링크 경로 · 담당자 확인 **없음** | 차단. "담당자에게 문의해 주세요" |
| `min_age` 미만 (만 13세 미만) | 차단. 대상 아님 |
| `max_age` 초과 (만 19세 이상) | 차단. 대상 아님 |

**⚠️ Task 12 fix round 1 로부터 필수 요구사항 (2026-07-28, 사람 판단 — 재질문 불필요, 임의로 빠뜨리지 말 것):**

Task 12 리뷰에서 Critical 2 / Important 3 두 건이 발견됐고, 지금(Task 12 시점) 고치면 나이를 채울 경로가 없어 OY_MSI 응시가 전면 403이 되므로 **나이 수집(이 Task 13)과 동시에 전환**하기로 했다. 이 Task를 구현하는 사람은 아래 3개를 빠뜨리면 안 된다:

**(a) fail closed 전환 — `age_at_test` 가 null 이면 "동의 불필요"가 아니라 "미충족(403)"으로 처리한다.**
근본 원인: `Test::needsGuardianConsentFor($age)` 가 `$age === null` 이면 `false`를 반환한다(app/Models/Test.php) → `ConsentGate::assertSatisfied()`가 나이를 모르는 attempt를 "보호자 동의 불필요"로 오판해 통과시킨다(fail open). 이 Task가 age-gate로 `consent()`/`link.landing()` 진입 전에 나이를 강제로 받게 만들면 정상 플로우에서는 `age_at_test`가 항상 채워지겠지만, **그것과 별개로** `ConsentGate`(또는 `Test::needsGuardianConsentFor`) 자체를 "guardian_consent_below_age 가 설정된 검사인데 age 를 모르면 차단"으로 바꿔야 한다 — 정상 플로우 밖에서 attempt가 만들어지는 경로(예: 직접 DB 조작, 다른 컨트롤러의 미래 진입점)에 대한 방어선이다. 이 태스크 완료 시 `age_at_test = null` + `guardian_consent_below_age` 설정된 검사 조합이 403이 되는지 테스트로 고정할 것.

**(b) `GUARDIAN_OFFLINE` 동의를 실제로 기록한다.**
현재 `ConsentGate::assertSatisfied()`는 `needsGuardianConsentFor()`가 true 인데 `GUARDIAN_OFFLINE` 타입 동의가 없으면 403 을 던지지만, 이 동의를 실제로 `ConsentGate::record(..., ConsentGate::GUARDIAN_OFFLINE, ...)`로 남기는 코드가 어디에도 없다 — 지금 상태로 (a)까지 적용하면 만 14세 미만은 **영원히 통과할 수 없는 403 데드락**이 된다. 이 Task의 `linkSubmit()`에서 `guardianConfirmed`(담당자 확인, 브리프 예시의 `guardian_consent_confirmed_at`)가 true 인 경로를 통과시킬 때 반드시 `ConsentGate::record($attempt, ConsentGate::GUARDIAN_OFFLINE, 'staff', ...)`를 호출해야 한다. 단, 이 시점엔 아직 attempt가 없을 수 있으므로(연령 확인이 동의/시작보다 앞선 단계) attempt 생성 이후(예: `agree()`/링크 동의 확정 시점)로 기록 위치를 조정해도 된다 — 핵심은 "빠뜨리지 않는 것"이다.
참고: 기존 `requires_guardian_consent` + `guardian_agree` 체크박스(GuardianConsentTest 가 검증하는 옛 플로우)는 검증만 하고 `consent_records`에 아무것도 남기지 않는다 — `consent_required` 계열과는 별개 메커니즘으로 계속 둘 것인지, `guardian_agree` 체크를 `GUARDIAN_OFFLINE` 기록과 연결할 것인지는 이 Task에서 판단해서 정리할 것 (임의로 둘 다 유지한 채 방치하지 말 것).

**(c) 링크 경로(`oymsi_age_token:{token}`)에도 (a)(b) 동일 적용 + 링크 수신자용 동의 화면.**
`LinkController::start()`는 Task 12 fix round 1 에서 `consent_required` 검사를 **통째로 막아뒀다**(`abort_if($voucher->test->consent_required, 403, ...)`, 링크용 동의 화면이 없어서). 이 Task가 링크 수신자용 동의 확인 화면(또는 흐름)을 만들면 그 차단을 걷어내고, 대신 (a)(b)가 링크 경로에서도 동일하게 적용되는지 확인할 것 — 특히 링크는 로그인이 없는 guest 흐름이라 `age_at_test`/동의 상태를 세션(`oymsi_age_token:{token}`)에 의존하는데, 여기서도 null-이면-차단(fail closed) 원칙이 지켜져야 한다.

- [ ] **Step 1: 연령 게이트 테스트 작성**

`tests/Feature/OyMsi/AgeGateTest.php`:

```php
<?php
use App\Models\Test;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->user = User::factory()->create();
});

function birthdateForAge(int $age): string
{
    return now()->subYears($age)->subDays(1)->format('Y-m-d');
}

test('만 14~18세는 개인 경로로 통과한다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => birthdateForAge(16)])
        ->assertRedirect(route('assessment.consent', 'OY_MSI'));

    expect(session('oymsi_age:OY_MSI'))->toBe(16);
});

test('만 13세는 개인 경로에서 차단되고 기관 안내를 본다', function () {
    $res = $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => birthdateForAge(13)]);

    $res->assertOk();
    $res->assertSee('기관을 통해');
    $res->assertSee('1388');
    expect(session('oymsi_age:OY_MSI'))->toBeNull();
});

test('만 12세는 대상 연령이 아니라 차단된다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => birthdateForAge(12)])
        ->assertOk()
        ->assertSee('대상');
});

test('만 19세는 대상 연령이 아니라 차단된다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => birthdateForAge(19)])
        ->assertOk()
        ->assertSee('대상');
});

test('연령 확인 없이 동의 화면에 들어가면 연령 게이트로 보낸다', function () {
    $this->actingAs($this->user)
        ->get(route('assessment.consent', 'OY_MSI'))
        ->assertRedirect(route('oymsi.age.form', 'OY_MSI'));
});

test('링크 경로 · 만 13세 · 담당자 확인 있으면 통과하고 동의가 기록된다', function () {
    $voucher = Voucher::create([
        'user_id' => $this->user->id, 'test_id' => $this->test->id,
        'source' => 'link', 'status' => 'active', 'issued_at' => now(),
        'access_token' => 'tok13ok',
        'guardian_consent_confirmed_at' => now(),
        'guardian_consent_confirmed_by' => $this->user->id,
    ]);

    $this->post(route('link.age.submit', $voucher->access_token), ['birthdate' => birthdateForAge(13)])
        ->assertRedirect(route('link.landing', $voucher->access_token));

    expect(session('oymsi_age_token:tok13ok'))->toBe(13);
});

test('링크 경로 · 만 13세 · 담당자 확인 없으면 차단된다', function () {
    $voucher = Voucher::create([
        'user_id' => $this->user->id, 'test_id' => $this->test->id,
        'source' => 'link', 'status' => 'active', 'issued_at' => now(),
        'access_token' => 'tok13no',
    ]);

    $this->post(route('link.age.submit', $voucher->access_token), ['birthdate' => birthdateForAge(13)])
        ->assertOk()
        ->assertSee('담당자');

    expect(session('oymsi_age_token:tok13no'))->toBeNull();
});

test('생년월일은 어디에도 저장되지 않는다 (만 나이만)', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => '2010-03-15']);

    expect(session()->all())->not->toHaveKey('birthdate');
    expect(session('oymsi_age:OY_MSI'))->toBeInt();
});

test('미래 날짜·잘못된 형식은 거부한다', function () {
    $this->actingAs($this->user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => now()->addDay()->format('Y-m-d')])
        ->assertSessionHasErrors('birthdate');
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=AgeGateTest`
Expected: FAIL — 라우트 없음

- [ ] **Step 3: 컨트롤러 작성**

`app/Http/Controllers/OyMsi/AgeGateController.php`:

```php
<?php
namespace App\Http\Controllers\OyMsi;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\Voucher;
use Illuminate\Http\Request;

class AgeGateController extends Controller
{
    public function form(string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        return view('oymsi.age-gate', [
            'test' => $test,
            'action' => route('oymsi.age.submit', $code),
        ]);
    }

    public function submit(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $age = $this->validateAge($request);

        if ($blocked = $this->blockReason($test, $age, guardianConfirmed: false)) {
            return response()->view('oymsi.age-blocked', [
                'test' => $test, 'reason' => $blocked, 'age' => $age,
            ]);
        }

        $request->session()->put('oymsi_age:'.$code, $age);
        return redirect()->route('assessment.consent', $code);
    }

    public function linkForm(string $token)
    {
        $voucher = Voucher::where('access_token', $token)->with('test')->firstOrFail();
        return view('oymsi.age-gate', [
            'test' => $voucher->test,
            'action' => route('link.age.submit', $token),
        ]);
    }

    public function linkSubmit(Request $request, string $token)
    {
        $voucher = Voucher::where('access_token', $token)->with('test')->firstOrFail();
        $test = $voucher->test;
        $age = $this->validateAge($request);

        $confirmed = $voucher->guardian_consent_confirmed_at !== null;
        if ($blocked = $this->blockReason($test, $age, guardianConfirmed: $confirmed)) {
            return response()->view('oymsi.age-blocked', [
                'test' => $test, 'reason' => $blocked, 'age' => $age,
            ]);
        }

        $request->session()->put('oymsi_age_token:'.$token, $age);
        return redirect()->route('link.landing', $token);
    }

    /** 만 나이. 생년월일은 반환하지 않는다 — 저장 금지. */
    public function calculateAge(string $birthdate): int
    {
        return (int) \Carbon\Carbon::parse($birthdate)->age;
    }

    private function validateAge(Request $request): int
    {
        $data = $request->validate([
            'birthdate' => ['required', 'date', 'before:today', 'after:'.now()->subYears(120)->format('Y-m-d')],
        ]);
        return $this->calculateAge($data['birthdate']);
    }

    /** @return null|'out_of_range'|'guardian_personal'|'guardian_link' */
    private function blockReason(Test $test, int $age, bool $guardianConfirmed): ?string
    {
        if ($test->min_age !== null && $age < $test->min_age) return 'out_of_range';
        if ($test->max_age !== null && $age > $test->max_age) return 'out_of_range';

        if ($test->needsGuardianConsentFor($age)) {
            return $guardianConfirmed ? null : ($this->isLinkContext() ? 'guardian_link' : 'guardian_personal');
        }
        return null;
    }

    private function isLinkContext(): bool
    {
        return request()->routeIs('link.*');
    }
}
```

- [ ] **Step 4: 라우트 등록**

`routes/web.php` — `assessment` 그룹 **위**에 개인 경로 연령 게이트를 두고(같은 `auth` 미들웨어), 링크 그룹 안에 링크용을 넣는다:

```php
use App\Http\Controllers\OyMsi\AgeGateController;

Route::middleware('auth')->controller(AgeGateController::class)
    ->prefix('assessment/{code}')->name('oymsi.age.')->group(function () {
        Route::get('age', 'form')->name('form');
        Route::post('age', 'submit')->name('submit');
    });
```

`link.` 그룹 안에 추가:

```php
    Route::get('age', [AgeGateController::class, 'linkForm'])->name('age.form');
    Route::post('age', [AgeGateController::class, 'linkSubmit'])->name('age.submit');
```

- [ ] **Step 5: 연령 미확인 시 게이트로 보내기**

`AssessmentController::consent()` 맨 앞에 추가:

```php
        if ($test->guardian_consent_below_age !== null
            && !$request->session()->has('oymsi_age:'.$code)) {
            return redirect()->route('oymsi.age.form', $code);
        }
```

(`consent(string $code)` 시그니처를 `consent(Request $request, string $code)` 로 바꾼다.)

`LinkController::landing()` 의 `voucherOrFail` 다음에 추가:

```php
        if ($voucher->test->guardian_consent_below_age !== null
            && !$request->session()->has('oymsi_age_token:'.$token)) {
            return redirect()->route('link.age.form', $token);
        }
```

- [ ] **Step 6: 뷰 2개 작성**

`resources/views/oymsi/age-gate.blade.php`:

```blade
<x-layouts.app :title="'연령 확인 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      <h1 class="text-2xl font-extrabold text-deepgreen">먼저 나이를 확인할게</h1>
      <p class="text-sm text-navy/60 mt-2">
        검사마다 참여할 수 있는 나이가 정해져 있어. 생년월일은 나이를 계산하는 데만 쓰고 저장하지 않아.
      </p>

      <form method="POST" action="{{ $action }}" class="mt-8">
        @csrf
        <label class="block text-sm font-semibold text-navy/80" for="birthdate">생년월일</label>
        <input id="birthdate" type="date" name="birthdate" required
               class="mt-2 w-full rounded-2xl border-navy/15 p-4 text-lg">
        @error('birthdate')
          <p class="mt-2 text-sm text-signal-red">{{ $message }}</p>
        @enderror

        <button class="mt-6 w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">
          다음
        </button>
      </form>
    </div>
  </div>
</x-layouts.app>
```

`resources/views/oymsi/age-blocked.blade.php`:

```blade
<x-layouts.app :title="'안내 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      @if($reason === 'out_of_range')
        <h1 class="text-2xl font-extrabold text-deepgreen">이 검사의 대상 나이가 아니야</h1>
        <p class="mt-3 text-navy/70">
          이 검사는 만 {{ $test->min_age }}~{{ $test->max_age }}세 청소년을 위한 검사야.
          다른 검사를 찾아볼 수 있어.
        </p>
        <a href="{{ route('catalog.index') }}"
           class="mt-6 inline-block rounded-xl bg-deepgreen text-cream px-6 py-3 font-bold">다른 검사 보기</a>

      @elseif($reason === 'guardian_personal')
        <h1 class="text-2xl font-extrabold text-deepgreen">기관을 통해 응시할 수 있어</h1>
        <p class="mt-3 text-navy/70">
          만 14세 미만은 법에 따라 보호자(법정대리인)의 동의가 확인되어야 검사할 수 있어.
          가까운 기관에 이야기하면 검사 링크를 받아서 바로 할 수 있어.
        </p>
        <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 space-y-2 text-sm">
          <p class="font-bold text-deepgreen">도움받을 수 있는 곳</p>
          <p>학교 밖 청소년 지원센터 <b>꿈드림</b></p>
          <p>청소년상담 <a href="tel:1388" class="font-bold text-teal">1388</a> · 24시간 365일</p>
          <p>자살예방 상담 <a href="tel:109" class="font-bold text-teal">109</a> · 24시간</p>
        </div>

      @else
        <h1 class="text-2xl font-extrabold text-deepgreen">담당자에게 문의해 줘</h1>
        <p class="mt-3 text-navy/70">
          만 14세 미만은 보호자(법정대리인) 동의가 확인되어야 검사할 수 있어.
          이 링크를 준 담당자에게 이야기하면 확인 후 다시 안내해 줄 거야.
        </p>
        <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 space-y-2 text-sm">
          <p>청소년상담 <a href="tel:1388" class="font-bold text-teal">1388</a></p>
          <p>자살예방 상담 <a href="tel:109" class="font-bold text-teal">109</a></p>
        </div>
      @endif
    </div>
  </div>
</x-layouts.app>
```

- [ ] **Step 7: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=AgeGateTest`
Expected: PASS 9건

- [ ] **Step 8: 전체 회귀 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 기존 pass 유지 (다른 검사는 `guardian_consent_below_age`가 null 이라 게이트가 걸리지 않는다)

- [ ] **Step 9: 커밋**

```bash
git add app/Http/Controllers/OyMsi/AgeGateController.php resources/views/oymsi/ \
        routes/web.php app/Http/Controllers/AssessmentController.php \
        app/Http/Controllers/LinkController.php tests/Feature/OyMsi/AgeGateTest.php
git commit -m "feat(oy-msi): 연령 게이트 — 만14세 미만 개인경로 차단·기관경로는 담당자 확인 필요 (PIPA 22-2)"
```

---

## Task 14: 기본정보 입력 (닉네임 · 성별)

**Files:**
- Create: `app/Http/Controllers/OyMsi/ProfileStepController.php`
- Create: `resources/views/oymsi/profile-step.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/AssessmentController.php` (`intro` → 기본정보 미입력 시 리다이렉트)
- Modify: `app/Http/Controllers/LinkController.php` (`start` 에서 닉네임·성별 수집)
- Modify: `resources/views/link/landing.blade.php` (닉네임·성별 입력 필드)
- Test: `tests/Feature/OyMsi/ProfileStepTest.php`

**Interfaces:**
- Consumes: Task 12의 세션 attempt (`oymsi_attempt:{code}`), Task 13의 세션 연령
- Produces:
  - 라우트 `GET/POST /assessment/{code}/profile` → `oymsi.profile.form` / `oymsi.profile.submit`
  - `test_attempts.nickname`(≤50) · `gender`(`male`|`female`|`no_answer`) · `age_at_test` 채워짐
  - 링크 경로는 `vouchers.recipient_name`(담당자 명부용, 담당자가 입력)과 `test_attempts.nickname`(청소년 본인 입력)을 **분리** 유지

- [ ] **Step 1: 테스트 작성**

`tests/Feature/OyMsi/ProfileStepTest.php`:

```php
<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->firstOrFail();
    $this->user = User::factory()->create();
});

/** 연령 게이트 + 동의를 통과한 상태를 만든다 */
function passGateAndConsent($testCase, User $user, int $age = 16): TestAttempt
{
    $testCase->actingAs($user)
        ->post(route('oymsi.age.submit', 'OY_MSI'), ['birthdate' => now()->subYears($age)->subDay()->format('Y-m-d')]);
    $testCase->actingAs($user)->post(route('assessment.agree', 'OY_MSI'), ['agree' => '1']);

    return TestAttempt::latest('id')->firstOrFail();
}

test('기본정보를 저장하면 attempt 에 닉네임·성별·나이가 남는다', function () {
    $attempt = passGateAndConsent($this, $this->user, 16);

    $this->actingAs($this->user)
        ->post(route('oymsi.profile.submit', 'OY_MSI'), ['nickname' => '민수', 'gender' => 'male'])
        ->assertRedirect(route('assessment.start', 'OY_MSI'));

    $attempt->refresh();
    expect($attempt->nickname)->toBe('민수');
    expect($attempt->gender)->toBe('male');
    expect($attempt->age_at_test)->toBe(16);
});

test('닉네임은 필수, 성별은 응답하지 않음을 고를 수 있다', function () {
    passGateAndConsent($this, $this->user);

    $this->actingAs($this->user)
        ->post(route('oymsi.profile.submit', 'OY_MSI'), ['gender' => 'no_answer'])
        ->assertSessionHasErrors('nickname');

    $this->actingAs($this->user)
        ->post(route('oymsi.profile.submit', 'OY_MSI'), ['nickname' => '별명', 'gender' => 'no_answer'])
        ->assertRedirect();

    expect(TestAttempt::latest('id')->first()->gender)->toBe('no_answer');
});

test('기본정보 없이 start 를 호출하면 기본정보 화면으로 보낸다', function () {
    passGateAndConsent($this, $this->user);

    $this->actingAs($this->user)
        ->post(route('assessment.start', 'OY_MSI'))
        ->assertRedirect(route('oymsi.profile.form', 'OY_MSI'));
});

test('학년·학교명은 받지 않는다 (학교 밖 청소년 대상)', function () {
    passGateAndConsent($this, $this->user);

    $html = $this->actingAs($this->user)->get(route('oymsi.profile.form', 'OY_MSI'))->getContent();
    expect($html)->not->toContain('name="grade"');
    expect($html)->not->toContain('name="school"');
    expect($html)->not->toContain('학교명');
});

test('링크 경로는 담당자 명부 이름과 청소년 닉네임을 분리 저장한다', function () {
    $voucher = Voucher::create([
        'user_id' => $this->user->id, 'test_id' => $this->test->id,
        'source' => 'link', 'status' => 'active', 'issued_at' => now(),
        'access_token' => 'tokprofile', 'recipient_name' => '김OO(명부)',
    ]);

    $this->post(route('link.age.submit', 'tokprofile'),
                ['birthdate' => now()->subYears(16)->subDay()->format('Y-m-d')]);
    $this->post(route('link.start', 'tokprofile'), ['nickname' => '별명이', 'gender' => 'female'])
         ->assertRedirect();

    $attempt = TestAttempt::where('voucher_id', $voucher->id)->firstOrFail();
    expect($attempt->nickname)->toBe('별명이');
    expect($attempt->age_at_test)->toBe(16);
    expect($voucher->fresh()->recipient_name)->toBe('김OO(명부)'); // 담당자 입력값 보존
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ProfileStepTest`
Expected: FAIL — 라우트 없음

- [ ] **Step 3: 컨트롤러 작성**

`app/Http/Controllers/OyMsi/ProfileStepController.php`:

```php
<?php
namespace App\Http\Controllers\OyMsi;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Http\Request;

class ProfileStepController extends Controller
{
    public const GENDERS = ['male', 'female', 'no_answer'];

    public function form(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $this->attemptOrFail($request, $code);

        return view('oymsi.profile-step', ['test' => $test]);
    }

    public function submit(Request $request, string $code)
    {
        $attempt = $this->attemptOrFail($request, $code);

        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:'.implode(',', self::GENDERS)],
        ]);

        $attempt->update([
            'nickname' => $data['nickname'],
            'gender' => $data['gender'],
            'age_at_test' => $attempt->age_at_test ?? $request->session()->get('oymsi_age:'.$code),
        ]);

        return redirect()->route('assessment.start', $code);
    }

    private function attemptOrFail(Request $request, string $code): TestAttempt
    {
        $id = $request->session()->get('oymsi_attempt:'.$code);
        $attempt = $id ? TestAttempt::find($id) : null;
        abort_unless($attempt, 403, '검사 전 동의가 확인되지 않았습니다.');
        return $attempt;
    }
}
```

- [ ] **Step 4: 라우트 등록**

`routes/web.php` — 연령 게이트 그룹 옆에:

```php
use App\Http\Controllers\OyMsi\ProfileStepController;

Route::middleware('auth')->controller(ProfileStepController::class)
    ->prefix('assessment/{code}')->name('oymsi.profile.')->group(function () {
        Route::get('profile', 'form')->name('form');
        Route::post('profile', 'submit')->name('submit');
    });
```

- [ ] **Step 5: start 진입 전 기본정보 확인**

`AssessmentController::start()` 의 `consent_required` 블록 안, `$attempt->update([...])` **앞**에 추가:

```php
            if (!$attempt->nickname) {
                return redirect()->route('oymsi.profile.form', $code);
            }
```

- [ ] **Step 6: 뷰 작성**

`resources/views/oymsi/profile-step.blade.php`:

```blade
<x-layouts.app :title="'기본정보 · '.$test->title_easy">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      <p class="text-sm text-teal font-semibold">2 동의 완료 · <span class="text-navy/40">3 기본정보 · 4 검사</span></p>
      <h1 class="text-2xl font-extrabold text-deepgreen mt-2">뭐라고 부르면 좋을까?</h1>
      <p class="text-sm text-navy/60 mt-2">결과지에 이 이름이 나와. 실명이 아니어도 괜찮아.</p>

      <form method="POST" action="{{ route('oymsi.profile.submit', $test->code) }}" class="mt-8 space-y-6">
        @csrf
        <div>
          <label class="block text-sm font-semibold text-navy/80" for="nickname">이름 또는 별명</label>
          <input id="nickname" name="nickname" type="text" maxlength="50" required
                 value="{{ old('nickname') }}"
                 class="mt-2 w-full rounded-2xl border-navy/15 p-4 text-lg" placeholder="예: 민수">
          @error('nickname')<p class="mt-2 text-sm text-signal-red">{{ $message }}</p>@enderror
        </div>

        <div>
          <span class="block text-sm font-semibold text-navy/80">성별</span>
          <div class="mt-2 grid grid-cols-3 gap-2">
            @foreach(['male' => '남', 'female' => '여', 'no_answer' => '응답하지 않음'] as $value => $label)
              <label class="cursor-pointer">
                <input type="radio" name="gender" value="{{ $value }}" class="peer sr-only"
                       @checked(old('gender') === $value) required>
                <span class="block rounded-2xl bg-white p-4 text-center text-sm ring-1 ring-black/5
                             peer-checked:bg-deepgreen peer-checked:text-cream peer-checked:font-bold">
                  {{ $label }}
                </span>
              </label>
            @endforeach
          </div>
          @error('gender')<p class="mt-2 text-sm text-signal-red">{{ $message }}</p>@enderror
        </div>

        <button class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">
          검사 시작하기
        </button>
      </form>
    </div>
  </div>
</x-layouts.app>
```

- [ ] **Step 7: 링크 경로에 닉네임·성별 반영**

`app/Http/Controllers/LinkController.php` — `start()` 의 validate 와 attempt 생성을 교체:

```php
        $data = $request->validate([
            'nickname' => 'required|string|max:50',
            'gender' => 'required|in:male,female,no_answer',
        ]);

        $attempt = TestAttempt::create([
            'user_id' => null,
            'guest_token' => $this->guestToken($request),
            'test_id' => $voucher->test_id,
            'voucher_id' => $voucher->id,
            'status' => 'created',
            'nickname' => $data['nickname'],
            'gender' => $data['gender'],
            'age_at_test' => $request->session()->get('oymsi_age_token:'.$token),
            'assessment_version' => $voucher->test->assessment_version,
            'scoring_version' => $voucher->test->scoringRule?->version,
            'started_at' => now(),
        ]);

        // 동의 기록 — 민감정보 + (만14세 미만이면) 담당자가 확보한 법정대리인 동의
        $gate = app(\App\Services\OyMsi\ConsentGate::class);
        $gate->record($attempt, \App\Services\OyMsi\ConsentGate::SENSITIVE, 'youth');
        if ($voucher->guardian_consent_confirmed_at) {
            $gate->record(
                $attempt,
                \App\Services\OyMsi\ConsentGate::GUARDIAN_OFFLINE,
                'staff',
                $voucher->guardian_consent_confirmed_by,
                ['confirmed_at' => $voucher->guardian_consent_confirmed_at->toIso8601String()]
            );
        }

        $attempt->update(['status' => 'in_progress']);
```

**`$voucher->update(['recipient_name' => ...])` 호출은 제거한다** — 명부 이름은 담당자가 발급 시 입력한 값이고, 청소년이 덮어쓰면 안 된다.

`LinkController::take()`·`submit()` 의 `authorizeLinkAttempt` 다음 줄에 게이트 추가:

```php
        app(\App\Services\OyMsi\ConsentGate::class)->assertSatisfied($attempt);
```

- [ ] **Step 8: 링크 랜딩 뷰 수정**

`resources/views/link/landing.blade.php` 의 입력 폼에서 `recipient_name`·`recipient_age` 필드를 **닉네임·성별**로 교체한다(위 profile-step 의 입력 마크업을 그대로 쓴다). `action` 은 `route('link.start', $voucher->access_token)` 유지.

- [ ] **Step 9: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ProfileStepTest`
Expected: PASS 5건

- [ ] **Step 10: 전체 회귀 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 기존 pass 유지. `MyVoucherTest`·`VoucherServiceTest` 가 `recipient_name` 을 참조하면 조정한다 — **담당자 입력 경로는 그대로 두고 청소년 입력 경로만 바뀐 것**이다.

- [ ] **Step 11: 커밋**

```bash
git add app/Http/Controllers/OyMsi/ProfileStepController.php \
        resources/views/oymsi/profile-step.blade.php resources/views/link/landing.blade.php \
        routes/web.php app/Http/Controllers/AssessmentController.php \
        app/Http/Controllers/LinkController.php tests/Feature/OyMsi/ProfileStepTest.php
git commit -m "feat(oy-msi): 기본정보 단계 — 닉네임·성별만 수집(학년·학교명 제거), 명부 이름과 분리"
```

---

## Task 15: 결과 문안 시더 + 금지 표현 검사

**Files:**
- Create: `docs/source/oy-msi/003-preliminary.txt` · `005-youth-report.txt` · `006-guardian-report.txt` (docx 추출 원문, 전사 대조용)
- Create: `database/seeders/OyMsi/TemplateSeeder.php`
- Create: `tests/Feature/OyMsi/TemplateCompletenessTest.php`
- Test: 위 파일

**Interfaces:**
- Consumes: Task 1의 `interpretation_templates`
- Produces: `interpretation_templates` 레코드 **174건**, `version='1.0.0'`, `locale='ko-KR'`.
  키 규칙 (007 §9.2) — `result.{audience}.{subject}.{band}.{component}`

| 묶음 | 키 패턴 | 건수 |
|---|---|---|
| 청소년 요인 | `result.YOUTH.{FACTOR}.{BAND}.meaning` / `.actions` | 9 × 3 × 2 = **54** |
| 보호자 요인 | `result.GUARDIAN.{FACTOR}.{BAND}.meaning` / `.actions` / `.avoid` | 9 × 3 × 3 = **81** |
| 안전 | `result.{YOUTH\|GUARDIAN}.SAF.{S0..S3}.safety_notice` | 2 × 4 = **8** |
| 환경 | `result.{YOUTH\|GUARDIAN}.ENV.{E0..E3}.safety_notice` | 2 × 4 = **8** |
| 종합 | `result.{YOUTH\|GUARDIAN}.OVERALL.{GREEN\|YELLOW\|RED}.meaning` | 2 × 3 = **6** |
| 강점 | `strength.{TRY_NEW\|SMALL_GOAL\|RECOVERY_HOPE\|HONEST_RESPONSE\|HELP_SEEKING}.text` | **5** |

| 솔루션 | `solution.{SOL_*}.steps` (줄바꿈 구분) | **10** |
| 고지문 | `disclaimer.YOUTH` · `disclaimer.GUARDIAN` | **2** |
| | **합계** | **174** |

`FACTOR` = DEP·ANX·IMP·TRM·ISO·FAM·LIF·RSK·FUT (SAF 제외)
`BAND` = GREEN·YELLOW·RED

**`HELP_SEEKING` 주의:** 007 §10.3 은 강점 5종을 정의하지만 `HELP_SEEKING` 의 발동 조건은 "초기정보에서 지원희망=true"다. 1단계는 그 필드를 수집하지 않으므로(질문 5에서 기본정보를 닉네임·성별로 최소화) **Task 3 의 `rules['strengths']` 에는 4종만 넣고, 문안은 5종 다 만들어 둔다.** 2단계에서 지원희망 항목이 생기면 규칙만 추가하면 된다.

**문안은 창작이 아니라 전사다.** 원문 위치:

| 키 묶음 | 원문 |
|---|---|
| 청소년 요인 | 003 Ⅵ (요인 1~9 × 초록/노랑/빨강 의 "해석" → `meaning`, "청소년 솔루션" → `actions`) |
| 보호자 요인 | 003 Ⅶ (요인 1~9 × 3밴드 불릿) + 006 5~13페이지로 보강. 불릿 중 "~하지 않는다 / ~을 피한다" 문장은 `avoid`, 나머지는 `actions` |
| 안전 | 003 Ⅴ.3 (초록/노랑/빨강 조치) → S0~S3 매핑, 006 14페이지 |
| 종합 | 005 2페이지 "종합결과 자동문안" 3종, 006 2페이지 |
| 강점 | 007 §10.3 표의 "표시 문안" |
| 솔루션 | 003 Ⅵ 각 밴드의 청소년 솔루션 항목 + 007 §10.1 제목 |
| 고지문 | 005 부록2 전문, 006 최종 고지문 |

> ### ⚠️ 2026-07-28 정정 — 위 매핑의 전제가 틀렸다 (003 Ⅶ 은 수신자 4종 혼합 섹션)
>
> 위 표는 "보호자 요인 ← **003 Ⅶ**" 이라고 적었지만, 003 Ⅶ 의 실제 절 제목은
> **`Ⅶ. 부모·보호자·교사·상담자용 해석과 솔루션`** (`docs/source/oy-msi/003-preliminary.txt:437`) 으로
> **네 종류의 수신자(부모·보호자·교사·상담자)를 한 절에 합쳐 쓴 것**이다.
> **006 은 사정이 다르다 — 문서 구조 문제가 아니다.** 006 은 `제1부. 부모·보호자용 결과보고서`(`:6`)와
> `제2부. 교사·상담자용 전문가 결과보고서`(`:415`)가 명확히 나뉘어 있고, 아래 표에서 제거한 두 줄
> (`:233`, `:278`)은 둘 다 **제1부의 "부모·보호자가 할 일" 목록 안**에 있다. 담당자 섹션이 흘러넘친 것이
> 아니라 **저자가 보호자용 목록 안에 담당자 시점 문장을 직접 쓴 것**이다(표지의
> `부모·보호자용 및 교사·상담자용 결과보고서`(`:5`)는 두 보고서 합본이라는 뜻일 뿐이다).
> 제거 판단은 출처 구조가 아니라 문장 내용 기준 (a)(b) 로 했으므로 그대로 유효하다.
>
> 계획서는 이 절 **전체**를 `result.GUARDIAN.*` 로 매핑했고, 그 결과
> **교사·상담자에게 하는 말이 보호자 화면(`/r/{token}`, 보호자 본인이 읽는다)에 그대로 표시**됐다.
> Task 18 에서 보호자 공유 링크가 붙으면서 실제 노출 경로가 생겨 문제가 드러났다.
>
> **조치(라운드 2, 2026-07-28 · 리포트 `.superpowers/sdd/2026-07-27-oy-msi-phase1/task-18b-report.md`)**
> — 아래 두 유형만 최소 범위로 본문에서 제거했다. **키는 삭제하지 않았다**(174건 전수 계약 +
> `ReportComposer::text()` 는 키가 없으면 예외를 던진다). 제거 자리에 새 문장을 짓지 않았다.
>
> | 키 | 제거한 줄 | 원문 위치 | 기준 |
> |---|---|---|---|
> | `result.GUARDIAN.TRM.RED.avoid` | 가해 가능성이 있는 보호자에게 성급하게 알리지 않습니다. | 003 Ⅶ.4 빨강 | (a)(b) |
> | `result.GUARDIAN.TRM.RED.actions` | 보호자가 관련된 경우 제3의 전문기관을 우선합니다. | 006 8p | (a)(b) |
> | `result.GUARDIAN.FAM.RED.actions` | 보호자에게 결과를 제공하는 것이 청소년의 위험을 높이는 경우 결과공유를 제한합니다. | 006 10p | (a)(b) |
>
> 기준 (a) 보호자·가족을 평가/경계 대상으로 지목 · (b) 보호자 모르게 진행되는 보호·신고·증거 절차 예고.
> 앞선 라운드 1(커밋 `1992d68`)에서 같은 이유로 `result.GUARDIAN.ENV.E0~E3` 을 중립 안내로 교체했다.
> `result.YOUTH.*` 는 005(청소년용)에서 왔고 수신자가 맞으므로 **무수정**이며,
> `TemplateCompletenessTest` 가 65건 해시로 고정한다.
>
> **`FAM.RED.meaning` 은 문안 대신 공유 차단으로 닫았다(2026-07-28 결정).** 이 문장
> ("가족이나 보호자가 보호자원보다 두려움이나 위험의 원인일 가능성을 확인해야 합니다")은 제거하면
> 키가 비어(빈 문안 금지) 보류했었다. 대신 **FAM 요인이 RED 면 보호자 공유 자체를 차단**한다 —
> 발급·열람 모두(`ShareController::familyRiskBlocksShare`). 가정이 위험 출처일 수 있는 경우
> 그 가정에 결과를 넘기지 않는다는 판단이며, 문장을 지어내지 않고 노출 경로를 없애는 방식이다.
> S2·E2 의 `needsContactFirst`(공유를 2차 선택으로 낮춤)와 달리 이것은 **차단**이다.
>
> **남은 일(저자 확인 대상):** 위 표 외에도 담당자 어투로 남은 문장이 여럿 있다(예:
> `LIF.RED.actions` 의 "사례관리", `DEP.YELLOW.actions` 의 "권고합니다",
> `IMP.RED.actions` 의 "가족 전체의 갈등대응 방법을 점검합니다").
> 또한 솔루션 제목 `SOL_FAM_PROTECT` = "보호자 안전성 평가·중재" 는 **FAM 이 YELLOW 일 때
> 여전히 보호자 화면에 렌더된다**(FAM RED 차단으로 닫히지 않는다 — 리포트 §11 에 실측 있음).
> 목록과 판단 근거는 리포트에 있다. 저자 확인 후 문안 교체 라운드가 필요하다.

- [ ] **Step 1: 원문을 리포지토리로 옮긴다**

docx 는 `C:\work\심지` 루트에 있고 simji 리포 밖이다. 전사 작업자가 원문을 볼 수 있게 텍스트를 리포로 복사한다.

```bash
mkdir -p docs/source/oy-msi
```

`C:\work\심지\*.docx` 4종을 아래 파이썬으로 추출해 `docs/source/oy-msi/` 에 저장한다(파일명: `003-preliminary.txt`, `005-youth-report.txt`, `006-guardian-report.txt`, `007-scoring-spec.txt`).

```python
import io, os, zipfile, glob, xml.etree.ElementTree as ET
NS = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
def txt(p): return ''.join(t.text or '' for t in p.iter('{%s}t' % NS['w']))
def extract(path):
    root = ET.fromstring(zipfile.ZipFile(path).read('word/document.xml'))
    out = []
    for el in root.find('w:body', NS):
        tag = el.tag.split('}')[1]
        if tag == 'p':
            s = txt(el).strip()
            if s: out.append(s)
        elif tag == 'tbl':
            out.append('[표시작]')
            for tr in el.findall('w:tr', NS):
                out.append(' | '.join(txt(tc).strip() for tc in tr.findall('w:tc', NS)))
            out.append('[표끝]')
    return '\n'.join(out)
# 실행 예: python extract.py  (경로는 로컬에 맞게 조정)
```

- [ ] **Step 2: 완결성 테스트를 먼저 작성**

`tests/Feature/OyMsi/TemplateCompletenessTest.php`:

```php
<?php
use App\Models\InterpretationTemplate;
use Database\Seeders\OyMsi\TemplateSeeder;

const FACTORS = ['DEP', 'ANX', 'IMP', 'TRM', 'ISO', 'FAM', 'LIF', 'RSK', 'FUT'];
const BANDS = ['GREEN', 'YELLOW', 'RED'];

beforeEach(function () {
    (new TemplateSeeder())->run();
    $this->keys = InterpretationTemplate::pluck('text', 'template_key');
});

test('청소년 요인 문안 54건이 모두 있다', function () {
    $missing = [];
    foreach (FACTORS as $f) {
        foreach (BANDS as $b) {
            foreach (['meaning', 'actions'] as $c) {
                $key = "result.YOUTH.{$f}.{$b}.{$c}";
                if (!isset($this->keys[$key])) $missing[] = $key;
            }
        }
    }
    expect($missing)->toBe([]);
});

test('보호자 요인 문안 81건이 모두 있다', function () {
    $missing = [];
    foreach (FACTORS as $f) {
        foreach (BANDS as $b) {
            foreach (['meaning', 'actions', 'avoid'] as $c) {
                $key = "result.GUARDIAN.{$f}.{$b}.{$c}";
                if (!isset($this->keys[$key])) $missing[] = $key;
            }
        }
    }
    expect($missing)->toBe([]);
});

test('안전·환경·종합 문안이 모두 있다', function () {
    $missing = [];
    foreach (['YOUTH', 'GUARDIAN'] as $a) {
        foreach (['S0', 'S1', 'S2', 'S3'] as $lv) {
            $k = "result.{$a}.SAF.{$lv}.safety_notice";
            if (!isset($this->keys[$k])) $missing[] = $k;
        }
        foreach (['E0', 'E1', 'E2', 'E3'] as $lv) {
            $k = "result.{$a}.ENV.{$lv}.safety_notice";
            if (!isset($this->keys[$k])) $missing[] = $k;
        }
        foreach (BANDS as $b) {
            $k = "result.{$a}.OVERALL.{$b}.meaning";
            if (!isset($this->keys[$k])) $missing[] = $k;
        }
    }
    expect($missing)->toBe([]);
});

test('강점 5 · 솔루션 10 · 고지문 2 가 있다', function () {
    foreach (['TRY_NEW', 'SMALL_GOAL', 'RECOVERY_HOPE', 'HONEST_RESPONSE', 'HELP_SEEKING'] as $s) {
        expect($this->keys)->toHaveKey("strength.{$s}.text");
    }
    foreach ([
        'SOL_SAF_PLAN', 'SOL_TRM_SAFETY', 'SOL_DEP_ACTIVATION', 'SOL_LIF_7DAY', 'SOL_ANX_BREATHING',
        'SOL_IMP_STOP', 'SOL_ISO_CONNECT', 'SOL_FAM_PROTECT', 'SOL_RSK_DIGITAL', 'SOL_FUT_3MONTH',
    ] as $sol) {
        expect($this->keys)->toHaveKey("solution.{$sol}.steps");
    }
    expect($this->keys)->toHaveKey('disclaimer.YOUTH');
    expect($this->keys)->toHaveKey('disclaimer.GUARDIAN');
});

test('총 174건이다', function () {
    expect(InterpretationTemplate::count())->toBe(174);
});

test('빈 문안이 없다', function () {
    expect(InterpretationTemplate::where('text', '')->orWhereNull('text')->count())->toBe(0);
});

test('금지 표현이 없다 (005 부록1 §3)', function () {
    $forbidden = [
        '문제가 심각하다', '비정상이다', '정신적으로 이상하다', '위험한 청소년이다',
        '부모의 말을 듣지 않는다', '의지가 부족하다', '게으르다', '부적응자이다',
        '자살성향이 있다', '치료가 반드시 필요하다',
    ];

    $hits = [];
    foreach (InterpretationTemplate::all() as $t) {
        foreach ($forbidden as $phrase) {
            if (str_contains($t->text, $phrase)) $hits[] = "{$t->template_key}: {$phrase}";
        }
    }
    expect($hits)->toBe([], "금지 표현 발견:\n" . implode("\n", $hits));
});

test('청소년용 문안은 반말체다', function () {
    // 005 문안은 "~해", "~야", "~어" 로 끝난다. 존댓말(습니다/세요)이 섞이면 톤이 깨진다.
    $violations = [];
    foreach (InterpretationTemplate::where('template_key', 'like', 'result.YOUTH.%')->get() as $t) {
        if (preg_match('/(습니다|하세요|십시오)/u', $t->text)) $violations[] = $t->template_key;
    }
    expect($violations)->toBe([]);
});
```

- [ ] **Step 3: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=TemplateCompletenessTest`
Expected: FAIL — `TemplateSeeder` 없음

- [ ] **Step 4: 시더 골격 작성**

`database/seeders/OyMsi/TemplateSeeder.php`:

```php
<?php
namespace Database\Seeders\OyMsi;

use App\Models\InterpretationTemplate;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    private const VERSION = '1.0.0';

    public function run(): void
    {
        foreach ($this->all() as $key => $text) {
            InterpretationTemplate::updateOrCreate(
                ['template_key' => $key, 'locale' => 'ko-KR', 'version' => self::VERSION],
                ['text' => $text, 'active' => true]
            );
        }
    }

    /** @return array<string, string> */
    private function all(): array
    {
        return array_merge(
            $this->youthFactors(),
            $this->guardianFactors(),
            $this->safetyAndEnvironment(),
            $this->overall(),
            $this->strengths(),
            $this->solutions(),
            $this->disclaimers(),
        );
    }
```

- [ ] **Step 5: 청소년 요인 문안 54건 전사 (003 Ⅵ)**

같은 파일에 이어서. **DEP 3밴드를 아래 형식 그대로** 채우고, 나머지 8요인(ANX·IMP·TRM·ISO·FAM·LIF·RSK·FUT)도 003 Ⅵ 의 해당 절에서 같은 방식으로 옮긴다. `meaning` = "해석" 문단, `actions` = "청소년 솔루션" 항목을 줄바꿈으로 이어 붙인다.

```php
    private function youthFactors(): array
    {
        return [
            // ── DEP 우울·무기력 (003 Ⅵ.1)
            'result.YOUTH.DEP.GREEN.meaning' =>
                '현재 슬픔이나 무기력 때문에 일상생활이 크게 흔들리는 상태는 아니야.',
            'result.YOUTH.DEP.GREEN.actions' => implode("\n", [
                '지금 유지하고 있는 생활습관을 계속하기',
                '기분이 좋아지는 활동을 일주일에 3회 이상 하기',
                '힘들 때 연락할 사람을 한 명 이상 정해두기',
                '한 달 후에 기분과 생활리듬을 다시 확인하기',
            ]),
            'result.YOUTH.DEP.YELLOW.meaning' =>
                '의욕이 떨어지고 즐거움이 줄어드는 신호가 나타나고 있어. 계속 혼자 참으면 생활리듬과 자신감이 더 떨어질 수 있어.',
            'result.YOUTH.DEP.YELLOW.actions' => implode("\n", [
                '하루에 해야 할 일을 한 가지로 줄여서 시작하기',
                '잠자리에서 생활하지 않고 일어나는 시간 정하기',
                '믿을 수 있는 어른이나 상담자에게 지금 상태를 말하기',
                '2주 동안 기분과 활동을 기록하기',
            ]),
            'result.YOUTH.DEP.RED.meaning' =>
                '마음의 에너지가 많이 떨어져 혼자 힘으로 일상을 유지하기 어려울 수 있어. 의지가 부족하거나 네가 게을러서가 아니야.',
            'result.YOUTH.DEP.RED.actions' => implode("\n", [
                '1주일 안에 전문상담 시작하기',
                '식사·수면·외출 같은 기본생활부터 회복하기',
                '하루 목표를 아주 작은 단위로 정하기',
                '죽고 싶은 생각이 들면 바로 상담자에게 알리기',
            ]),

            // ── ANX 불안·긴장 (003 Ⅵ.2) — 같은 형식으로 6건
            // ── IMP 분노·충동조절 (003 Ⅵ.3) — 6건
            // ── TRM 외상반응·안전감 (003 Ⅵ.4) — 6건
            // ── ISO 고립·대인관계 (003 Ⅵ.5) — 6건
            // ── FAM 가족·보호환경 (003 Ⅵ.6) — 6건
            // ── LIF 생활리듬·신체기능 (003 Ⅵ.7) — 6건
            // ── RSK 디지털·물질·위험행동 (003 Ⅵ.8) — 6건
            // ── FUT 미래희망·학업진로 (003 Ⅵ.9) — 6건
        ];
    }
```

**전사 규칙 3가지**
1. 003 Ⅵ 는 문어체("~한다")다. 청소년용은 005 의 반말체("~해", "~야")로 바꾼다. 위 DEP 예시가 그 변환의 기준이다.
2. 금지 표현을 쓰지 않는다. 003 원문의 "의지 부족이나 게으름으로 판단해서는 안 된다"는 그대로 옮기면 "게으르다"가 걸리므로, 위 예시처럼 **"네가 게을러서가 아니야"**로 바꾼다.
3. `actions` 항목은 문장 끝을 "~하기"로 통일한다.

- [ ] **Step 6: 보호자 요인 문안 81건 전사 (003 Ⅶ + 006)**

`guardianFactors()` 를 같은 방식으로 작성한다. 003 Ⅶ 의 불릿을 세 갈래로 나눈다:

- `meaning` — 현재 상태 설명 (006 5~13페이지의 해당 요인 서술)
- `actions` — "~한다 / ~를 확인한다 / ~를 연결한다" 형 지원행동
- `avoid` — "~하지 않는다 / ~를 피한다" 형. 003 Ⅶ 에 명시된 금지행동을 모은다

DEP 예시:

```php
    private function guardianFactors(): array
    {
        return [
            'result.GUARDIAN.DEP.GREEN.meaning' =>
                '현재 일상기능과 흥미가 비교적 유지되고 있습니다.',
            'result.GUARDIAN.DEP.GREEN.actions' => implode("\n", [
                '최근 학교중단, 관계상실 등 환경변화가 있었는지 관찰합니다.',
                '성취보다 노력과 생활유지를 인정해 주세요.',
            ]),
            'result.GUARDIAN.DEP.GREEN.avoid' =>
                '결과가 양호하다고 해서 대화를 줄이지 않습니다.',
            'result.GUARDIAN.DEP.YELLOW.meaning' =>
                '의욕과 흥미가 줄어드는 신호가 나타나고 있습니다. 수면·식사·외출·흥미활동의 변화를 함께 살펴볼 시점입니다.',
            'result.GUARDIAN.DEP.YELLOW.actions' => implode("\n", [
                '수면, 식사, 외출, 흥미활동의 변화를 확인합니다.',
                '해결책을 먼저 말하기보다 힘든 점을 먼저 듣습니다.',
                '1~2주 이내 상담 또는 면담을 권합니다.',
            ]),
            'result.GUARDIAN.DEP.YELLOW.avoid' => implode("\n", [
                '"마음먹으면 할 수 있다"는 말을 하지 않습니다.',
                '의지 문제로 돌리는 표현을 피합니다.',
            ]),
            'result.GUARDIAN.DEP.RED.meaning' =>
                '기능저하가 뚜렷해 전문적인 확인이 필요한 수준입니다. 훈육이나 의지만으로 해결하기 어렵습니다.',
            'result.GUARDIAN.DEP.RED.actions' => implode("\n", [
                '기능저하와 자살사고를 반드시 함께 확인합니다.',
                '전문상담 및 정신건강 평가를 연결합니다.',
                '기본생활을 도와주되 모든 일을 대신하지는 않습니다.',
            ]),
            'result.GUARDIAN.DEP.RED.avoid' => implode("\n", [
                '혼내거나 강제로 활동을 늘리지 않습니다.',
                '결과를 근거로 추궁하지 않습니다.',
            ]),

            // ── ANX·IMP·TRM·ISO·FAM·LIF·RSK·FUT 각 9건씩, 003 Ⅶ.2~9 + 006 6~13페이지
        ];
    }
```

- [ ] **Step 7: 안전·환경·종합·강점·솔루션·고지문 39건 작성**

```php
    private function safetyAndEnvironment(): array
    {
        return [
            'result.YOUTH.SAF.S0.safety_notice' => '',   // S0 는 안전 패널을 띄우지 않음 — 빈 문자열 금지, 아래 참고
            // ...
        ];
    }
```

**S0·E0 도 빈 문자열이 아니라 실제 문안을 넣는다**(빈 문안 테스트가 걸린다). S0 예시:

```php
'result.YOUTH.SAF.S0.safety_notice' =>
    '지금은 스스로를 해치고 싶은 마음이 뚜렷하게 나타나지 않았어. 그래도 힘들어지면 언제든 1388이나 109에 연락할 수 있어.',
'result.YOUTH.SAF.S1.safety_notice' =>
    '최근 사라지고 싶다는 생각이 조금 나타났어. 가능하면 오늘 안에 믿을 수 있는 어른이나 상담자에게 지금 마음을 이야기해 줘.',
'result.YOUTH.SAF.S2.safety_notice' =>
    '지금 많이 힘든 것 같아. 혼자 견디지 말고 오늘 안에 상담자와 이야기하자. 1388이나 109로 전화하면 바로 연결돼.',
'result.YOUTH.SAF.S3.safety_notice' =>
    '지금 바로 도움이 필요해 보여. 혼자 있지 말고 지금 109나 1388로 전화해 줘. 위급하면 112나 119에 연락해도 돼.',
```

`GUARDIAN` 쪽은 006 14페이지 기준 존댓말로, `ENV` 는 003 Ⅵ.4/Ⅵ.6/Ⅵ.8 의 빨강 대응과 006 5페이지 확인질문을 요약해 작성한다.

솔루션 `steps` 는 007 §10.1 제목 + 003 Ⅵ 의 해당 밴드 솔루션 항목을 줄바꿈으로 잇는다:

```php
    private function solutions(): array
    {
        return [
            'solution.SOL_DEP_ACTIVATION.steps' => implode("\n", [
                '오늘 할 일 한 가지만 정하기',
                '10분 밖에 나가기',
                '믿을 수 있는 사람에게 지금 상태 말하기',
            ]),
            'solution.SOL_SAF_PLAN.steps' => implode("\n", [
                '지금 연락할 수 있는 사람 한 명 적어두기',
                '위험한 물건을 손이 닿지 않는 곳에 두기',
                '109 또는 1388 번호를 휴대폰에 저장하기',
            ]),
            // ── 나머지 8종
        ];
    }

    private function disclaimers(): array
    {
        return [
            // 005 부록2 전문
            'disclaimer.YOUTH' => implode("\n", [
                '이 결과는 최근 마음상태와 생활기능을 확인하기 위한 선별자료이고, 정신질환을 진단하거나 치료를 결정하는 의료적 진단결과가 아니야.',
                '이 결과는 네가 답한 내용을 바탕으로 하니까, 실제 상황이나 상담·주변 환경과 함께 봐야 해.',
                '자해·자살, 폭력, 착취 위험이 있을 때는 점수와 상관없이 바로 전문가의 도움이 필요해.',
            ]),
            // 006 최종 고지문
            'disclaimer.GUARDIAN' => implode("\n", [
                '본 검사는 청소년의 현재 마음상태와 생활기능을 확인하기 위한 선별검사이며, 정신질환에 대한 의학적 진단을 제공하지 않습니다.',
                '검사결과는 자기보고, 면담, 행동관찰, 가족·생활환경 및 다른 평가자료와 함께 해석해야 합니다.',
                '자해·자살, 학대, 폭력, 온라인 착취, 급성중독 또는 주거위험이 확인되는 경우 검사 총점과 관계없이 즉각적인 안전평가와 보호조치가 필요합니다.',
            ]),
        ];
    }
}
```

- [ ] **Step 8: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=TemplateCompletenessTest`
Expected: PASS 8건. "총 174건" 테스트가 개수를 강제하므로 빠뜨린 키가 바로 드러난다.

- [ ] **Step 9: 커밋**

```bash
git add docs/source/oy-msi/ database/seeders/OyMsi/TemplateSeeder.php \
        tests/Feature/OyMsi/TemplateCompletenessTest.php
git commit -m "feat(oy-msi): 결과 문안 174건 시더 + 완결성·금지표현·반말체 검사"
```

---

## Task 16: 응시 화면 — 4점 척도 · 응답거부 · 안전 안내 모달

**Files:**
- Modify: `resources/views/assessment/take.blade.php`
- Create: `resources/js/oymsi-safety-alert.js` (또는 Blade 인라인 `<script>`)
- Test: `tests/Feature/OyMsi/TakeScreenTest.php`

**Interfaces:**
- Consumes: Task 2의 `test_items.options|scale_code|timeframe_code`, Task 11의 `AnswerValue::PREFER_NOT`
- Produces: 응시 화면이 문항별 `options` 로 보기를 렌더한다. SAF 문항에는 `data-item-code`·`data-safety` 속성이 붙어 JS가 등급을 계산한다.

**고쳐야 할 원본 버그:** `maybeShowImmediateAlert()` 의 중복방지 키가 **문항 단위**(`itemId:value`)인데 발동 조건은 **전역 안전등급**이다. 10번에서 S2가 되면 이후 모든 문항에서 모달이 다시 뜬다(최악 50회). 억제 키를 **"이미 보여준 최고 등급"** 으로 바꾼다.

- [ ] **Step 1: 화면 테스트 작성**

`tests/Feature/OyMsi/TakeScreenTest.php`:

```php
<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();

    $this->attempt = TestAttempt::create([
        'test_id' => $this->test->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
        'nickname' => '민수', 'gender' => 'male', 'age_at_test' => 16,
    ]);
    app(App\Services\OyMsi\ConsentGate::class)
        ->record($this->attempt, App\Services\OyMsi\ConsentGate::SENSITIVE, 'youth', $this->user->id);
});

test('4점 척도 보기 문구가 그대로 나온다', function () {
    $res = $this->actingAs($this->user)->get(route('assessment.take', ['OY_MSI', $this->attempt->id]));
    $res->assertOk();
    $res->assertSee('전혀 그렇지 않다');
    $res->assertSee('거의 항상 그렇다');
});

test('안전문항은 전용 척도 문구를 쓴다', function () {
    $res = $this->actingAs($this->user)->get(route('assessment.take', ['OY_MSI', $this->attempt->id]));
    $res->assertSee('자주 있었거나 지금도 그렇다');  // SAF_THOUGHT_4PT
    $res->assertSee('4회 이상 또는 최근 1개월 안에 있었다'); // SAF_BEHAVIOR_4PT
});

test('응답값이 0부터 시작한다', function () {
    $first = $this->test->items->firstWhere('item_code', 'DEP01');
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('name="answers['.$first->id.']" value="0"', false);
});

test('12개월 기준 문항에 기간 안내가 붙는다', function () {
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('최근 12개월');
});

test('응답거부 선택지가 제공된다', function () {
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('응답하기 어려움');
});

test('안전문항에 data-safety 속성이 붙는다', function () {
    $saf = $this->test->items->firstWhere('item_code', 'SAF04');
    $this->actingAs($this->user)
        ->get(route('assessment.take', ['OY_MSI', $this->attempt->id]))
        ->assertSee('data-item-code="SAF04"', false);
    expect($saf->area)->toBe('SAF');
});

test('기존 5점 검사 화면은 그대로 동작한다 (회귀)', function () {
    (new Database\Seeders\SampleTestSeeder())->run();
    $sample = Test::where('code', 'KMSIA-SAMPLE')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $sample->id, 'user_id' => $this->user->id,
        'status' => 'in_progress', 'started_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('assessment.take', ['KMSIA-SAMPLE', $attempt->id]))
        ->assertOk();
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=TakeScreenTest`
Expected: FAIL — 4점 보기 문구 없음

- [ ] **Step 3: take 뷰의 보기 렌더를 options 기반으로 교체**

`resources/views/assessment/take.blade.php` 의 문항 루프 안 보기 부분을 교체한다. 기존 `value="{{ $v }}"` 루프를 **문항별 `options`** 로 바꾸고, `options` 가 없으면 레거시 5점(1~5)을 유지한다:

```blade
@php
  $options = is_array($item->options) && count($item->options)
      ? collect($item->options)->map(fn ($label, $i) => ['value' => $i, 'label' => $label])->all()
      : collect(range(1, 5))->map(fn ($v) => ['value' => $v, 'label' => (string) $v])->all();
  $isSafety = $item->area === 'SAF';
@endphp

@if($item->timeframe_code === 'PAST_12_MONTHS')
  <p class="text-xs font-semibold text-amber-700 mb-2">최근 12개월 동안을 기준으로 답해 줘</p>
@elseif($item->timeframe_code === 'PAST_2_WEEKS')
  <p class="text-xs text-navy/45 mb-2">최근 2주 동안을 기준으로 답해 줘</p>
@endif

<div class="grid gap-2 {{ count($options) === 4 ? 'sm:grid-cols-4' : 'sm:grid-cols-5' }}">
  @foreach($options as $opt)
    <label class="cursor-pointer">
      <input type="radio"
             name="answers[{{ $item->id }}]"
             value="{{ $opt['value'] }}"
             class="peer sr-only js-answer"
             @if($isSafety) data-item-code="{{ $item->item_code }}" @endif
             required>
      <span class="block rounded-2xl bg-white p-3 text-center text-sm ring-1 ring-black/5
                   peer-checked:bg-deepgreen peer-checked:text-cream peer-checked:font-bold">
        {{ $opt['label'] }}
      </span>
    </label>
  @endforeach
</div>

@if($isSafety)
  <label class="mt-2 inline-flex items-center gap-2 cursor-pointer text-sm text-navy/55">
    <input type="radio" name="answers[{{ $item->id }}]" value="PREFER_NOT"
           class="js-answer" data-item-code="{{ $item->item_code }}">
    응답하기 어려움
  </label>
@endif
```

**`required` 주의:** `PREFER_NOT` 라디오는 같은 `name` 그룹이므로 하나만 고르면 `required` 가 충족된다.

- [ ] **Step 4: 안전 안내 모달 스크립트 추가**

`take.blade.php` 하단에 `@if($test->scoring_engine === 'oy_msi')` 로 감싸 삽입한다:

```blade
@if($test->scoring_engine === 'oy_msi')
<div id="safety-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl">
    <h2 id="safety-title" class="text-lg font-extrabold text-deepgreen"></h2>
    <p id="safety-body" class="mt-3 text-sm text-navy/75"></p>
    <div class="mt-5 grid gap-2">
      <a href="tel:109" class="rounded-xl bg-signal-red text-white py-3 text-center font-bold">109 자살예방 상담</a>
      <a href="tel:1388" class="rounded-xl bg-teal text-white py-3 text-center font-bold">1388 청소년 상담</a>
      <button type="button" id="safety-continue"
              class="rounded-xl bg-navy/5 py-3 font-semibold text-navy/70">검사 계속하기</button>
    </div>
  </div>
</div>

<script>
(function () {
  // 003 기준 안전등급 — scoring_rules.rules.safety 와 동일해야 한다.
  // 서버 채점이 최종 권위이고, 이 스크립트는 화면 안내 전용이다.
  var RULES = [
    { level: 3, conds: [['SAF03','=',3],['SAF04','>=',1],['SAF06','>=',1],
                        ['SAF01','=',3],['SAF02','=',3],['SAF05','>=',2]] },
    { level: 2, conds: [['SAF01','=',2],['SAF02','=',2],['SAF03','=',2]] },
    { level: 1, conds: [['SAF01','=',1],['SAF02','=',1],['SAF03','=',1],['SAF05','=',1]] }
  ];

  var answers = {};
  // ★ 원본 버그 수정: 억제 키가 "문항"이 아니라 "이미 보여준 최고 등급"이다.
  //   원본은 문항 단위 키라 S2 도달 후 남은 모든 문항에서 모달이 반복됐다.
  var shownLevel = 0;

  function level() {
    for (var i = 0; i < RULES.length; i++) {
      var r = RULES[i];
      for (var j = 0; j < r.conds.length; j++) {
        var c = r.conds[j], v = answers[c[0]];
        if (v === undefined || v === null) continue;
        if (c[1] === '=' ? v === c[2] : v >= c[2]) return r.level;
      }
    }
    // 안전문항 응답거부는 최소 S1
    for (var code in answers) {
      if (answers[code] === null) return Math.max(1, 0);
    }
    return 0;
  }

  var modal = document.getElementById('safety-modal');
  document.getElementById('safety-continue').addEventListener('click', function () {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  });

  document.querySelectorAll('.js-answer[data-item-code]').forEach(function (input) {
    input.addEventListener('change', function () {
      var code = input.dataset.itemCode;
      answers[code] = input.value === 'PREFER_NOT' ? null : parseInt(input.value, 10);

      var lv = level();
      if (lv < 2 || lv <= shownLevel) return;  // 등급이 올라갈 때만 표시
      shownLevel = lv;

      document.getElementById('safety-title').textContent =
        lv >= 3 ? '지금 바로 도움이 필요해 보여' : '지금 많이 힘든 것 같아';
      document.getElementById('safety-body').textContent =
        lv >= 3
          ? '혼자 있지 말고 지금 전화해 줘. 위급하면 112나 119에 연락해도 돼. 검사는 이어서 해도 괜찮아.'
          : '혼자 견디지 말고 오늘 안에 이야기하자. 검사는 이어서 해도 괜찮아.';

      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });
  });
})();
</script>
@endif
```

- [ ] **Step 5: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=TakeScreenTest`
Expected: PASS 7건

- [ ] **Step 6: 브라우저 수동 확인 (자동화 불가 부분)**

`export PATH="/c/xampp/php:$PATH" && php artisan serve` 후 실제로 응시해 확인한다:
- SAF01 에 "여러 번 있었다"(2) → 모달 1회 표시
- 이어서 11·12·13번 답변 → **모달이 다시 뜨지 않음** (원본 버그였던 부분)
- SAF04 에 "한두 번"(1) → 등급이 3으로 올라가며 모달 재표시
- "응답하기 어려움" 선택 후 제출 → 서버에서 `missing_code='PREFER_NOT'` 저장 확인

- [ ] **Step 7: 커밋**

```bash
git add resources/views/assessment/take.blade.php tests/Feature/OyMsi/TakeScreenTest.php
git commit -m "feat(oy-msi): 응시 화면 4점 척도·응답거부·안전 안내 모달 (반복 팝업 버그 수정)"
```

---

## Task 17: ReportComposer + 청소년용 결과 화면

**Files:**
- Create: `app/Services/OyMsi/ReportComposer.php`
- Create: `resources/views/oymsi/result.blade.php`
- Modify: `app/Http/Controllers/ResultController.php`
- Test: `tests/Feature/OyMsi/ResultScreenTest.php`

**Interfaces:**
- Consumes: Task 9의 `test_results.engine_result`, Task 15의 `interpretation_templates`
- Produces: `ReportComposer::compose(TestResult $result, string $audience): array` — 섹션 배열
  `[['type'=>'SAFETY_NOTICE'|'OVERALL'|'FACTORS'|'PRIORITY'|'STRENGTH'|'SOLUTIONS'|'RECHECK'|'DISCLAIMER', ...], ...]`
  `$audience` 는 `'YOUTH'` 또는 `'GUARDIAN'`.

**섹션 순서** (005 부록1 §2): 안전 → 종합 → 영역별 → 상위3 → 강점 → 실천 → 재검 → 고지문.
**SAF 요인 원점수는 어떤 audience 에도 노출하지 않는다.**

- [ ] **Step 1: 테스트 작성**

`tests/Feature/OyMsi/ResultScreenTest.php`:

```php
<?php
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\OyMsi\ReportComposer;
use App\Services\ScoringService;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TemplateSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    (new TemplateSeeder())->run();
    $this->test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $this->user = User::factory()->create();
});

function completedAttempt(array $overrides = [], ?User $user = null): TestAttempt
{
    $test = Test::where('code', 'OY_MSI')->with('items')->firstOrFail();
    $attempt = TestAttempt::create([
        'test_id' => $test->id, 'user_id' => $user?->id, 'guest_token' => $user ? null : 'g',
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        'nickname' => '민수', 'gender' => 'male', 'age_at_test' => 16,
        'assessment_version' => $test->assessment_version,
        'scoring_version' => $test->scoringRule->version,
    ]);
    foreach ($test->items as $item) {
        $raw = $overrides[$item->item_code] ?? 0;
        $attempt->answers()->create([
            'test_item_id' => $item->id,
            'value' => $raw, 'missing_code' => null,
        ]);
    }
    app(ScoringService::class)->score($attempt);
    return $attempt->fresh();
}

test('섹션 순서가 005 부록1 기준이다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3, 'SAF01' => 2]);
    $sections = app(ReportComposer::class)->compose($attempt->result, 'YOUTH');

    expect(array_column($sections, 'type'))->toBe([
        'SAFETY_NOTICE', 'OVERALL', 'FACTORS', 'PRIORITY', 'STRENGTH', 'SOLUTIONS', 'RECHECK', 'DISCLAIMER',
    ]);
});

test('S0·E0 이면 안전 섹션을 넣지 않는다', function () {
    $sections = app(ReportComposer::class)->compose(completedAttempt()->result, 'YOUTH');
    expect(array_column($sections, 'type'))->not->toContain('SAFETY_NOTICE');
});

test('상위 3영역에 문안이 채워진다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    $sections = collect(app(ReportComposer::class)->compose($attempt->result, 'YOUTH'))->keyBy('type');

    $priority = $sections['PRIORITY']['items'];
    expect($priority[0]['factor'])->toBe('DEP');
    expect($priority[0]['meaning'])->not->toBeEmpty();
    expect($priority[0]['actions'])->not->toBeEmpty();
});

test('강점이 최소 1개 들어간다', function () {
    $sections = collect(app(ReportComposer::class)->compose(completedAttempt()->result, 'YOUTH'))->keyBy('type');
    expect($sections['STRENGTH']['items'])->not->toBeEmpty();
});

test('보호자용에는 피해야 할 반응이 들어간다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3]);
    $sections = collect(app(ReportComposer::class)->compose($attempt->result, 'GUARDIAN'))->keyBy('type');
    expect($sections['PRIORITY']['items'][0]['avoid'])->not->toBeEmpty();
});

test('SAF 요인 점수는 어떤 대상에게도 노출되지 않는다', function () {
    $attempt = completedAttempt(['SAF01' => 3, 'SAF02' => 3, 'SAF03' => 3]);
    foreach (['YOUTH', 'GUARDIAN'] as $audience) {
        $sections = collect(app(ReportComposer::class)->compose($attempt->result, $audience))->keyBy('type');
        expect(array_column($sections['FACTORS']['items'], 'factor'))->not->toContain('SAF');
    }
});

test('결과 화면이 렌더된다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('민수')
        ->assertSee('1388');
});

test('안전등급 S2 이상이면 결과 화면에 연락처가 최상단에 뜬다', function () {
    $attempt = completedAttempt(['SAF01' => 2], $this->user);
    $this->actingAs($this->user)
        ->get(route('result.show', $attempt->id))
        ->assertOk()
        ->assertSee('109');
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ResultScreenTest`
Expected: FAIL — `ReportComposer` 없음

- [ ] **Step 3: ReportComposer 구현**

`app/Services/OyMsi/ReportComposer.php`:

```php
<?php
namespace App\Services\OyMsi;

use App\Models\InterpretationTemplate;
use App\Models\TestResult;

class ReportComposer
{
    private array $cache = [];

    /** @return list<array{type:string, ...}> */
    public function compose(TestResult $result, string $audience): array
    {
        $engine = $result->engine_result;
        $rules = $result->attempt->test->scoringRule->rules;
        $sections = [];

        // 1. 안전 — S1/E1 이상일 때만, 최상단
        $safety = $engine['safety']['suicide_level'];
        $environment = $engine['safety']['environment_level'];
        if ((int) substr($safety, 1) >= 1 || (int) substr($environment, 1) >= 1) {
            $sections[] = [
                'type' => 'SAFETY_NOTICE',
                'safety_level' => $safety,
                'environment_level' => $environment,
                'safety_text' => $this->text("result.{$audience}.SAF.{$safety}.safety_notice"),
                'environment_text' => $this->text("result.{$audience}.ENV.{$environment}.safety_notice"),
            ];
        }

        // 2. 종합
        $sections[] = [
            'type' => 'OVERALL',
            'band' => $engine['overall']['band'],
            'risk_index' => $engine['overall']['risk_index'],
            'final_case_code' => $engine['profile']['final_case_code'],
            'text' => $this->text("result.{$audience}.OVERALL.{$engine['overall']['band']}.meaning"),
        ];

        // 3. 영역별 (SAF 제외)
        $factorItems = [];
        foreach ($engine['factors'] as $code => $f) {
            if (!($rules['factors'][$code]['included_in_overall'] ?? false)) continue;
            $factorItems[] = [
                'factor' => $code,
                'name' => $rules['factors'][$code]['name'],
                'raw' => $f['raw'],
                'max' => 18,
                'risk_index' => $f['risk_index'],
                'band' => $f['band'],
                'score_status' => $f['score_status'],
            ];
        }
        $sections[] = ['type' => 'FACTORS', 'items' => $factorItems];

        // 4. 상위 3영역
        $priorityItems = [];
        foreach ($engine['priority'] as $row) {
            $f = $row['factor']; $b = $row['band'];
            $item = [
                'factor' => $f,
                'name' => $rules['factors'][$f]['name'],
                'band' => $b,
                'rank' => $row['rank'],
                'meaning' => $this->text("result.{$audience}.{$f}.{$b}.meaning"),
                'actions' => $this->lines("result.{$audience}.{$f}.{$b}.actions"),
            ];
            if ($audience === 'GUARDIAN') {
                $item['avoid'] = $this->lines("result.GUARDIAN.{$f}.{$b}.avoid");
            }
            $priorityItems[] = $item;
        }
        $sections[] = ['type' => 'PRIORITY', 'items' => $priorityItems];

        // 5. 강점
        $sections[] = [
            'type' => 'STRENGTH',
            'items' => array_map(fn ($c) => $this->text("strength.{$c}.text"), $engine['strengths']),
        ];

        // 6. 실천 솔루션
        $sections[] = [
            'type' => 'SOLUTIONS',
            'items' => array_map(fn ($c) => [
                'code' => $c,
                'title' => $rules['solutions'][$c]['title'],
                'steps' => $this->lines("solution.{$c}.steps"),
            ], $engine['solutions']),
        ];

        // 7. 재검
        $sections[] = ['type' => 'RECHECK'] + $engine['recheck'];

        // 8. 고지문
        $sections[] = ['type' => 'DISCLAIMER', 'lines' => $this->lines("disclaimer.{$audience}")];

        return $sections;
    }

    private function text(string $key): string
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = InterpretationTemplate::where('template_key', $key)
                ->where('active', true)->value('text') ?? '';
        }
        return $this->cache[$key];
    }

    /** @return list<string> */
    private function lines(string $key): array
    {
        $text = $this->text($key);
        return $text === '' ? [] : array_values(array_filter(explode("\n", $text)));
    }
}
```

- [ ] **Step 4: ResultController 분기**

`app/Http/Controllers/ResultController.php` 의 `show()` 에서 `scoring_engine` 이 `oy_msi` 면 전용 뷰로 보낸다:

```php
        if ($attempt->test->scoring_engine === 'oy_msi') {
            return view('oymsi.result', [
                'attempt' => $attempt,
                'result' => $attempt->result,
                'sections' => app(\App\Services\OyMsi\ReportComposer::class)
                    ->compose($attempt->result, 'YOUTH'),
                'audience' => 'YOUTH',
            ]);
        }
```

- [ ] **Step 5: 결과 뷰 작성**

`resources/views/oymsi/result.blade.php` — `$sections` 를 순서대로 렌더한다. 핵심 구조만:

```blade
<x-layouts.app :title="'검사 결과 · '.$attempt->test->title_easy">
<div class="bg-cream min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-10 space-y-6">
    <h1 class="text-2xl font-extrabold text-deepgreen">{{ $attempt->nickname }}의 마음상태</h1>

    @foreach($sections as $s)
      @if($s['type'] === 'SAFETY_NOTICE')
        <section class="rounded-3xl bg-red-50 ring-2 ring-signal-red/40 p-6">
          <p class="text-sm text-navy/80">{{ $s['safety_text'] }}</p>
          @if($s['environment_text'])<p class="mt-2 text-sm text-navy/80">{{ $s['environment_text'] }}</p>@endif
          <div class="mt-4 grid grid-cols-2 gap-2">
            <a href="tel:109" class="rounded-xl bg-signal-red text-white py-3 text-center font-bold">109</a>
            <a href="tel:1388" class="rounded-xl bg-teal text-white py-3 text-center font-bold">1388</a>
            <a href="tel:112" class="rounded-xl bg-navy/10 py-3 text-center font-semibold">112</a>
            <a href="tel:119" class="rounded-xl bg-navy/10 py-3 text-center font-semibold">119</a>
          </div>
        </section>

      @elseif($s['type'] === 'OVERALL')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <p class="text-sm text-navy/50">종합 마음상태</p>
          <p class="mt-1 text-3xl font-extrabold
             {{ ['GREEN'=>'text-signal-green','YELLOW'=>'text-signal-yellow','RED'=>'text-signal-red'][$s['band']] }}">
            {{ ['GREEN'=>'초록','YELLOW'=>'노랑','RED'=>'빨강'][$s['band']] }}
          </p>
          <p class="text-sm text-navy/50">전체 위험지수 {{ $s['risk_index'] }}점</p>
          <p class="mt-3 text-navy/80">{{ $s['text'] }}</p>
        </section>

      @elseif($s['type'] === 'FACTORS')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <h2 class="font-bold text-deepgreen">영역별 마음상태</h2>
          <div class="mt-4 space-y-3">
            @foreach($s['items'] as $f)
              <div>
                <div class="flex justify-between text-sm">
                  <span>{{ $f['name'] }}</span>
                  <span class="text-navy/50">{{ $f['raw'] }}/{{ $f['max'] }}</span>
                </div>
                <div class="mt-1 h-2 rounded-full bg-navy/10">
                  <div class="h-2 rounded-full
                       {{ ['GREEN'=>'bg-signal-green','YELLOW'=>'bg-signal-yellow','RED'=>'bg-signal-red'][$f['band']] ?? 'bg-navy/20' }}"
                       style="width: {{ $f['risk_index'] ?? 0 }}%"></div>
                </div>
              </div>
            @endforeach
          </div>
        </section>

      @elseif($s['type'] === 'PRIORITY')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5 space-y-5">
          <h2 class="font-bold text-deepgreen">지금 먼저 살펴볼 3가지</h2>
          @foreach($s['items'] as $p)
            <div>
              <p class="font-bold">{{ $p['rank'] }}. {{ $p['name'] }}</p>
              <p class="mt-1 text-sm text-navy/75">{{ $p['meaning'] }}</p>
              <ul class="mt-2 list-disc pl-5 text-sm text-navy/70">
                @foreach($p['actions'] as $a)<li>{{ $a }}</li>@endforeach
              </ul>
              @if(!empty($p['avoid']))
                <p class="mt-2 text-sm font-semibold text-signal-red">피해야 할 반응</p>
                <ul class="list-disc pl-5 text-sm text-navy/70">
                  @foreach($p['avoid'] as $a)<li>{{ $a }}</li>@endforeach
                </ul>
              @endif
            </div>
          @endforeach
        </section>

      @elseif($s['type'] === 'STRENGTH')
        <section class="rounded-3xl bg-mint/20 p-6">
          <h2 class="font-bold text-deepgreen">나에게 남아 있는 강점</h2>
          <ul class="mt-2 list-disc pl-5 text-sm text-navy/75">
            @foreach($s['items'] as $t)<li>{{ $t }}</li>@endforeach
          </ul>
        </section>

      @elseif($s['type'] === 'SOLUTIONS')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <h2 class="font-bold text-deepgreen">이번 주 작은 실천</h2>
          @foreach($s['items'] as $sol)
            <p class="mt-3 font-semibold text-sm">{{ $sol['title'] }}</p>
            <ul class="list-disc pl-5 text-sm text-navy/70">
              @foreach($sol['steps'] as $step)<li>{{ $step }}</li>@endforeach
            </ul>
          @endforeach
        </section>

      @elseif($s['type'] === 'RECHECK')
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
          <h2 class="font-bold text-deepgreen">다시 확인할 시점</h2>
          <p class="mt-1 text-sm text-navy/70">{{ $s['days'] }}일 뒤에 다시 해보면 변화를 확인할 수 있어.</p>
        </section>

      @elseif($s['type'] === 'DISCLAIMER')
        <section class="text-xs text-navy/50 leading-relaxed">
          @foreach($s['lines'] as $line)<p>{{ $line }}</p>@endforeach
        </section>
      @endif
    @endforeach

    <div class="grid grid-cols-2 gap-2 print:hidden">
      <button onclick="window.print()" class="rounded-xl bg-navy/10 py-3 font-semibold">인쇄하기</button>
      <a href="{{ route('oymsi.share.form', $attempt->id) }}"
         class="rounded-xl bg-deepgreen text-cream py-3 text-center font-bold">보호자와 공유하기</a>
    </div>
  </div>
</div>
</x-layouts.app>
```

- [ ] **Step 6: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ResultScreenTest`
Expected: PASS 8건.
`oymsi.share.form` 라우트는 Task 18 에서 만든다 — 그 전까지는 뷰의 해당 `<a>` 를 `href="#"` 로 두고 Task 18 에서 되돌린다.

- [ ] **Step 7: 커밋**

```bash
git add app/Services/OyMsi/ReportComposer.php resources/views/oymsi/result.blade.php \
        app/Http/Controllers/ResultController.php tests/Feature/OyMsi/ResultScreenTest.php
git commit -m "feat(oy-msi): ReportComposer + 청소년용 결과 화면 (005 부록1 표시 순서)"
```

---

## Task 18: 보호자용 결과 공유

**Files:**
- Create: `app/Http/Controllers/OyMsi/ShareController.php`
- Create: `resources/views/oymsi/share-form.blade.php`
- Create: `resources/views/oymsi/share-created.blade.php`
- Create: `resources/views/oymsi/guardian-result.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OyMsi/ShareTest.php`

**Interfaces:**
- Consumes: Task 1의 `report_shares`, Task 17의 `ReportComposer`
- Produces:
  - 라우트 `GET /result/{attempt}/share` → `oymsi.share.form`, `POST` → `oymsi.share.create`
  - 라우트 `GET /r/{token}` → `oymsi.share.view` (로그인 불필요)
  - `POST /result/{attempt}/share/revoke` → `oymsi.share.revoke`

**S2 이상 분기** (spec §5.3): 공유 버튼 대신 연결 안내를 먼저 띄우고, 공유는 눈에 덜 띄는 2차 선택으로 둔다.

- [ ] **Step 1: 테스트 작성**

`tests/Feature/OyMsi/ShareTest.php`:

```php
<?php
use App\Models\ReportShare;
use App\Models\User;
use Database\Seeders\OyMsi\ScoringRuleSeeder;
use Database\Seeders\OyMsi\TemplateSeeder;
use Database\Seeders\OyMsi\TestSeeder;

beforeEach(function () {
    (new TestSeeder())->run();
    (new ScoringRuleSeeder())->run();
    (new TemplateSeeder())->run();
    $this->user = User::factory()->create();
});
// completedAttempt() 는 ResultScreenTest.php 에 정의된 헬퍼와 동일한 것을 쓴다.
// Pest 전역 함수 충돌을 피하려면 tests/Pest.php 로 옮기고 양쪽에서 참조한다.

test('S0 이면 공유 링크가 바로 만들어진다', function () {
    $attempt = completedAttempt([], $this->user);

    $this->actingAs($this->user)
        ->post(route('oymsi.share.create', $attempt->id))
        ->assertOk()
        ->assertSee('/r/');

    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();
    expect($share->audience)->toBe('guardian');
    expect($share->source)->toBe('youth_self');
    expect($share->expires_at->isFuture())->toBeTrue();
});

test('S2 이상이면 공유 화면이 연결 안내를 먼저 보여준다', function () {
    $attempt = completedAttempt(['SAF01' => 2], $this->user);

    $this->actingAs($this->user)
        ->get(route('oymsi.share.form', $attempt->id))
        ->assertOk()
        ->assertSee('먼저 이야기할 사람')
        ->assertSee('109');
});

test('공유 링크로 로그인 없이 보호자용 결과를 본다', function () {
    $attempt = completedAttempt(['DEP01' => 3, 'DEP02' => 3, 'DEP03' => 3, 'DEP04' => 3, 'DEP05' => 3, 'DEP06' => 3], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->post(route('logout'));
    $res = $this->get(route('oymsi.share.view', $share->token));

    $res->assertOk();
    $res->assertSee('피해야 할 반응');
    expect($share->fresh()->viewed_at)->not->toBeNull();
});

test('보호자용에는 자해·자살 문항별 응답이 없다', function () {
    $attempt = completedAttempt(['SAF01' => 3, 'SAF03' => 3], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $html = $this->get(route('oymsi.share.view', $share->token))->getContent();
    expect($html)->not->toContain('SAF01');
    expect($html)->not->toContain('SAF03');
    expect($html)->not->toContain('목숨을 끊');
});

test('철회한 링크는 열리지 않는다', function () {
    $attempt = completedAttempt([], $this->user);
    $this->actingAs($this->user)->post(route('oymsi.share.create', $attempt->id));
    $share = ReportShare::where('attempt_id', $attempt->id)->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('oymsi.share.revoke', $attempt->id))
        ->assertRedirect();

    $this->get(route('oymsi.share.view', $share->token))->assertNotFound();
});

test('만료된 링크는 열리지 않는다', function () {
    $attempt = completedAttempt([], $this->user);
    $share = ReportShare::create([
        'attempt_id' => $attempt->id, 'audience' => 'guardian',
        'token' => 'expiredtoken', 'source' => 'youth_self',
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('oymsi.share.view', $share->token))->assertNotFound();
});

test('남의 결과를 공유할 수 없다', function () {
    $attempt = completedAttempt([], $this->user);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->post(route('oymsi.share.create', $attempt->id))
        ->assertForbidden();
});
```

- [ ] **Step 2: 실패 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ShareTest`
Expected: FAIL — 라우트 없음

- [ ] **Step 3: 컨트롤러 작성**

`app/Http/Controllers/OyMsi/ShareController.php`:

```php
<?php
namespace App\Http\Controllers\OyMsi;

use App\Http\Controllers\Controller;
use App\Models\ReportShare;
use App\Models\TestAttempt;
use App\Services\OyMsi\ReportComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    private const EXPIRES_DAYS = 30;

    public function form(Request $request, TestAttempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);
        $engine = $attempt->result->engine_result;

        return view('oymsi.share-form', [
            'attempt' => $attempt,
            'needsContactFirst' => $this->needsContactFirst($engine),
            'existing' => $attempt->shares()->whereNull('revoked_at')->latest('id')->first(),
        ]);
    }

    public function create(Request $request, TestAttempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);

        $share = ReportShare::create([
            'attempt_id' => $attempt->id,
            'audience' => 'guardian',
            'token' => Str::random(48),
            'source' => 'youth_self',
            'created_by' => auth()->id(),
            'expires_at' => now()->addDays(self::EXPIRES_DAYS),
        ]);

        return response()->view('oymsi.share-created', [
            'attempt' => $attempt,
            'url' => route('oymsi.share.view', $share->token),
            'expiresAt' => $share->expires_at,
        ]);
    }

    public function revoke(Request $request, TestAttempt $attempt)
    {
        $this->authorizeOwner($request, $attempt);
        $attempt->shares()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return redirect()->route('result.show', $attempt->id);
    }

    /** 로그인 불필요 */
    public function view(string $token, ReportComposer $composer)
    {
        $share = ReportShare::where('token', $token)->first();
        abort_unless($share && $share->isUsable(), 404);

        $share->update(['viewed_at' => $share->viewed_at ?? now()]);
        $attempt = $share->attempt()->with('result', 'test.scoringRule')->firstOrFail();

        return view('oymsi.guardian-result', [
            'attempt' => $attempt,
            'sections' => $composer->compose($attempt->result, 'GUARDIAN'),
        ]);
    }

    private function authorizeOwner(Request $request, TestAttempt $attempt): void
    {
        abort_unless($attempt->isOwnedBy($request), 403);
        abort_unless($attempt->result, 404);
    }

    private function needsContactFirst(array $engine): bool
    {
        return max(
            (int) substr($engine['safety']['suicide_level'], 1),
            (int) substr($engine['safety']['environment_level'], 1)
        ) >= 2;
    }
}
```

- [ ] **Step 4: 라우트 등록**

`routes/web.php`:

```php
use App\Http\Controllers\OyMsi\ShareController;

Route::middleware('auth')->controller(ShareController::class)
    ->prefix('result/{attempt}/share')->name('oymsi.share.')->group(function () {
        Route::get('/', 'form')->name('form');
        Route::post('/', 'create')->name('create');
        Route::post('revoke', 'revoke')->name('revoke');
    });

Route::get('/r/{token}', [ShareController::class, 'view'])->name('oymsi.share.view');
```

- [ ] **Step 5: 뷰 3개 작성**

`resources/views/oymsi/share-form.blade.php` — S2 이상이면 연결 안내가 먼저:

```blade
<x-layouts.app title="보호자와 공유">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 py-12">
      @if($needsContactFirst)
        <h1 class="text-2xl font-extrabold text-deepgreen">지금은 먼저 이야기할 사람이 필요해 보여</h1>
        <p class="mt-3 text-sm text-navy/70">
          결과를 보호자와 나누는 것보다, 지금 상담자와 이야기하는 게 먼저야.
        </p>
        <div class="mt-6 grid gap-2">
          <a href="tel:109" class="rounded-xl bg-signal-red text-white py-4 text-center font-bold">109 자살예방 상담</a>
          <a href="tel:1388" class="rounded-xl bg-teal text-white py-4 text-center font-bold">1388 청소년 상담</a>
        </div>
        <form method="POST" action="{{ route('oymsi.share.create', $attempt->id) }}" class="mt-8">
          @csrf
          <button class="text-sm text-navy/45 underline">그래도 보호자와 공유할래</button>
        </form>
      @else
        <h1 class="text-2xl font-extrabold text-deepgreen">보호자와 공유할까?</h1>
        <p class="mt-3 text-sm text-navy/70">
          공유하면 보호자가 결과 요약과 도와줄 방법을 볼 수 있어.
          네가 어떻게 답했는지 문항별 내용은 보이지 않아. 언제든 공유를 취소할 수 있어.
        </p>
        <form method="POST" action="{{ route('oymsi.share.create', $attempt->id) }}" class="mt-8">
          @csrf
          <button class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold">공유 링크 만들기</button>
        </form>
        <a href="{{ route('result.show', $attempt->id) }}"
           class="mt-4 block text-center text-sm text-navy/50 underline">지금은 안 할래</a>
      @endif
    </div>
  </div>
</x-layouts.app>
```

`resources/views/oymsi/share-created.blade.php` — 링크와 만료일, 복사 버튼, 철회 폼.
`resources/views/oymsi/guardian-result.blade.php` — `oymsi/result.blade.php` 와 같은 섹션 렌더 구조를 쓰되 존댓말 헤더("자녀의 마음상태")로 하고 **공유·인쇄 버튼 중 공유 버튼은 뺀다.**

- [ ] **Step 6: Task 17 의 임시 링크 되돌리기**

`resources/views/oymsi/result.blade.php` 의 `href="#"` 를 `href="{{ route('oymsi.share.form', $attempt->id) }}"` 로 되돌린다.

- [ ] **Step 7: 통과 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test --filter=ShareTest`
Expected: PASS 7건

- [ ] **Step 8: 전체 테스트**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 전부 PASS

- [ ] **Step 9: 커밋**

```bash
git add app/Http/Controllers/OyMsi/ShareController.php resources/views/oymsi/ \
        routes/web.php tests/Feature/OyMsi/ShareTest.php
git commit -m "feat(oy-msi): 보호자용 결과 공유 — 청소년 선택 발급·30일 만료·철회, S2 이상은 연결 우선"
```

---

## Task 19: 1단계 완료 확인

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php` (OyMsi 시더 3종 등록)
- Test: 전체

- [ ] **Step 1: 시더 등록**

`database/seeders/DatabaseSeeder.php` 의 `run()` 에 추가:

```php
        $this->call([
            \Database\Seeders\OyMsi\TestSeeder::class,
            \Database\Seeders\OyMsi\ScoringRuleSeeder::class,
            \Database\Seeders\OyMsi\TemplateSeeder::class,
        ]);
```

- [ ] **Step 2: 전체 테스트**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan test`
Expected: 전부 PASS. 기존 55 + OyMsi 신규 약 90건.

- [ ] **Step 3: 비공개 상태 확인**

Run: `export PATH="/c/xampp/php:$PATH" && php artisan tinker --execute="echo App\Models\Test::where('code','OY_MSI')->value('status');"`
Expected: `draft` — **`active` 가 아니어야 한다.** 1단계는 내부·시연용이다.

- [ ] **Step 4: 수동 시나리오 확인**

`php artisan serve` 후 다음을 직접 해본다.

| 시나리오 | 확인할 것 |
|---|---|
| 개인 · 만 16세 · 전부 0 | G0/S0/E0, 안전 패널 없음, 강점 1개 이상, 재검 90일 |
| 개인 · 만 13세 | 기관 안내 화면 + 1388 |
| 개인 · SAF01 에 "여러 번"(2) | 응시 중 모달 1회 → **이후 문항에서 재표시 안 됨** |
| 개인 · SAF04 에 "한두 번"(1) | 등급 상승으로 모달 재표시, 결과 C3 |
| 개인 · DEP 전부 3 | DEP RED, 상위 1순위 DEP, 솔루션에 행동활성화 |
| 개인 · SAF 문항 "응답하기 어려움" | 제출 성공, 안전등급 S1 |
| 공유 · S0 | 링크 생성 → 로그아웃 상태에서 열람 → 피해야 할 반응 표시 |
| 공유 · S2 | 109/1388 먼저, 공유는 작은 링크 |
| 링크 · 만 13세 · 담당자 확인 없음 | 담당자 문의 안내 |
| 기존 5점 샘플 검사 | 결과가 이전과 동일 |

- [ ] **Step 5: 커밋**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "chore(oy-msi): 시더 등록 — 1단계 완료 (status=draft 유지, 공개 오픈은 2단계 게이트 통과 후)"
```

---

## 부록 A. Spec 대비 커버리지

| Spec 항목 | Task |
|---|---|
| §1.3 문항 배치 교정 | 2 |
| §2.1 기존 테이블 확장 | 1 |
| §2.2 신규 테이블 3개 | 1 |
| §2.3 규칙 데이터화 | 3 |
| §3.1 엔진 디스패처 | 4 |
| §3.2 소단위 8개 | 5·6·7·8 |
| §3.3 0점 거부 버그 | 11 |
| §3.3 안전 모달 반복 | 16 |
| §3.3 shownAlerts 미초기화 | 16 |
| §3.3 priority weight | 7 |
| §3.3 factorScores count | 5 |
| §3.3 E1 코드 가림 | 1·9 (general/final 병기) |
| §3.3 문항 번호 이중화 | 2 |
| §3.3 SAF 미표시 | 17 |
| §3.4 응시 중 안내 | 16 |
| §4.1 동의 우회 차단 | 12 |
| §4.2 개인 경로 | 13·14 |
| §4.3 기관 링크 경로 | 13·14 |
| §5.1 청소년 결과 | 17 |
| §5.2 금지 표현 | 15 |
| §5.3 보호자 공유 | 18 |
| §5.4 재열람 | 17·18 |
| §6 테스트 1 경계값 | 5·6·7 |
| §6 테스트 2 JS 0 diff | 10 |
| §6 테스트 3 문항 무결성 | 2 |
| §6 테스트 3b 배치 규칙 | 2 |
| §6 테스트 4 문안 누락 | 15 |
| §6 테스트 5 금지 표현 | 15 |
| §6 테스트 6 동의 우회 | 12 |
| §6 테스트 7 연령 분기 | 13 |
| §6 테스트 8 회귀 | 1·4·11·12·14·16 전 태스크 |
| §7 완료 정의 | 19 |

§8(2단계 게이트)·§9(비범위)는 이번 계획 범위 밖이다.

## 부록 B. 주의할 함정

1. **`SampleTestSeeder` 회귀** — 5점 척도 검사가 계속 1~5 를 받아야 한다. Task 11 의 회귀 테스트 2건이 이걸 지킨다.
2. **`GuardianConsentTest`** — 기존 `requires_guardian_consent` 흐름을 Task 12 에서 건드린다. 깨지면 새 `consent_required` 와 혼선이 난 것이다. 둘은 별개 컬럼이다.
3. **Pest 전역 함수 충돌** — `completedAttempt()`·`saf()`·`depScores()` 같은 헬퍼를 여러 테스트 파일에 중복 정의하면 "Cannot redeclare" 로 죽는다. 두 번째 파일에서 쓰는 순간 `tests/Pest.php` 로 옮긴다.
4. **`round()` 부동소수** — 위험지수는 `round($x, 1)`. PHP 와 JS 의 반올림이 `.05` 경계에서 갈릴 수 있어 Task 10 대조는 `0.05` 허용오차를 둔다.
5. **`attempt_answers.value` nullable 변경** — SQLite 는 테이블 재작성으로 처리한다. 로컬에서 `migrate:fresh` 로 확인하고, 운영 MySQL 반영 시 별도 점검한다.
6. **문안 전사량** — Task 15 가 174건이라 가장 오래 걸린다. 개수 테스트가 강제하므로 나눠서 하되 커밋은 한 번에 한다.
7. **`status='draft'`** — 어떤 Task 에서도 `active` 로 올리지 않는다. 올리는 순간 003 이 금지한 "담당자 없는 환경에서의 시행"이 된다.
