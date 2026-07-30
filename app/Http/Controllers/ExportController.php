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
