<?php

namespace App\Services\OyMsi;

/**
 * 결과 문안 한 건(여러 줄)을 렌더링 종류별로 분류한다.
 *
 * 왜 필요한가 — Task 15 가 시딩한 문안은 원문(005/006/003) 전사물이라 한 필드 안에
 * 소제목·질문·실천항목·서술문단이 섞여 있다. 예를 들어
 * `result.YOUTH.IMP.YELLOW.actions` 는 "멈춤 4단계" / "함께 확인할 것" 이라는
 * 소제목 줄과 "잠이 부족한 날 화가 더 심해지는가?" 같은 점검 질문을 포함한다.
 * 이걸 줄 단위 체크리스트로 그대로 찍으면 소제목이 실천항목 불릿으로 나온다.
 * 원문 텍스트는 전사물이므로 고치지 않고, 여기(렌더링 쪽)에서 분류한다.
 *
 * 분류 규칙 (데이터 174건 전수 확인 후 확정 — ResultScreenTest 가 고정한다)
 *   0. 앞뒤 공백을 지우고 빈 줄은 버린다.
 *   1. `?` 로 끝나면 question — 점검 질문이다. 실천항목과 구분해서 보여준다.
 *   2. 소제목 후보 = 종결부호(. ! ? ” ") 없이 끝나면서 `기`(명사형 어미)로도
 *      끝나지 않는 줄. 후보가 연달아 나오면 첫 줄만 heading 이고 나머지는 item 이다
 *      (예: "기억할 연락처" 아래 "자살예방 상담전화: 109" 같은 값 나열).
 *   3. `기` 로 끝나면 언제나 item (실천항목의 표준 어미).
 *   4. MODE_LIST(actions/avoid/steps) — 나머지는 전부 item. 이 필드들은 통째로
 *      목록이므로 서술문단이 없다.
 *   5. MODE_MIXED(safety_notice) — 첫 줄은 도입 서술이므로 언제나 paragraph.
 *      소제목이 나온 뒤라면: 두 문장 이상이면서 50자를 넘으면 paragraph(마무리
 *      서술), 아니면 item. 소제목이 아직 안 나왔다면: 두 문장 이상이거나 45자를
 *      넘으면 paragraph, 아니면 item.
 *
 * 알려진 한계 — YOUTH ENV E0/E1 의 두 번째 줄("...1388에 바로 알려 줘." /
 * "어떤 상황인지 상담자와 구체적으로 이야기해 보자.")은 짧은 서술문이라 item 으로
 * 분류된다. 지시문 성격이라 불릿으로 읽어도 뜻이 어긋나지 않아 그대로 둔다.
 */
class TemplateLineParser
{
    /** actions / avoid / steps — 필드 전체가 목록이다 */
    public const MODE_LIST = 'list';

    /** safety_notice — 서술 문단 + 소제목 + 항목이 섞여 있다 */
    public const MODE_MIXED = 'mixed';

    private const HEADING_MAX_SENTENCES_LEN = 50;
    private const PREHEADING_PARAGRAPH_LEN = 45;

    /** @return list<array{kind:'heading'|'item'|'question'|'paragraph', text:string}> */
    public function parse(string $text, string $mode): array
    {
        $raw = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $raw[] = $line;
            }
        }

        $candidate = array_map(fn ($l) => $this->isHeadingCandidate($l), $raw);

        $out = [];
        $headingSeen = false;
        foreach ($raw as $i => $line) {
            $kind = $this->classify($line, $mode, $i, $candidate, $headingSeen);
            if ($kind === 'heading') {
                $headingSeen = true;
            }
            $out[] = ['kind' => $kind, 'text' => $line];
        }

        return $out;
    }

    /** @param list<bool> $candidate */
    private function classify(string $line, string $mode, int $i, array $candidate, bool $headingSeen): string
    {
        if (str_ends_with($line, '?')) {
            return 'question';
        }

        // 소제목 후보가 연달아 오면 첫 줄만 소제목 (그 아래는 값 나열)
        if ($candidate[$i] && !($candidate[$i - 1] ?? false)) {
            return 'heading';
        }

        if (str_ends_with($line, '기')) {
            return 'item';
        }

        if ($mode === self::MODE_LIST) {
            return 'item';
        }

        // MODE_MIXED
        if ($i === 0) {
            return 'paragraph';
        }

        $sentences = $this->sentenceCount($line);
        $length = mb_strlen($line);

        if ($headingSeen) {
            return ($sentences > 1 && $length > self::HEADING_MAX_SENTENCES_LEN) ? 'paragraph' : 'item';
        }

        return ($sentences > 1 || $length > self::PREHEADING_PARAGRAPH_LEN) ? 'paragraph' : 'item';
    }

    private function isHeadingCandidate(string $line): bool
    {
        if (preg_match('/[.!?”"’\'）)]$/u', $line)) {
            return false;
        }

        return !str_ends_with($line, '기');
    }

    private function sentenceCount(string $line): int
    {
        return preg_match_all('/[.!?](\s|$)/u', $line) ?: 0;
    }
}
