<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropUnique('trucks_driver_id_unique');
            $table->dateTime('driver_observed_at')->nullable();
            $table->dateTime('driver_recorded_at')->nullable();
            $table->foreignId('driver_source_import_id')->nullable()->constrained('fleet_imports')->nullOnDelete();
        });
        Schema::table('fleet_imports', function (Blueprint $table) {
            $table->dropUnique('fleet_imports_sha256_unique');
            $table->string('kind')->default('trucks');
            $table->unique(['sha256', 'kind']);
        });
        // Preserve the current names as an independent roster, without asserting identity links.
        $known = DB::table('fleet_people')->pluck('name')->map(fn ($name) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($name))))->flip();
        foreach (DB::table('trucks')->whereNotNull('conductor_name')->get() as $truck) {
            $name = trim($truck->conductor_name);
            $key = mb_strtoupper(preg_replace('/\s+/u', ' ', $name));
            if ($name !== '' && ! $known->has($key)) {
                DB::table('fleet_people')->insert(['name' => $name, 'is_owner' => false, 'is_driver' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                $known->put($key, true);
            }
        }
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_source_import_id');
            $table->dropColumn(['driver_observed_at', 'driver_recorded_at']);
        });
        Schema::table('fleet_imports', function (Blueprint $table) {
            $table->dropUnique(['sha256', 'kind']);
            $table->dropColumn('kind');
        });
        // Do not reinstate the old unique driver constraint: a person may have several observed plates.
    }
};
