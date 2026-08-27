<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => now()->toDateString(), 'priority' => '1.0'],
            ['loc' => route('catalog.index'), 'lastmod' => CatalogItem::query()->max('updated_at'), 'priority' => '0.9'],
            ['loc' => route('board'), 'lastmod' => now()->toDateString(), 'priority' => '0.6'],
            ['loc' => route('contact'), 'lastmod' => now()->toDateString(), 'priority' => '0.5'],
        ]);
        CatalogItem::query()->published()->where('type', 'book')->get(['slug', 'updated_at'])->each(fn ($item) => $urls->push([
            'loc' => route('catalog.show', $item->slug), 'lastmod' => $item->updated_at->toDateString(), 'priority' => '0.8',
        ]));
        Page::query()->where('status', 'published')->get(['slug', 'updated_at'])->each(fn ($page) => $urls->push([
            'loc' => in_array($page->slug, ['about', 'publish-with-us'], true) ? url('/'.$page->slug) : route('policy', $page->slug),
            'lastmod' => $page->updated_at->toDateString(), 'priority' => '0.6',
        ]));

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
