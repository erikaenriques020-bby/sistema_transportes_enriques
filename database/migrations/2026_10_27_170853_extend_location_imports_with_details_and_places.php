<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_positions', function (Blueprint $table) {
            $table->json('details')->nullable();
            $table->json('source_metadata')->nullable();
        });
        Schema::table('gps_import_rows', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->json('payload')->nullable();
        });
        Schema::table('places', function (Blueprint $table) {
            $table->string('import_key', 64)->nullable()->unique();
        });
        $seen = [];
        foreach (DB::table('places')->orderBy('id')->get() as $place) {
            $name = mb_strtoupper(preg_replace('/\s+/u', ' ', trim($place->name)));
            $key = hash('sha256', $name.'|'.number_format((float) $place->latitude, 7, '.', '').'|'.number_format((float) $place->longitude, 7, '.', ''));
            if (! isset($seen[$key])) {
                DB::table('places')->where('id', $place->id)->update(['import_key' => $key]);
                $seen[$key] = true;
            }
        }
    }

    public function down(): void
    {
        Schema::table('gps_positions', fn (Blueprint $table) => $table->dropColumn(['details', 'source_metadata']));
        Schema::table('gps_import_rows', fn (Blueprint $table) => $table->dropColumn(['name', 'payload']));
        Schema::table('places', function (Blueprint $table) {
            $table->dropUnique(['import_key']);
            $table->dropColumn('import_key');
        });
    }
};
