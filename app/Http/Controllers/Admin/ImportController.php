<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WooCommerceImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportController extends Controller
{
    public function preview(Request $request, WooCommerceImporter $importer): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:json,csv,txt', 'max:20480']]);
        $file = $request->file('file');
        $raw = strtolower($file->getClientOriginalExtension()) === 'csv'
            ? $this->parseCsv($file->getRealPath())
            : json_decode((string) file_get_contents($file->getRealPath()), true, flags: JSON_THROW_ON_ERROR);
        $raw = isset($raw['products']) && is_array($raw['products']) ? $raw['products'] : $raw;
        if (! is_array($raw) || ($raw !== [] && ! is_array($raw[0] ?? null))) {
            throw new RuntimeException('The WooCommerce export must contain an array of products.');
        }
        $products = isset($raw[0]['source_id']) ? $raw : $importer->normalizeStoreApi($raw);

        $batchId = DB::transaction(function () use ($request, $file, $products): int {
            $batchId = DB::table('import_batches')->insertGetId([
                'user_id' => $request->user()->id, 'source' => 'woocommerce_upload',
                'file_name' => $file->getClientOriginalName(), 'checksum' => hash_file('sha256', $file->getRealPath()),
                'status' => 'previewed', 'total_rows' => count($products),
                'summary' => json_encode($this->previewSummary($products)), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($products as $index => $product) {
                DB::table('import_rows')->insert([
                    'import_batch_id' => $batchId, 'row_number' => $index + 1,
                    'source_id' => (string) ($product['source_id'] ?? ''), 'action' => 'merge',
                    'source_data' => json_encode($product, JSON_THROW_ON_ERROR),
                    'mapped_data' => json_encode($product, JSON_THROW_ON_ERROR),
                    'messages' => json_encode($this->warnings($product)), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $batchId;
        });

        return response()->json([
            'batch_id' => $batchId, 'summary' => $this->previewSummary($products),
            'rows' => array_slice(array_map(fn ($product) => $product + ['warnings' => $this->warnings($product)], $products), 0, 50),
        ], 201);
    }

    public function commit(Request $request, int $batch, WooCommerceImporter $importer): JsonResponse
    {
        $record = DB::table('import_batches')->where('id', $batch)->where('status', 'previewed')->first();
        abort_unless($record, 404);
        $request->validate(['download_images' => ['sometimes', 'boolean']]);
        DB::table('import_batches')->where('id', $batch)->update(['status' => 'processing', 'updated_at' => now()]);

        try {
            $products = DB::table('import_rows')->where('import_batch_id', $batch)->orderBy('row_number')->pluck('mapped_data')
                ->map(fn ($row) => json_decode($row, true, flags: JSON_THROW_ON_ERROR))->all();
            $summary = $importer->import($products, $request->boolean('download_images'));
            DB::table('import_batches')->where('id', $batch)->update([
                'status' => 'completed', 'created_rows' => $summary['created'], 'updated_rows' => $summary['updated'],
                'summary' => json_encode($summary), 'updated_at' => now(),
            ]);
            DB::table('import_rows')->where('import_batch_id', $batch)->update(['action' => 'merge', 'updated_at' => now()]);
        } catch (Throwable $exception) {
            DB::table('import_batches')->where('id', $batch)->update(['status' => 'failed', 'error' => $exception->getMessage(), 'updated_at' => now()]);
            throw $exception;
        }

        return response()->json(['batch_id' => $batch, 'status' => 'completed', 'summary' => $summary]);
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = fgetcsv($handle) ?: [];
        $products = [];
        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($headers, array_pad($row, count($headers), null));
            $categories = array_filter(array_map('trim', explode(',', (string) ($record['Categories'] ?? ''))));
            $stock = $record['Stock'] ?? $record['Stock quantity'] ?? null;
            $isbn = preg_replace('/[^0-9X]/i', '', (string) ($record['ISBN'] ?? $record['ISBN-13'] ?? ''));
            $products[] = [
                'source_id' => (string) ($record['ID'] ?? count($products) + 1),
                'slug' => $record['Slug'] ?? \Illuminate\Support\Str::slug((string) ($record['Name'] ?? 'book')),
                'title' => html_entity_decode(strip_tags((string) ($record['Name'] ?? 'Untitled'))),
                'summary' => html_entity_decode(strip_tags((string) ($record['Short description'] ?? ''))),
                'description' => html_entity_decode(strip_tags((string) ($record['Description'] ?? ''))),
                'author' => trim((string) ($record['Author'] ?? '')),
                'kind' => str_contains(strtolower((string) ($record['Categories'] ?? '')), 'ebook') ? 'ebook' : 'print_book',
                'price_amount' => is_numeric($record['Regular price'] ?? null) ? (int) round(((float) $record['Regular price']) * 100) : null,
                'currency' => 'CAD', 'purchasable' => ($record['Published'] ?? '1') === '1',
                'stock_quantity' => is_numeric($stock) ? (int) $stock : null,
                'in_stock' => strtolower((string) ($record['In stock?'] ?? '1')) !== '0',
                'sku' => trim((string) ($record['SKU'] ?? '')) ?: null,
                'isbn_10' => strlen($isbn) === 10 ? strtoupper($isbn) : null,
                'isbn_13' => strlen($isbn) === 13 ? strtoupper($isbn) : null,
                'categories' => array_map(fn ($name) => ['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)], $categories),
                'image_url' => trim(explode(',', (string) ($record['Images'] ?? ''))[0]) ?: null,
                'source_url' => null,
            ];
        }
        fclose($handle);

        return $products;
    }

    private function previewSummary(array $products): array
    {
        $warnings = [];
        foreach ($products as $product) {
            foreach ($this->warnings($product) as $warning) {
                $warnings[$warning] = ($warnings[$warning] ?? 0) + 1;
            }
        }

        return ['rows' => count($products), 'warnings' => $warnings];
    }

    private function warnings(array $product): array
    {
        return array_values(array_filter([
            empty($product['author']) ? 'missing_author' : null,
            empty($product['isbn_10']) && empty($product['isbn_13']) ? 'missing_isbn' : null,
            empty($product['publication_date']) ? 'missing_publication_date' : null,
            ! isset($product['price_amount']) ? 'missing_price' : null,
            ! isset($product['stock_quantity']) ? 'missing_stock_count' : null,
        ]));
    }
}
