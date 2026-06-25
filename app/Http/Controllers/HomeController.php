<?php
namespace App\Http\Controllers;
use App\Models\Test;
use App\Support\Rooms;
class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'rooms' => Rooms::all(),
            'recommended' => Test::where('status','active')->latest()->take(3)->get(),
        ]);
    }
}
