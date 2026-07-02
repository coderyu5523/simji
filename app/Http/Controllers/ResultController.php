<?php
namespace App\Http\Controllers;
use App\Models\TestAttempt;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function show(Request $request, TestAttempt $attempt)
    {
        $attempt->load('test', 'result', 'voucher');

        $isTaker = $attempt->isOwnedBy($request);
        $isIssuer = auth()->check() && $attempt->voucher && $attempt->voucher->user_id === auth()->id();
        abort_unless($isTaker || $isIssuer, 403);

        abort_if($attempt->result === null, 404);

        // 열람 통제: 응시자 본인이 볼 때 발급자가 '대기'로 막아뒀으면 안내 (발급자는 항상 열람)
        if ($isTaker && !$isIssuer && $attempt->voucher && !$attempt->voucher->result_visible) {
            return view('result.pending', ['attempt' => $attempt, 'test' => $attempt->test]);
        }

        return view('result.show', ['attempt' => $attempt, 'test' => $attempt->test, 'result' => $attempt->result]);
    }
}
