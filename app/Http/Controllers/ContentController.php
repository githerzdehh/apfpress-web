<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ContentController extends Controller
{
    public function page(string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('content.page', compact('page'));
    }

    public function board(): View
    {
        $members = DB::table('editorial_board_members')->where('active', true)->orderBy('position')->get();

        return view('content.board', compact('members'));
    }
}
