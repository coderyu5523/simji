<?php
namespace App\Http\Controllers;

use App\Models\Test;
use App\Support\Rooms;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogController extends Controller
{
    public function index()
    {
        return view('catalog.index', ['rooms' => Rooms::all()]);
    }

    public function room(string $code)
    {
        $room = Rooms::find($code) ?? throw new NotFoundHttpException();
        $tests = Test::where('room', $code)->where('status', 'active')->get();
        return view('catalog.room', compact('room', 'tests'));
    }

    public function show(string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        return view('catalog.show', compact('test'));
    }
}
