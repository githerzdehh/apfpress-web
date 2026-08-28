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
            'authors' => $this->contributors->filter(fn ($contributor) => $contributor->pivot->role === 'author')->map->only(['id', 'name', 'slug'])->values(),
            'contributors' => $this->contributors->map(fn ($contributor) => [
                'id' => $contributor->id,
                'name' => $contributor->name,
                'slug' => $contributor->slug,
                'role' => $contributor->pivot->role,
                'position' => (int) $contributor->pivot->position,
            ])->values(),
            'categories' => $this->categories->map->only(['id', 'name', 'slug']),
            'cover' => $this->cover?->url,
            'offerings' => $this->offerings->where('active', true)->sortBy('position')->values()->map(fn ($offering) => [
                'id' => $offering->id,
                'kind' => $offering->kind,
                'name' => $offering->name,
                'sku' => $offering->sku,
                'price_amount' => $offering->price_amount,
                'formatted_price' => $offering->formatted_price,
                'currency' => $offering->currency,
                'purchase_mode' => $offering->purchase_mode,
                'available' => $offering->isAvailable(),
                'edition' => $offering->bookEdition ? [
                    'format' => $offering->bookEdition->format,
                    'isbn_10' => $offering->bookEdition->isbn_10,
                    'isbn_13' => $offering->bookEdition->isbn_13,
                    'publication_date' => $offering->bookEdition->publication_date?->toDateString(),
                    'page_count' => $offering->bookEdition->page_count,
                ] : null,
            ]),
            'url' => route('catalog.show', $this->slug),
        ];
    }
}
