<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featured = CatalogItem::query()->published()
            ->with([
                'contributors', 'media',
                'offerings' => fn ($query) => $query->active()->orderBy('position'),
                'offerings.inventory',
            ])
            ->where('type', 'book')
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        return view('home', [
            'featured' => $featured,
            'statistics' => [
                'titles' => CatalogItem::query()->published()->where('type', 'book')->count(),
                'perspectives' => 'Independent',
                'home' => 'Canada',
            ],
            'boardPreview' => DB::table('editorial_board_members')->where('active', true)->orderBy('position')->limit(3)->get(),
        ]);
    }
}
