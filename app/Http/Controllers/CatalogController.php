<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Offering;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'in:print_book,ebook'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:newest,title,price_low,price_high'],
        ]);

        $query = CatalogItem::query()->published()->where('type', 'book')
            ->with(['contributors', 'categories', 'media', 'offerings.bookEdition', 'offerings.inventory']);

        $query->when($filters['q'] ?? null, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('summary', 'like', '%'.$search.'%')
                    ->orWhereHas('contributors', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'));
            });
        });
        $query->when($filters['format'] ?? null, fn (Builder $query, string $format) => $query->whereHas('offerings', fn (Builder $query) => $query->where('kind', $format)));
        $query->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereHas('categories', fn (Builder $query) => $query->where('slug', $category)));

        match ($filters['sort'] ?? 'newest') {
            'title' => $query->orderBy('title'),
            'price_low' => $query->orderBy(Offering::select('price_amount')->whereColumn('catalog_item_id', 'catalog_items.id')->orderBy('price_amount')->limit(1)),
            'price_high' => $query->orderByDesc(Offering::select('price_amount')->whereColumn('catalog_item_id', 'catalog_items.id')->orderByDesc('price_amount')->limit(1)),
            default => $query->orderByDesc('published_at')->orderBy('title'),
        };

        return view('catalog.index', [
            'items' => $query->paginate(12)->withQueryString(),
            'categories' => Category::query()->whereHas('catalogItems', fn (Builder $query) => $query->published())->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(CatalogItem $catalogItem): View
    {
        abort_unless($catalogItem->status === 'published' && $catalogItem->type === 'book', 404);
        $catalogItem->load(['contributors', 'categories', 'media', 'bookDetails', 'offerings.bookEdition', 'offerings.inventory']);

        $related = CatalogItem::query()->published()->where('type', 'book')->whereKeyNot($catalogItem->id)
            ->when($catalogItem->categories->isNotEmpty(), fn (Builder $query) => $query->whereHas('categories', fn (Builder $query) => $query->whereIn('categories.id', $catalogItem->categories->pluck('id'))))
            ->with(['contributors', 'media', 'offerings.inventory'])->limit(3)->get();

        return view('catalog.show', compact('catalogItem', 'related'));
    }
}
