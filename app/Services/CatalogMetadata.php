<?php

namespace App\Services;

use App\Models\CatalogItem;

class CatalogMetadata
{
    /** @return array{flags: array<int, string>, warnings: array<string, array<int, string>>} */
    public function assess(CatalogItem $item): array
    {
        $item->loadMissing(['contributors', 'offerings.bookEdition', 'offerings.inventory']);
        $flags = [];
        $warnings = [];

        if (! $item->contributors->contains(fn ($contributor) => $contributor->pivot->role === 'author')) {
            $flags[] = 'missing_author';
            $warnings['contributors'][] = 'Add at least one contributor with the Author role.';
        }

        $offerings = $item->offerings->sortBy('position')->values();
        foreach ($offerings as $index => $offering) {
            if (! $offering->active) {
                continue;
            }
            $edition = $offering->bookEdition;
            $prefix = "offerings.{$index}";

            if (! $edition?->isbn_10 && ! $edition?->isbn_13) {
                $flags[] = 'missing_isbn';
                $warnings["{$prefix}.edition.isbn_13"][] = 'Add an ISBN-10 or ISBN-13 when it is available.';
            }
            if (! $edition?->publication_date) {
                $flags[] = 'missing_publication_date';
                $warnings["{$prefix}.edition.publication_date"][] = 'Add the publication date when it is confirmed.';
            }
            if (! $edition?->page_count) {
                $flags[] = 'missing_page_count';
                $warnings["{$prefix}.edition.page_count"][] = 'Add the final page count when it is confirmed.';
            }
            if ($offering->price_amount === null) {
                $flags[] = 'missing_price';
                $warnings["{$prefix}.price_amount"][] = 'Add a price when it is confirmed.';
            }
            if (! $offering->inventory?->track_inventory && $offering->kind === 'print_book') {
                $flags[] = 'missing_stock_count';
                $warnings["{$prefix}.inventory.on_hand"][] = 'Enable inventory tracking when the exact stock count is known.';
            }
        }

        return ['flags' => array_values(array_unique($flags)), 'warnings' => $warnings];
    }
}
