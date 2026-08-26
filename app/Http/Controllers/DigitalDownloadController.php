<?php

namespace App\Http\Controllers;

use App\Models\DigitalEntitlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalDownloadController extends Controller
{
    public function __invoke(Request $request, DigitalEntitlement $entitlement): StreamedResponse
    {
        abort_unless($entitlement->user_id === $request->user()->id && $entitlement->isAccessible(), 403, 'This download is unavailable or has expired.');
        $asset = $entitlement->asset;
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        DB::transaction(function () use ($request, $entitlement): void {
            $entitlement->increment('download_count');
            DB::table('download_events')->insert([
                'digital_entitlement_id' => $entitlement->id,
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'downloaded_at' => now(),
            ]);
        });

        return Storage::disk($asset->disk)->download($asset->path, $asset->file_name);
    }
}
