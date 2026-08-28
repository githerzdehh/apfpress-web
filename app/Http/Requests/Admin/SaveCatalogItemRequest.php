<?php

namespace App\Http\Requests\Admin;

use App\Models\BookEdition;
use App\Models\CatalogItem;
use App\Models\Offering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCatalogItemRequest extends FormRequest
{
    private const CONTRIBUTOR_ROLES = ['author', 'editor', 'translator', 'illustrator', 'foreword', 'contributor'];

    public function rules(): array
    {
        /** @var CatalogItem|null $item */
        $item = $this->route('catalogItem');

        return [
            'type' => ['required', Rule::in(['book'])],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('catalog_items', 'slug')->ignore($item?->id)],
            'summary' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'featured' => ['required', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'book_details' => ['required', 'array'],
            'book_details.publisher' => ['required', 'string', 'max:255'],
            'book_details.imprint' => ['nullable', 'string', 'max:255'],
            'book_details.original_language' => ['required', 'string', 'max:10'],
            'contributors' => ['present', 'array'],
            'contributors.*.id' => ['nullable', 'integer', 'exists:contributors,id'],
            'contributors.*.name' => ['nullable', 'string', 'max:255'],
            'contributors.*.role' => ['required', Rule::in(self::CONTRIBUTOR_ROLES)],
            'contributors.*.position' => ['nullable', 'integer', 'min:0'],
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer', 'exists:categories,id'],
            'categories.*.name' => ['nullable', 'string', 'max:255'],
            'offerings' => ['required', 'array', 'min:1'],
            'offerings.*.id' => ['nullable', 'integer', 'exists:offerings,id'],
            'offerings.*.active' => ['required', 'boolean'],
            'offerings.*.position' => ['nullable', 'integer', 'min:0'],
            'offerings.*.kind' => ['required', Rule::in(['print_book', 'ebook'])],
            'offerings.*.name' => ['required', 'string', 'max:120'],
            'offerings.*.sku' => ['nullable', 'string', 'max:120'],
            'offerings.*.price_amount' => ['nullable', 'integer', 'min:0'],
            'offerings.*.purchase_mode' => ['required', Rule::in(['online', 'inquiry', 'unavailable'])],
            'offerings.*.access_duration_days' => ['nullable', 'integer', 'between:1,3650'],
            'offerings.*.edition' => ['required', 'array'],
            'offerings.*.edition.format' => ['required', Rule::in(['paperback', 'hardcover', 'pdf', 'epub', 'other'])],
            'offerings.*.edition.edition_label' => ['nullable', 'string', 'max:255'],
            'offerings.*.edition.isbn_10' => ['nullable', 'string', 'size:10'],
            'offerings.*.edition.isbn_13' => ['nullable', 'string', 'size:13'],
            'offerings.*.edition.publication_date' => ['nullable', 'date_format:Y-m-d'],
            'offerings.*.edition.page_count' => ['nullable', 'integer', 'min:1'],
            'offerings.*.edition.language' => ['required', 'string', 'max:10'],
            'offerings.*.edition.weight_grams' => ['nullable', 'numeric', 'gt:0'],
            'offerings.*.edition.width_mm' => ['nullable', 'numeric', 'gt:0'],
            'offerings.*.edition.height_mm' => ['nullable', 'numeric', 'gt:0'],
            'offerings.*.edition.depth_mm' => ['nullable', 'numeric', 'gt:0'],
            'offerings.*.inventory' => ['required', 'array'],
            'offerings.*.inventory.on_hand' => ['required', 'integer', 'min:0'],
            'offerings.*.inventory.low_stock_threshold' => ['required', 'integer', 'min:0'],
            'offerings.*.inventory.track_inventory' => ['required', 'boolean'],
            'offerings.*.inventory.allow_backorder' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = fn ($value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
        $contributors = collect(is_array($this->input('contributors')) ? $this->input('contributors') : [])->map(function ($contributor, int $position) use ($nullable): array {
            return array_merge((array) $contributor, [
                'name' => $nullable($contributor['name'] ?? null),
                'position' => $position,
            ]);
        })->all();
        $categories = collect(is_array($this->input('categories')) ? $this->input('categories') : [])->map(fn ($category) => array_merge((array) $category, [
            'name' => $nullable($category['name'] ?? null),
        ]))->all();
        $offerings = collect(is_array($this->input('offerings')) ? $this->input('offerings') : [])->map(function ($offering, int $position) use ($nullable): array {
            $offering = (array) $offering;
            $edition = (array) ($offering['edition'] ?? []);
            $inventory = (array) ($offering['inventory'] ?? []);
            foreach (['edition_label', 'publication_date', 'page_count', 'weight_grams', 'width_mm', 'height_mm', 'depth_mm'] as $field) {
                $edition[$field] = $nullable($edition[$field] ?? null);
            }
            foreach (['isbn_10', 'isbn_13'] as $field) {
                $edition[$field] = ($value = $nullable($edition[$field] ?? null))
                    ? strtoupper((string) preg_replace('/[^0-9X]/i', '', $value))
                    : null;
            }

            return array_merge($offering, [
                'position' => $position,
                'name' => $nullable($offering['name'] ?? null),
                'sku' => $nullable($offering['sku'] ?? null),
                'price_amount' => $nullable($offering['price_amount'] ?? null),
                'access_duration_days' => $nullable($offering['access_duration_days'] ?? null),
                'edition' => $edition,
                'inventory' => $inventory,
            ]);
        })->all();

        $this->merge([
            'type' => 'book',
            'title' => trim((string) $this->input('title')),
            'subtitle' => $nullable($this->input('subtitle')),
            'slug' => ($slug = $nullable($this->input('slug'))) ? Str::slug($slug) : null,
            'summary' => $nullable($this->input('summary')),
            'description' => $nullable($this->input('description')),
            'seo_title' => $nullable($this->input('seo_title')),
            'seo_description' => $nullable($this->input('seo_description')),
            'book_details' => array_map($nullable, (array) $this->input('book_details', [])),
            'contributors' => $contributors,
            'categories' => $categories,
            'offerings' => $offerings,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $item = $this->route('catalogItem');
            $contributors = is_array($this->input('contributors')) ? $this->input('contributors') : [];
            $categories = is_array($this->input('categories')) ? $this->input('categories') : [];
            $offerings = is_array($this->input('offerings')) ? $this->input('offerings') : [];

            $this->validateNamedSelections($validator, 'contributors', $contributors);
            $this->validateNamedSelections($validator, 'categories', $categories);

            if ($this->input('status') === 'published' && collect($offerings)->doesntContain(fn ($offering) => (bool) ($offering['active'] ?? false))) {
                $validator->errors()->add('offerings', 'A published title must have at least one active edition.');
            }

            $seenSkus = [];
            $seenIsbns = [];
            foreach ($offerings as $index => $offering) {
                $id = isset($offering['id']) ? (int) $offering['id'] : null;
                if ($id && (! $item || ! $item->offerings()->whereKey($id)->exists())) {
                    $validator->errors()->add("offerings.{$index}.id", 'This edition does not belong to the selected title.');
                }

                $active = (bool) ($offering['active'] ?? false);
                $online = ($offering['purchase_mode'] ?? null) === 'online';
                if ($online && ! $active) {
                    $validator->errors()->add("offerings.{$index}.purchase_mode", 'An online edition must be active.');
                }
                if ($online && empty($offering['sku'])) {
                    $validator->errors()->add("offerings.{$index}.sku", 'A SKU is required for online sale.');
                }
                if ($online && ! isset($offering['price_amount'])) {
                    $validator->errors()->add("offerings.{$index}.price_amount", 'A price is required for online sale.');
                }
                if ($online && ($offering['kind'] ?? null) === 'ebook'
                    && (! $id || ! Offering::query()->whereKey($id)->whereHas('digitalAssets', fn ($query) => $query->where('active', true)->where('is_current', true))->exists())) {
                    $validator->errors()->add("offerings.{$index}.purchase_mode", 'Upload a protected digital file before enabling online sale.');
                }

                $format = $offering['edition']['format'] ?? null;
                $allowedFormats = ($offering['kind'] ?? null) === 'ebook' ? ['pdf', 'epub', 'other'] : ['paperback', 'hardcover', 'other'];
                if ($format && ! in_array($format, $allowedFormats, true)) {
                    $validator->errors()->add("offerings.{$index}.edition.format", 'Choose a format that matches the edition type.');
                }

                $this->validateUniqueValue($validator, $seenSkus, 'offerings', 'sku', $offering['sku'] ?? null, $id, $index);
                foreach (['isbn_10', 'isbn_13'] as $field) {
                    $isbn = $offering['edition'][$field] ?? null;
                    if ($isbn && ! $this->validIsbn($isbn)) {
                        $validator->errors()->add("offerings.{$index}.edition.{$field}", 'Enter a valid ISBN with a correct checksum.');
                    }
                    $this->validateUniqueValue($validator, $seenIsbns, 'book_editions', $field, $isbn, $id, $index, 'edition.');
                }
            }
        });
    }

    private function validateNamedSelections(Validator $validator, string $field, array $values): void
    {
        $seen = [];
        foreach ($values as $index => $value) {
            if (empty($value['id']) && empty($value['name'])) {
                $validator->errors()->add("{$field}.{$index}.name", 'Select an existing record or enter a new name.');
            }
            $key = ! empty($value['id']) ? 'id:'.$value['id'] : 'name:'.Str::lower((string) ($value['name'] ?? ''));
            if (isset($seen[$key])) {
                $validator->errors()->add($field === 'categories' ? 'categories' : "contributors.{$index}.name", 'This record has already been added.');
            }
            $seen[$key] = true;
        }
    }

    private function validateUniqueValue(Validator $validator, array &$seen, string $table, string $field, mixed $value, ?int $offeringId, int $index, string $pathPrefix = ''): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $key = Str::lower((string) $value);
        $path = "offerings.{$index}.{$pathPrefix}{$field}";
        if (isset($seen[$key])) {
            $validator->errors()->add($path, 'This value is repeated in another edition.');
            return;
        }
        $seen[$key] = true;

        $exists = $table === 'offerings'
            ? Offering::query()->whereRaw('LOWER('.$field.') = ?', [$key])->when($offeringId, fn ($query) => $query->whereKeyNot($offeringId))->exists()
            : BookEdition::query()->where($field, $value)->when($offeringId, fn ($query) => $query->where('offering_id', '!=', $offeringId))->exists();
        if ($exists) {
            $validator->errors()->add($path, 'This value is already used by another edition.');
        }
    }

    private function validIsbn(string $isbn): bool
    {
        if (strlen($isbn) === 10) {
            $sum = 0;
            foreach (str_split($isbn) as $index => $character) {
                $value = $character === 'X' && $index === 9 ? 10 : (ctype_digit($character) ? (int) $character : -100);
                $sum += (10 - $index) * $value;
            }
            return $sum % 11 === 0;
        }
        if (strlen($isbn) === 13 && ctype_digit($isbn)) {
            $sum = 0;
            foreach (str_split(substr($isbn, 0, 12)) as $index => $character) {
                $sum += (int) $character * ($index % 2 === 0 ? 1 : 3);
            }
            return (10 - ($sum % 10)) % 10 === (int) $isbn[12];
        }
        return false;
    }
}
