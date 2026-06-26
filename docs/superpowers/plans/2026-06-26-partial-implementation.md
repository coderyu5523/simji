# 심지 부분 구현 4종 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 개발안 대비 "부분 구현(⚠️)" 상태였던 5개 연령방·준비중 검사카드·보호자 동의 분기·강의코칭 소개 페이지를 흐름/화면 수준까지 완성한다.

**Architecture:** 기존 Laravel + Blade 구조를 따른다. 연령방은 `App\Support\Rooms` 정적 배열이 단일 출처이며 홈·카탈로그가 이를 순회 렌더하므로 방 추가는 자동 전파된다. 검사 콘텐츠는 0종 유지하고 방별 "준비중" 카드는 `Rooms`의 `planned_tests` 정적 데이터로 렌더한다. 보호자 동의는 `tests.requires_guardian_consent` 플래그로 consent 화면을 분기한다. 강의·코칭은 정적 컨트롤러+뷰.

**Tech Stack:** PHP 8 / Laravel, Blade, Tailwind, Pest(Feature 테스트), SQLite(in-memory 테스트).

## Global Constraints

- 실제 임상 검사 문항 작성 금지. 방별 검사는 "준비중" 비활성 카드만(응시 불가).
- `<?=` 단축태그 금지(Blade 풀 태그/`{{ }}`만). 운영 서버 short_open_tag=Off 가능성.
- 기존 검사 응시→결과 플로우(어른 검사) 회귀 없음. 어른 검사 consent 화면은 변화 없어야 함.
- 신고의무 고지 문구는 **임시(placeholder)** — 코드에 `{{-- TODO: 문구·연계절차 추후 협의 --}}` 주석 필수.
- 연령방 순서: 초등학생(elem) → 중고등학생(middle) → 대학생(univ) → 직장인·성인(worker) → 실버(silver).
- 테스트 실행: `php artisan test --filter=<name>` 또는 `./vendor/bin/pest`.

---

## File Structure

- `app/Support/Rooms.php` — 수정: elem/middle 방 + 전 방 `planned_tests` 추가 (Task A, B)
- `public/images/rooms/elem.png`, `middle.png` — 생성: 방 썸네일(플레이스홀더) (Task A)
- `scripts/gen-images.php` — 수정: elem/middle job 추가 (Task A)
- `resources/views/home.blade.php` — 수정: 카피 보정 (Task A)
- `resources/views/catalog/room.blade.php` — 수정: 준비중 카드 렌더 (Task B)
- `database/migrations/XXXX_add_requires_guardian_consent_to_tests.php` — 생성 (Task C)
- `app/Models/Test.php` — 수정: fillable/cast (Task C)
- `resources/views/assessment/consent.blade.php` — 수정: 보호자 분기 (Task C)
- `app/Http/Controllers/AssessmentController.php` — 수정: agree 검증 (Task C)
- `routes/web.php` — 수정: coaching 라우트 (Task D)
- `app/Http/Controllers/CoachingController.php` — 생성 (Task D)
- `resources/views/coaching/index.blade.php` — 생성 (Task D)
- 테스트: `tests/Feature/HomeTest.php`, `CatalogTest.php`(수정), `tests/Feature/GuardianConsentTest.php`, `CoachingTest.php`(생성)

---

## Task A: 5개 연령방 완성

**Files:**
- Modify: `app/Support/Rooms.php`
- Modify: `resources/views/home.blade.php`
- Modify: `scripts/gen-images.php`
- Create: `public/images/rooms/elem.png`, `public/images/rooms/middle.png`
- Test: `tests/Feature/HomeTest.php` (수정)

**Interfaces:**
- Produces: `Rooms::all()` 가 5개 방을 순서대로 반환. 각 방은 `['code','name','desc','tags']` 키 보유(planned_tests는 Task B에서 추가). code 값: `elem, middle, univ, worker, silver`.

- [ ] **Step 1: HomeTest 갱신 (실패 테스트)**

`tests/Feature/HomeTest.php` 를 다음으로 교체:

```php
<?php
test('home shows all five rooms and updated copy', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $this->get('/')
        ->assertOk()
        ->assertSee('마음을 검사하고')
        ->assertSee('초등학생')->assertSee('중고등학생')
        ->assertSee('대학생')->assertSee('직장인·성인')->assertSee('실버')
        ->assertSee('초등학생부터 실버');
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=HomeTest`
Expected: FAIL — '초등학생' 미노출(방 없음) / '초등학생부터 실버' 카피 없음.

- [ ] **Step 3: Rooms.php에 elem/middle 추가**

`app/Support/Rooms.php` 의 `all()` 배열 맨 앞에 두 방을 추가하고 순서를 맞춘다(최종 순서 elem→middle→univ→worker→silver):

```php
public static function all(): array
{
    return [
        ['code' => 'elem',   'name' => '초등학생',    'desc' => '안전하고 밝은 방, 마음을 살피는 첫걸음', 'tags' => ['불안','우울','주의집중','또래관계','스마트폰']],
        ['code' => 'middle', 'name' => '중고등학생',  'desc' => '진로와 감정 사이, 흔들리는 마음 다잡기', 'tags' => ['스트레스','시험불안','진로','대인관계','자기조절']],
        ['code' => 'univ',   'name' => '대학생',     'desc' => '진로와 관계 사이에서 나를 찾는 시기', 'tags' => ['우울','불안','스트레스','진로','대인관계','자기조절']],
        ['code' => 'worker', 'name' => '직장인·성인', 'desc' => '회복과 성과 사이, 단단한 마음 만들기', 'tags' => ['번아웃','직무스트레스','분노','회복탄력성','마음상태']],
        ['code' => 'silver', 'name' => '실버',       'desc' => '존엄과 활력의 시기, 마음을 돌보기',   'tags' => ['우울','고독감','인지건강','삶의만족도']],
    ];
}
```

- [ ] **Step 4: 홈 카피 보정**

`resources/views/home.blade.php`:
- Line 11 영역 `대학생부터 실버 세대까지` → `초등학생부터 실버 세대까지` 로 변경.
- Line 34 가치밴드 배열의 `'d'=>'대학생·직장인·실버 맞춤 구성'` → `'d'=>'초등학생부터 실버까지 맞춤 구성'` 로 변경.

```php
// home.blade.php hero <p> 내부
대학생부터 실버 세대까지, 연령별 마음상태를 과학적으로 확인하고
// ↓ 변경
초등학생부터 실버 세대까지, 연령별 마음상태를 과학적으로 확인하고
```

```php
['t'=>'연령별 마음방','d'=>'초등학생부터 실버까지 맞춤 구성','p'=>'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6'],
```

- [ ] **Step 5: 방 썸네일 플레이스홀더 PNG 생성**

API 키 없이도 깨지지 않도록 GD로 그라데이션 플레이스홀더를 생성한다. 다음을 1회 실행:

```bash
php -r '
foreach (["elem"=>"초등학생","middle"=>"중고등학생"] as $code=>$label) {
  $w=800;$h=600;$im=imagecreatetruecolor($w,$h);
  // deep green -> teal 세로 그라데이션
  for($y=0;$y<$h;$y++){ $t=$y/$h;
    $r=(int)(31+(46-31)*$t); $g=(int)(77+(125-77)*$t); $b=(int)(63+(107-63)*$t);
    imagefilledrectangle($im,0,$y,$w,$y,imagecolorallocate($im,$r,$g,$b));
  }
  imagepng($im, __DIR__."/public/images/rooms/$code.png");
  imagedestroy($im);
  echo "wrote $code.png\n";
}'
```

> 주의: 한글 텍스트는 GD 기본폰트 미지원이라 넣지 않음(그라데이션만). 실제 일러스트는 추후 `gen-images.php`로 교체.

- [ ] **Step 6: gen-images.php에 job 추가(추후 실제 생성용)**

`scripts/gen-images.php` 의 `$jobs` 배열에 추가:

```php
    'rooms/elem.png'   => "$base $styleHint An elementary school child, safe and bright, gentle and reassuring.",
    'rooms/middle.png' => "$base $styleHint A teenage student, navigating emotions and future paths, hopeful.",
```

- [ ] **Step 7: 테스트 통과 확인**

Run: `php artisan test --filter=HomeTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Support/Rooms.php resources/views/home.blade.php scripts/gen-images.php public/images/rooms/elem.png public/images/rooms/middle.png tests/Feature/HomeTest.php
git commit -m "feat: 초등·중고등 연령방 추가로 5개 마음방 완성"
```

---

## Task B: 방별 "준비중" 검사 카드

**Files:**
- Modify: `app/Support/Rooms.php` (planned_tests 추가)
- Modify: `resources/views/catalog/room.blade.php`
- Test: `tests/Feature/CatalogTest.php` (수정)

**Interfaces:**
- Consumes: Task A의 `Rooms::all()` 5개 방.
- Produces: 각 방 배열에 `planned_tests` 키 추가 — `array<int, ['name'=>string,'target'=>string,'guardian'=>bool]>`. `Rooms::find($code)` 반환값에도 포함됨.

- [ ] **Step 1: CatalogTest 갱신 (실패 테스트)**

`tests/Feature/CatalogTest.php` 를 다음으로 교체:

```php
<?php
beforeEach(fn() => $this->seed(\Database\Seeders\SampleTestSeeder::class));

test('catalog index shows all five rooms', function () {
    $this->get('/tests')->assertOk()
        ->assertSee('초등학생')->assertSee('중고등학생')
        ->assertSee('대학생')->assertSee('직장인·성인')->assertSee('실버');
});
test('elem room shows coming-soon cards with guardian badge', function () {
    $this->get('/tests/room/elem')->assertOk()
        ->assertSee('마음안전선별검사')
        ->assertSee('준비중')
        ->assertSee('보호자 동의 필요');
});
test('worker room shows active sample then coming-soon cards', function () {
    $this->get('/tests/room/worker')->assertOk()
        ->assertSee('직장인 마음상태 검사(샘플)')
        ->assertSee('번아웃검사')
        ->assertSee('준비중');
});
test('room page with unknown code 404', function () {
    $this->get('/tests/room/nope')->assertNotFound();
});
test('test detail shows meta and start button', function () {
    $this->get('/tests/KMSIA-SAMPLE')->assertOk()->assertSee('검사 시작')->assertSee('스트레스')->assertSee('약 5분');
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=CatalogTest`
Expected: FAIL — '마음안전선별검사'/'준비중'/'보호자 동의 필요' 미노출.

- [ ] **Step 3: Rooms.php 각 방에 planned_tests 추가**

`app/Support/Rooms.php` 각 방 배열에 `'planned_tests' => [...]` 키를 추가한다(개발안 §4 검사명):

```php
// elem
'planned_tests' => [
    ['name'=>'마음안전선별검사', 'target'=>'부모·교사용', 'guardian'=>true],
    ['name'=>'정서행동검사',     'target'=>'부모·교사용', 'guardian'=>true],
    ['name'=>'사회성검사',       'target'=>'부모·교사용', 'guardian'=>true],
    ['name'=>'주의집중검사',     'target'=>'부모·교사용', 'guardian'=>true],
    ['name'=>'스마트폰 사용검사','target'=>'부모·교사용', 'guardian'=>true],
],
// middle
'planned_tests' => [
    ['name'=>'청소년 마음상태검사','target'=>'학생 본인','guardian'=>false],
    ['name'=>'학업스트레스검사',  'target'=>'학생 본인','guardian'=>false],
    ['name'=>'시험불안검사',      'target'=>'학생 본인','guardian'=>false],
    ['name'=>'진로성향검사',      'target'=>'학생 본인','guardian'=>false],
    ['name'=>'대인관계검사',      'target'=>'학생 본인','guardian'=>false],
],
// univ
'planned_tests' => [
    ['name'=>'우울·불안·스트레스검사','target'=>'본인','guardian'=>false],
    ['name'=>'진로정체감검사',       'target'=>'본인','guardian'=>false],
    ['name'=>'대인관계검사',         'target'=>'본인','guardian'=>false],
    ['name'=>'자기조절검사',         'target'=>'본인','guardian'=>false],
],
// worker
'planned_tests' => [
    ['name'=>'번아웃검사',     'target'=>'본인','guardian'=>false],
    ['name'=>'직무스트레스검사','target'=>'본인','guardian'=>false],
    ['name'=>'분노조절검사',   'target'=>'본인','guardian'=>false],
    ['name'=>'회복탄력성검사', 'target'=>'본인','guardian'=>false],
],
// silver
'planned_tests' => [
    ['name'=>'실버 마음상태검사', 'target'=>'본인·가족','guardian'=>false],
    ['name'=>'우울·고독감검사',   'target'=>'본인·가족','guardian'=>false],
    ['name'=>'인지건강 선별검사', 'target'=>'본인·가족','guardian'=>false],
    ['name'=>'삶의만족도검사',    'target'=>'본인·가족','guardian'=>false],
],
```

> worker 방의 planned에는 활성 샘플(직장인 마음상태)과 중복되지 않는 4종만 둔다.

- [ ] **Step 4: room.blade.php에 준비중 카드 렌더**

`resources/views/catalog/room.blade.php` 의 검사 목록 섹션(현재 17–30행)을 다음으로 교체:

```blade
  <section class="bg-cream">
    <div class="max-w-6xl mx-auto px-4 py-14">
      @php $planned = $room['planned_tests'] ?? []; @endphp
      @if($tests->isEmpty() && empty($planned))
        <div class="rounded-3xl bg-white p-12 text-center shadow-sm">
          <p class="text-navy/60">준비 중인 검사입니다. 곧 만나보실 수 있어요.</p>
          <a href="{{ route('catalog.index') }}" class="inline-block mt-5 rounded-xl border border-teal text-teal px-6 py-2.5 font-semibold hover:bg-mint/30 transition">다른 방 보기</a>
        </div>
      @else
        <div class="grid md:grid-cols-3 gap-6">
          {{-- 활성 검사 (응시 가능) --}}
          @foreach($tests as $test) <x-test-card :test="$test"/> @endforeach

          {{-- 준비중 검사 (응시 불가) --}}
          @foreach($planned as $p)
            <div class="rounded-2xl bg-white/60 ring-1 ring-black/5 p-5 opacity-80">
              <div class="h-28 w-full rounded-xl mb-3 bg-gradient-to-br from-cream to-mint/30 flex items-center justify-center">
                <span class="text-navy/30 text-sm">준비중</span>
              </div>
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="font-semibold text-navy/50">{{ $p['name'] }}</h3>
                <span class="rounded-full bg-black/5 text-navy/50 text-[11px] px-2 py-0.5">준비중</span>
              </div>
              <p class="text-xs text-navy/40 mt-1">{{ $p['target'] }}</p>
              @if($p['guardian'])
                <span class="inline-block mt-2 rounded-full bg-signal-yellow/20 text-amber-700 text-[11px] px-2 py-0.5">보호자 동의 필요</span>
              @endif
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </section>
```

- [ ] **Step 5: 테스트 통과 확인**

Run: `php artisan test --filter=CatalogTest`
Expected: PASS (5개 테스트 전부).

- [ ] **Step 6: Commit**

```bash
git add app/Support/Rooms.php resources/views/catalog/room.blade.php tests/Feature/CatalogTest.php
git commit -m "feat: 방별 준비중 검사 카드 노출(개발안 검사명·보호자동의 뱃지)"
```

---

## Task C: 보호자(법정대리인) 동의 분기

**Files:**
- Create: `database/migrations/2026_06_26_000000_add_requires_guardian_consent_to_tests.php`
- Modify: `app/Models/Test.php`
- Modify: `resources/views/assessment/consent.blade.php`
- Modify: `app/Http/Controllers/AssessmentController.php`
- Test: `tests/Feature/GuardianConsentTest.php`

**Interfaces:**
- Produces: `tests.requires_guardian_consent` boolean(default false). `Test::$casts['requires_guardian_consent' => 'boolean']`. consent 폼이 플래그 ON일 때 `guardian_agree` 필드 전송. `AssessmentController::agree` 가 ON이면 `guardian_agree` 도 `accepted` 검증.

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/GuardianConsentTest.php` 생성:

```php
<?php
use App\Models\Test;

function makeGuardianTest(): Test {
    return Test::create([
        'code'=>'ELEM-GC','room'=>'elem','title_easy'=>'초등 마음안전(샘플)','title_pro'=>'GC',
        'target'=>'초1~초6 부모·교사용','duration_min'=>7,'item_count'=>4,
        'areas'=>['불안'],'result_type'=>'signal','description'=>'d','status'=>'active',
        'requires_guardian_consent'=>true,
    ]);
}

test('guardian test consent shows guardian section and reporting notice', function () {
    makeGuardianTest();
    $this->get('/assessment/ELEM-GC/consent')->assertOk()
        ->assertSee('만 14세 미만')
        ->assertSee('법정대리인')
        ->assertSee('아동학대'); // 신고의무 임시 고지
});

test('guardian test agree requires guardian_agree', function () {
    makeGuardianTest();
    $this->from('/assessment/ELEM-GC/consent')
        ->post('/assessment/ELEM-GC/agree', ['agree'=>'1']) // guardian_agree 누락
        ->assertSessionHasErrors('guardian_agree');
});

test('guardian test agree passes with both checks', function () {
    makeGuardianTest();
    $this->post('/assessment/ELEM-GC/agree', ['agree'=>'1','guardian_agree'=>'1'])
        ->assertRedirect(route('assessment.intro','ELEM-GC'));
});

test('adult test consent unchanged (no guardian section)', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $this->get('/assessment/KMSIA-SAMPLE/consent')->assertOk()
        ->assertSee('민감정보')
        ->assertDontSee('법정대리인');
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=GuardianConsentTest`
Expected: FAIL — `requires_guardian_consent` 컬럼 없음(SQL 에러) 및 화면 미분기.

- [ ] **Step 3: 마이그레이션 생성**

`database/migrations/2026_06_26_000000_add_requires_guardian_consent_to_tests.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tests', function (Blueprint $table) {
            $table->boolean('requires_guardian_consent')->default(false)->after('status');
        });
    }
    public function down(): void {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('requires_guardian_consent');
        });
    }
};
```

- [ ] **Step 4: Test 모델 반영**

`app/Models/Test.php` 는 `$guarded = []`(전체 mass-assign 허용)이라 fillable 수정 불필요. `$casts` 에 boolean 캐스트만 추가:

```php
    protected $casts = ['areas' => 'array', 'requires_guardian_consent' => 'boolean'];
```

- [ ] **Step 5: consent.blade.php 분기 추가**

`resources/views/assessment/consent.blade.php` 의 민감정보 안내 박스(8–11행) **아래, form 위**에 다음 블록을 삽입:

```blade
      @if($test->requires_guardian_consent)
        <div class="mt-4 rounded-3xl bg-signal-yellow/10 ring-1 ring-signal-yellow/40 p-6 text-sm text-navy/80 space-y-2">
          <p class="font-bold text-amber-700">⚠️ 이 검사는 만 14세 미만 대상입니다.</p>
          <p>개인정보보호법에 따라 <b>보호자(법정대리인)의 동의</b>가 있어야 검사를 진행할 수 있습니다.</p>
          {{-- TODO: 신고의무 고지 임시 문구 — 문구·연계절차 추후 협의 --}}
          <p class="text-navy/60">※ 검사 과정에서 아동학대 정황이 인지될 경우 관계 법령에 따라 신고될 수 있습니다.</p>
        </div>
      @endif
```

그리고 form 안 본인 동의 `<label>` **아래**(button 위)에 보호자 동의 체크를 조건부로 추가:

```blade
        @if($test->requires_guardian_consent)
          <label class="flex items-center gap-3 rounded-2xl bg-white p-4 mt-3 shadow-sm ring-1 ring-black/5 cursor-pointer">
            <input type="checkbox" name="guardian_agree" value="1" required class="h-5 w-5 accent-[#1F4D3F]">
            <span class="text-navy/80">저는 <b>보호자(법정대리인)</b>이며 아이의 검사에 동의합니다 <span class="text-signal-red">(필수)</span></span>
          </label>
        @endif
```

- [ ] **Step 6: AssessmentController::agree 검증 추가**

`app/Http/Controllers/AssessmentController.php` 의 `agree()` 를 수정:

```php
    public function agree(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $rules = ['agree' => 'accepted'];
        if ($test->requires_guardian_consent) {
            $rules['guardian_agree'] = 'accepted';
        }
        $request->validate($rules);
        return redirect()->route('assessment.intro', $code);
    }
```

- [ ] **Step 7: 테스트 통과 확인**

Run: `php artisan test --filter=GuardianConsentTest`
Expected: PASS (4개). 어른 검사 미분기 테스트 포함.

- [ ] **Step 8: 회귀 확인(기존 동의 흐름)**

Run: `php artisan test --filter=AssessmentStartTest`
Expected: PASS (어른 검사 동의/시작 변화 없음).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_06_26_000000_add_requires_guardian_consent_to_tests.php app/Models/Test.php resources/views/assessment/consent.blade.php app/Http/Controllers/AssessmentController.php tests/Feature/GuardianConsentTest.php
git commit -m "feat: 만14세미만 보호자(법정대리인) 동의 분기 + 신고의무 임시 고지"
```

---

## Task D: 강의·코칭 소개 페이지

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/CoachingController.php`
- Create: `resources/views/coaching/index.blade.php`
- Test: `tests/Feature/CoachingTest.php`

**Interfaces:**
- Consumes: 기존 `institution` named route(하단 CTA 링크).
- Produces: `coaching` named route가 `CoachingController@index` 로 연결(coming-soon 클로저 대체).

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/CoachingTest.php` 생성:

```php
<?php
test('coaching page renders program intro and age-group programs', function () {
    $this->get('/coaching')->assertOk()
        ->assertSee('변화가 시작됩니다')
        ->assertSee('마음안전 신호등 교실')   // 초등 프로그램
        ->assertSee('번아웃 리셋 마음관리')   // 직장인 프로그램
        ->assertSee('1:1 코칭');              // 유형 칩
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=CoachingTest`
Expected: FAIL — 현재 `/coaching` 은 coming-soon 스텁이라 '변화가 시작됩니다' 미노출.

- [ ] **Step 3: 라우트 교체**

`routes/web.php`:
- coming-soon `foreach` 배열(20–28행)에서 `'coaching' => ['/coaching', '강의·코칭'],` 줄을 **제거**.
- `CatalogController` use 문 근처에 `use App\Http\Controllers\CoachingController;` 추가.
- catalog 라우트들 아래에 추가:

```php
Route::get('/coaching', [CoachingController::class, 'index'])->name('coaching');
```

- [ ] **Step 4: 컨트롤러 생성**

`app/Http/Controllers/CoachingController.php`:

```php
<?php
namespace App\Http\Controllers;

class CoachingController extends Controller
{
    public function index()
    {
        $programs = [
            ['room'=>'초등학생',   'name'=>'마음안전 신호등 교실',      'desc'=>'감정표현, 친구관계, 스마트폰 조절', 'type'=>'4회기 집단'],
            ['room'=>'초등 부모',  'name'=>'우리아이 마음 읽기 부모코칭','desc'=>'정서행동 이해, 훈육, 양육스트레스 관리', 'type'=>'2시간 특강/4주 코칭'],
            ['room'=>'중고등학생', 'name'=>'흔들리는 10대 마음근육 훈련','desc'=>'스트레스, 시험불안, 자기조절, 진로', 'type'=>'학교 특강/집단상담'],
            ['room'=>'대학생',     'name'=>'나를 찾는 진로·관계 코칭',   'desc'=>'진로정체감, 대인관계, 자기효능감', 'type'=>'워크숍/1:1 코칭'],
            ['room'=>'직장인',     'name'=>'번아웃 리셋 마음관리',       'desc'=>'스트레스, 분노, 회복탄력성, 소통', 'type'=>'기업특강/EAP'],
            ['room'=>'실버',       'name'=>'다시 피어나는 마음정원',     'desc'=>'고독감, 우울, 회상, 삶의 의미', 'type'=>'복지관 프로그램'],
        ];
        $types = ['특강','집단상담','부모교육','교사연수','1:1 코칭','기관 패키지'];
        return view('coaching.index', compact('programs', 'types'));
    }
}
```

- [ ] **Step 5: 뷰 생성**

`resources/views/coaching/index.blade.php`:

```blade
<x-layouts.app :title="'강의·코칭 · 검사 결과 이후의 변화'">
  {{-- HERO --}}
  <section class="bg-gradient-to-br from-deepgreen to-teal text-cream">
    <div class="max-w-5xl mx-auto px-4 py-16">
      <span class="inline-block rounded-full bg-mint/20 text-mint px-3 py-1 text-xs font-semibold mb-4">강의·코칭 프로그램</span>
      <h1 class="text-2xl md:text-4xl font-extrabold leading-snug">검사 결과 이후,<br>변화가 시작됩니다</h1>
      <p class="mt-4 text-cream/80 max-w-2xl">심지는 검사와 결과에서 끝나지 않습니다. “그래서 이제 무엇을 해야 하나요?”에 답하는 연령별 강의·코칭으로 이어집니다.</p>
      <div class="mt-6 flex flex-wrap gap-2">
        @foreach($types as $type)
          <span class="rounded-full bg-cream/10 text-cream/90 px-4 py-1.5 text-sm">{{ $type }}</span>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 연령별 프로그램 --}}
  <section class="bg-cream">
    <div class="max-w-5xl mx-auto px-4 py-16">
      <h2 class="text-2xl font-extrabold text-deepgreen text-center mb-10">연령별 강의·코칭 프로그램</h2>
      <div class="grid md:grid-cols-2 gap-5">
        @foreach($programs as $p)
          <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex items-center justify-between">
              <span class="rounded-full bg-mint/40 text-deepgreen text-xs font-semibold px-3 py-1">{{ $p['room'] }}</span>
              <span class="text-xs text-navy/50">{{ $p['type'] }}</span>
            </div>
            <h3 class="font-bold text-deepgreen text-lg mt-3">{{ $p['name'] }}</h3>
            <p class="text-sm text-navy/60 mt-1.5">{{ $p['desc'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 기관 CTA --}}
  <section class="bg-white">
    <div class="max-w-5xl mx-auto px-4 py-14">
      <div class="rounded-3xl bg-navy text-cream p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-xl md:text-2xl font-extrabold">기관 맞춤형 특강·코칭이 필요하신가요?</h3>
          <p class="mt-2 text-cream/70">학교·기업·복지관 단위 프로그램을 설계해 드립니다.</p>
        </div>
        <a href="{{ route('institution') }}" class="shrink-0 rounded-xl bg-mint text-deepgreen px-7 py-3.5 font-bold hover:brightness-105 transition">기관 도입 문의</a>
      </div>
    </div>
  </section>
</x-layouts.app>
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `php artisan test --filter=CoachingTest`
Expected: PASS.

- [ ] **Step 7: 전체 회귀 + 헤더 링크 확인**

Run: `php artisan test`
Expected: 전체 PASS. (헤더 메뉴 '강의·코칭' 링크는 `route('coaching')` 이므로 자동 정상.)

- [ ] **Step 8: Commit**

```bash
git add routes/web.php app/Http/Controllers/CoachingController.php resources/views/coaching/index.blade.php tests/Feature/CoachingTest.php
git commit -m "feat: 강의·코칭 소개 페이지(연령별 프로그램·유형·기관CTA)"
```

---

## 최종 검증

- [ ] `php artisan test` 전체 PASS.
- [ ] `php artisan migrate:fresh --seed` 후 수동 확인: 홈 5개 방 / 초등방 준비중+보호자뱃지 / `/coaching` 정상 / 어른 검사 동의 화면 무변화.
- [ ] (검증용) 기존 worker 샘플에 `requires_guardian_consent` 임시 true → consent 화면 보호자 섹션 확인 → false 복원.

## Self-Review 결과

- 스펙 커버리지: A(5방)·B(준비중카드)·C(보호자동의)·D(강의코칭) 전부 태스크 매핑됨. 향후 협의 과제는 의도적 비범위.
- placeholder: 신고의무 문구만 의도적 임시(코드에 TODO 주석). 그 외 없음.
- 타입 일관성: `planned_tests` 키(name/target/guardian), `requires_guardian_consent`, `guardian_agree` 필드명 태스크 간 일치.
