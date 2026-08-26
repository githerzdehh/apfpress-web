<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\DigitalAsset;
use App\Models\MediaAsset;
use App\Models\Offering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MediaController extends Controller
{
    public function cover(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        $request->validate(['cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'], 'alt_text' => ['nullable', 'string', 'max:255']]);
        $file = $request->file('cover');
        $path = $file->store('catalog/covers', 'public');
        $asset = MediaAsset::query()->create([
            'disk' => 'public', 'path' => $path, 'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(), 'alt_text' => $request->input('alt_text') ?: $catalogItem->title.' book cover',
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);
        DB::table('catalog_item_media')->where('catalog_item_id', $catalogItem->id)->where('role', 'cover')->delete();
        $catalogItem->media()->attach($asset->id, ['role' => 'cover', 'position' => 0]);

        return response()->json(['id' => $asset->id, 'url' => $asset->url, 'alt_text' => $asset->alt_text], 201);
    }

    public function digital(Request $request, Offering $offering): JsonResponse
    {
        abort_unless(in_array($offering->kind, ['ebook', 'digital_product'], true), 422, 'Digital files can only be attached to digital offerings.');
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,epub', 'max:102400'],
            'access_duration_days' => ['nullable', 'integer', 'between:1,3650'],
        ]);
        $file = $request->file('file');
        $path = $file->store('digital/'.$offering->id, 'local');
        $asset = DigitalAsset::query()->where('offering_id', $offering->id)->where('active', true)->first();
        $asset ??= new DigitalAsset(['offering_id' => $offering->id, 'version' => 0, 'active' => true]);
        $asset->fill([
            'disk' => 'local', 'path' => $path, 'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()), 'version' => $asset->version + 1, 'active' => true,
        ])->save();
        $offering->update([
            'access_duration_days' => $request->integer('access_duration_days') ?: config('apf.digital_access_days'),
            'purchase_mode' => $offering->price_amount !== null && $offering->sku ? 'online' : 'inquiry',
        ]);

        return response()->json(['id' => $asset->id, 'file_name' => $asset->file_name, 'version' => $asset->version, 'purchase_mode' => $offering->fresh()->purchase_mode], 201);
    }
}
