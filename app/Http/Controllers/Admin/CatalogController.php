<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCatalogItemRequest;
use App\Http\Resources\AdminCatalogItemResource;
use App\Models\BookDetail;
use App\Models\BookEdition;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Contributor;
use App\Models\Inventory;
use App\Models\Offering;
use App\Services\CatalogMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    private const RELATIONS = [
        'contributors', 'categories', 'media', 'bookDetails',
        'offerings.bookEdition', 'offerings.inventory', 'offerings.digitalAssets',
    ];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $items = CatalogItem::query()->where('type', 'book')->with(self::RELATIONS)
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhereHas('contributors', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('updated_at')->paginate(25)->withQueryString();

        return AdminCatalogItemResource::collection($items)->response();
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'contributors' => Contributor::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'contributor_roles' => ['author', 'editor', 'translator', 'illustrator', 'foreword', 'contributor'],
        ]);
    }

    public function show(CatalogItem $catalogItem): JsonResponse
    {
        abort_unless($catalogItem->type === 'book', 404);

        return (new AdminCatalogItemResource($catalogItem->load(self::RELATIONS)))->response();
    }

    public function store(SaveCatalogItemRequest $request, CatalogMetadata $metadata): JsonResponse
    {
        $item = DB::transaction(fn () => $this->persist(new CatalogItem, $request->validated(), $request->user()->id, $metadata));

        return (new AdminCatalogItemResource($item))->response()->setStatusCode(201);
    }

    public function update(SaveCatalogItemRequest $request, CatalogItem $catalogItem, CatalogMetadata $metadata): JsonResponse
    {
        abort_unless($catalogItem->type === 'book', 404);
        $item = DB::transaction(fn () => $this->persist($catalogItem, $request->validated(), $request->user()->id, $metadata));

        return (new AdminCatalogItemResource($item))->response();
    }

    private function persist(CatalogItem $item, array $data, int $userId, CatalogMetadata $metadata): CatalogItem
    {
        $before = $item->exists
            ? (new AdminCatalogItemResource($item->load(self::RELATIONS)))->resolve()
            : null;
        $bookDetails = $data['book_details'];
        $contributors = $data['contributors'];
        $categories = $data['categories'];
        $offerings = $data['offerings'];
        unset($data['book_details'], $data['contributors'], $data['categories'], $data['offerings']);

        $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['title'], $item->exists ? $item->id : null);
        $data['published_at'] = $data['status'] === 'published' ? ($item->published_at ?: now()) : null;
        $item->fill($data)->save();
        BookDetail::query()->updateOrCreate(['catalog_item_id' => $item->id], $bookDetails);

        $contributorSync = [];
        foreach ($contributors as $position => $input) {
            $contributor = ! empty($input['id'])
                ? Contributor::query()->findOrFail($input['id'])
                : Contributor::query()->firstOrCreate(
                    ['slug' => Str::slug($input['name']) ?: 'contributor-'.Str::lower(Str::random(8))],
                    ['name' => $input['name']],
                );
            $contributorSync[$contributor->id] = ['role' => $input['role'], 'position' => $position];
        }
        $item->contributors()->sync($contributorSync);

        $categoryIds = collect($categories)->map(function (array $input): int {
            if (! empty($input['id'])) {
                return (int) $input['id'];
            }

            return Category::query()->firstOrCreate(
                ['slug' => Str::slug($input['name']) ?: 'category-'.Str::lower(Str::random(8))],
                ['name' => $input['name']],
            )->id;
        })->all();
        $item->categories()->sync($categoryIds);

        $keptOfferingIds = [];
        foreach ($offerings as $position => $input) {
            $offering = ! empty($input['id'])
                ? $item->offerings()->whereKey($input['id'])->firstOrFail()
                : new Offering(['catalog_item_id' => $item->id]);
            $edition = $input['edition'];
            $inventory = $input['inventory'];
            $offering->fill([
                'position' => $position,
                'kind' => $input['kind'],
                'name' => $input['name'],
                'sku' => $input['sku'] ?? null,
                'price_amount' => $input['price_amount'] ?? null,
                'currency' => config('apf.currency'),
                'purchase_mode' => $input['purchase_mode'],
                'tax_class' => 'books',
                'active' => $input['active'],
                'access_duration_days' => $input['kind'] === 'ebook'
                    ? ($input['access_duration_days'] ?? config('apf.digital_access_days'))
                    : null,
            ])->save();
            $keptOfferingIds[] = $offering->id;

            BookEdition::query()->updateOrCreate(['offering_id' => $offering->id], $edition);
            $savedInventory = Inventory::query()->firstOrNew(['offering_id' => $offering->id]);
            $savedInventory->fill([
                'on_hand' => $inventory['on_hand'],
                'low_stock_threshold' => $inventory['low_stock_threshold'],
                'track_inventory' => $inventory['track_inventory'],
                'allow_backorder' => $inventory['allow_backorder'],
            ])->save();
        }
        $item->offerings()->whereNotIn('id', $keptOfferingIds)->update(['active' => false]);

        $item->load(self::RELATIONS);
        $item->forceFill(['metadata_flags' => $metadata->assess($item)['flags']])->save();
        $item = $item->fresh(self::RELATIONS);
        $after = (new AdminCatalogItemResource($item))->resolve();

        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => $before ? 'catalog.updated' : 'catalog.created',
            'auditable_type' => CatalogItem::class,
            'auditable_id' => $item->id,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $item;
    }

    private function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = Str::slug($title) ?: 'catalogue-title';
        $slug = $base;
        $suffix = 2;
        while (CatalogItem::query()->where('slug', $slug)->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
