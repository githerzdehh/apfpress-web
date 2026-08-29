<?php

namespace App\Http\Resources;

use App\Services\CatalogMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'contributors', 'categories', 'media', 'bookDetails',
            'offerings.bookEdition', 'offerings.inventory', 'offerings.digitalAssets',
        ]);
        $assessment = app(CatalogMetadata::class)->assess($this->resource);
        $cover = $this->cover;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'description' => $this->description,
            'status' => $this->status,
            'featured' => (bool) $this->featured,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'published_at' => $this->published_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'metadata_flags' => $assessment['flags'],
            'warnings' => $assessment['warnings'],
            'book_details' => [
                'publisher' => $this->bookDetails?->publisher ?? 'APF Press',
                'imprint' => $this->bookDetails?->imprint,
                'original_language' => $this->bookDetails?->original_language ?? 'en',
            ],
            'contributors' => $this->contributors->map(fn ($contributor) => [
                'id' => $contributor->id,
                'name' => $contributor->name,
                'role' => $contributor->pivot->role,
                'position' => (int) $contributor->pivot->position,
            ])->values(),
            'categories' => $this->categories->map->only(['id', 'name', 'slug'])->values(),
            'cover' => $cover ? [
                'id' => $cover->id,
                'url' => $cover->url,
                'alt_text' => $cover->alt_text,
            ] : null,
            'offerings' => $this->offerings->sortBy('position')->values()->map(function ($offering): array {
                $edition = $offering->bookEdition;
                $inventory = $offering->inventory;
                $currentAsset = $offering->digitalAssets->first(fn ($asset) => $asset->active && $asset->is_current);

                return [
                    'id' => $offering->id,
                    'active' => (bool) $offering->active,
                    'position' => (int) $offering->position,
                    'kind' => $offering->kind,
                    'name' => $offering->name,
                    'sku' => $offering->sku,
                    'price_amount' => $offering->price_amount,
                    'currency' => $offering->currency,
                    'purchase_mode' => $offering->purchase_mode,
                    'access_duration_days' => $offering->access_duration_days,
                    'available' => $offering->isAvailable(),
                    'edition' => [
                        'format' => $edition?->format ?? ($offering->kind === 'ebook' ? 'pdf' : 'paperback'),
                        'edition_label' => $edition?->edition_label,
                        'isbn_10' => $edition?->isbn_10,
                        'isbn_13' => $edition?->isbn_13,
                        'publication_date' => $edition?->publication_date?->toDateString(),
                        'page_count' => $edition?->page_count,
                        'language' => $edition?->language ?? 'en',
                        'weight_grams' => $edition?->weight_grams,
                        'width_mm' => $edition?->width_mm,
                        'height_mm' => $edition?->height_mm,
                        'depth_mm' => $edition?->depth_mm,
                    ],
                    'inventory' => [
                        'on_hand' => (int) ($inventory?->on_hand ?? 0),
                        'reserved' => (int) ($inventory?->reserved ?? 0),
                        'low_stock_threshold' => (int) ($inventory?->low_stock_threshold ?? 2),
                        'track_inventory' => (bool) ($inventory?->track_inventory ?? false),
                        'allow_backorder' => (bool) ($inventory?->allow_backorder ?? false),
                    ],
                    'current_digital_asset' => $currentAsset ? [
                        'id' => $currentAsset->id,
                        'file_name' => $currentAsset->file_name,
                        'version' => (int) $currentAsset->version,
                        'size_bytes' => $currentAsset->size_bytes,
                    ] : null,
                ];
            }),
        ];
    }
}
