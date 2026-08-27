<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'catalog_items' => CatalogItem::query()->count(),
            'published_items' => CatalogItem::query()->published()->count(),
            'metadata_issues' => CatalogItem::query()->whereNotNull('metadata_flags')->whereJsonLength('metadata_flags', '>', 0)->count(),
            'open_orders' => Order::query()->whereIn('status', ['paid', 'processing'])->count(),
            'revenue_cad' => Order::query()->where('payment_status', 'paid')->sum('total_amount'),
            'new_inquiries' => DB::table('contact_inquiries')->where('status', 'new')->count(),
            'new_submissions' => DB::table('manuscript_submissions')->where('status', 'new')->count(),
        ]);
    }
}
