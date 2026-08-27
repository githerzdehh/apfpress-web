<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'description' => $this->description,
            'authors' => $this->contributors->map->only(['id', 'name', 'slug']),
            'categories' => $this->categories->map->only(['id', 'name', 'slug']),
            'cover' => $this->cover?->url,
            'offerings' => $this->offerings->map(fn ($offering) => [
                'id' => $offering->id,
                'kind' => $offering->kind,
                'name' => $offering->name,
                'sku' => $offering->sku,
                'price_amount' => $offering->price_amount,
                'formatted_price' => $offering->formatted_price,
                'currency' => $offering->currency,
                'purchase_mode' => $offering->purchase_mode,
                'available' => $offering->isAvailable(),
                'edition' => $offering->bookEdition?->only(['format', 'isbn_10', 'isbn_13', 'publication_date', 'page_count']),
            ]),
            'url' => route('catalog.show', $this->slug),
        ];
    }
}
