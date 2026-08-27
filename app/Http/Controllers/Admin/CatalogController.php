<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookDetail;
use App\Models\BookEdition;
use App\Models\CatalogItem;
use App\Models\Contributor;
use App\Models\Inventory;
use App\Models\Offering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = CatalogItem::query()->with(['contributors', 'offerings.bookEdition', 'offerings.inventory'])
            ->when($request->string('q')->toString(), fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('updated_at')->paginate(25);

        return response()->json($items);
    }

    public function show(CatalogItem $catalogItem): JsonResponse
    {
        return response()->json($catalogItem->load(['contributors', 'categories', 'media', 'bookDetails', 'offerings.bookEdition', 'offerings.inventory']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateItem($request);
        $item = DB::transaction(fn () => $this->persist(new CatalogItem, $data, $request->user()->id));

        return response()->json($item, 201);
    }

    public function update(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        $data = $this->validateItem($request, $catalogItem);
        $item = DB::transaction(fn () => $this->persist($catalogItem, $data, $request->user()->id));

        return response()->json($item);
    }

    private function validateItem(Request $request, ?CatalogItem $item = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['book', 'product', 'service'])],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('catalog_items')->ignore($item)],
            'title' => ['required', 'string', 'max:255'], 'subtitle' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'], 'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])], 'featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:70'], 'seo_description' => ['nullable', 'string', 'max:320'],
            'author' => ['nullable', 'string', 'max:255'],
            'offering.kind' => ['nullable', Rule::in(['print_book', 'ebook', 'physical_product', 'digital_product', 'service'])],
            'offering.name' => ['required_with:offering.kind', 'nullable', 'string', 'max:120'], 'offering.sku' => ['required_if:offering.purchase_mode,online', 'nullable', 'string', 'max:120', Rule::unique('offerings', 'sku')->ignore($item?->offerings()->first())],
            'offering.price_amount' => ['required_if:offering.purchase_mode,online', 'nullable', 'integer', 'min:0'], 'offering.purchase_mode' => ['nullable', Rule::in(['online', 'inquiry', 'unavailable'])],
            'offering.format' => ['nullable', Rule::in(['paperback', 'hardcover', 'pdf', 'epub', 'other'])],
            'offering.isbn_10' => ['nullable', 'string', 'size:10'], 'offering.isbn_13' => ['nullable', 'string', 'size:13'],
            'offering.publication_date' => ['nullable', 'date'], 'offering.page_count' => ['nullable', 'integer', 'min:1'],
            'offering.on_hand' => ['nullable', 'integer', 'min:0'], 'offering.track_inventory' => ['boolean'],
        ]);
    }

    private function persist(CatalogItem $item, array $data, int $userId): CatalogItem
    {
        $before = $item->exists ? $item->toArray() : null;
        $offeringData = $data['offering'] ?? [];
        unset($data['offering'], $data['author']);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['published_at'] = $data['status'] === 'published' ? ($item->published_at ?: now()) : null;
        $data['metadata_flags'] = $this->metadataFlags($offeringData, request()->input('author'));
        $item->fill($data)->save();

        if ($item->type === 'book') {
            BookDetail::query()->firstOrCreate(['catalog_item_id' => $item->id], ['publisher' => 'APF Press']);
        }

        if ($author = trim((string) request()->input('author'))) {
            $contributor = Contributor::query()->firstOrCreate(['slug' => Str::slug($author)], ['name' => $author]);
            $item->contributors()->sync([$contributor->id => ['role' => 'author', 'position' => 0]]);
        }

        if (! empty($offeringData['kind'])) {
            $offering = Offering::query()->updateOrCreate(
                ['catalog_item_id' => $item->id, 'kind' => $offeringData['kind']],
                collect($offeringData)->only(['name', 'sku', 'price_amount', 'purchase_mode'])->all() + ['currency' => config('apf.currency'), 'tax_class' => $item->type === 'book' ? 'books' : 'standard', 'active' => true],
            );
            if ($item->type === 'book') {
                BookEdition::query()->updateOrCreate(['offering_id' => $offering->id], collect($offeringData)->only(['format', 'isbn_10', 'isbn_13', 'publication_date', 'page_count'])->all() + ['format' => $offeringData['format'] ?? 'other']);
            }
            Inventory::query()->updateOrCreate(['offering_id' => $offering->id], [
                'on_hand' => $offeringData['on_hand'] ?? 0, 'track_inventory' => $offeringData['track_inventory'] ?? false,
            ]);
        }

        DB::table('audit_logs')->insert([
            'user_id' => $userId, 'action' => $before ? 'catalog.updated' : 'catalog.created',
            'auditable_type' => CatalogItem::class, 'auditable_id' => $item->id,
            'before' => $before ? json_encode($before) : null, 'after' => json_encode($item->fresh()->toArray()),
            'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $item->fresh(['contributors', 'offerings.bookEdition', 'offerings.inventory']);
    }

    private function metadataFlags(array $offering, ?string $author): array
    {
        return array_values(array_filter([
            trim((string) $author) === '' ? 'missing_author' : null,
            empty($offering['isbn_10']) && empty($offering['isbn_13']) ? 'missing_isbn' : null,
            empty($offering['publication_date']) ? 'missing_publication_date' : null,
            ! isset($offering['price_amount']) ? 'missing_price' : null,
            ! isset($offering['on_hand']) ? 'missing_stock_count' : null,
        ]));
    }
}
