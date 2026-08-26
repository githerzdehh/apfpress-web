<?php

namespace Database\Seeders;

use App\Services\WooCommerceImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/woocommerce-products.json');
        $products = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($products)) {
            throw new RuntimeException('The catalog seed file must contain a JSON array.');
        }

        $summary = app(WooCommerceImporter::class)->import($products, config('apf.import_download_media'));
        $this->command?->info(sprintf(
            'Catalog imported: %d created, %d updated.',
            $summary['created'],
            $summary['updated'],
        ));
    }
}
