<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offerings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->default(0)->after('catalog_item_id');
        });

        Schema::table('digital_assets', function (Blueprint $table): void {
            $table->boolean('is_current')->default(false)->after('active');
        });

        $positions = [];
        DB::table('offerings')->orderBy('catalog_item_id')->orderBy('id')->get(['id', 'catalog_item_id'])
            ->each(function (object $offering) use (&$positions): void {
                $position = $positions[$offering->catalog_item_id] ?? 0;
                DB::table('offerings')->where('id', $offering->id)->update(['position' => $position]);
                $positions[$offering->catalog_item_id] = $position + 1;
            });

        DB::table('digital_assets')->orderBy('offering_id')->orderBy('created_at')->orderBy('id')
            ->get(['id', 'offering_id', 'active'])->groupBy('offering_id')->each(function ($assets): void {
                $assets->values()->each(function (object $asset, int $position): void {
                    DB::table('digital_assets')->where('id', $asset->id)->update([
                        'version' => $position + 1,
                        'is_current' => false,
                    ]);
                });

                $current = $assets->filter(fn (object $asset): bool => (bool) $asset->active)->last();
                if ($current) {
                    DB::table('digital_assets')->where('id', $current->id)->update(['is_current' => true]);
                }
            });

        Schema::table('offerings', function (Blueprint $table): void {
            $table->index(['catalog_item_id', 'active', 'position'], 'offerings_catalog_active_position_index');
        });

        Schema::table('digital_assets', function (Blueprint $table): void {
            $table->index(['offering_id', 'active', 'is_current'], 'digital_assets_current_index');
            $table->unique(['offering_id', 'version'], 'digital_assets_offering_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('digital_assets', function (Blueprint $table): void {
            $table->dropUnique('digital_assets_offering_version_unique');
            $table->dropIndex('digital_assets_current_index');
            $table->dropColumn('is_current');
        });

        Schema::table('offerings', function (Blueprint $table): void {
            $table->dropIndex('offerings_catalog_active_position_index');
            $table->dropColumn('position');
        });
    }
};
