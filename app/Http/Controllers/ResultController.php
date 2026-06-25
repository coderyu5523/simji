<?php
namespace App\Http\Controllers;
use App\Models\TestAttempt;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function show(Request $request, TestAttempt $attempt)
    {
        abort_unless($attempt->isOwnedBy($request), 403);

        $attempt->load('test', 'result');
        abort_if($attempt->result === null, 404);
        return view('result.show', ['attempt' => $attempt, 'test' => $attempt->test, 'result' => $attempt->result]);
    }
}
