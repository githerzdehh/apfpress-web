<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogItemResource;
use App\Models\CatalogItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = CatalogItem::query()->published()->where('type', 'book')
            ->with([
                'contributors', 'categories', 'media',
                'offerings' => fn ($query) => $query->active()->orderBy('position'),
                'offerings.bookEdition', 'offerings.inventory', 'offerings.digitalAssets',
            ])
            ->orderBy('title')->paginate(24);

        return CatalogItemResource::collection($items);
    }

    public function show(CatalogItem $catalogItem): CatalogItemResource
    {
        abort_unless($catalogItem->status === 'published' && $catalogItem->type === 'book', 404);

        return new CatalogItemResource($catalogItem->load([
            'contributors', 'categories', 'media',
            'offerings' => fn ($query) => $query->active()->orderBy('position'),
            'offerings.bookEdition', 'offerings.inventory', 'offerings.digitalAssets',
        ]));
    }
}
