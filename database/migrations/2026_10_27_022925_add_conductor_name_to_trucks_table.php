<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $trucks = DB::table('trucks')->get();
        $normalized = [];
        foreach ($trucks as $truck) {
            $plate = mb_strtoupper(preg_replace('/\s+/u', ' ', trim($truck->plate)));
            if (isset($normalized[$plate])) {
                throw new RuntimeException('Hay placas equivalentes en la base. Conciliarlas antes de aplicar esta migración.');
            }
            $normalized[$plate] = true;
        }
        Schema::table('trucks', function (Blueprint $table) {
            $table->string('conductor_name')->nullable();
        });
        foreach ($trucks as $truck) {
            DB::table('trucks')->where('id', $truck->id)->update([
                'plate' => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($truck->plate))),
                'conductor_name' => $truck->driver_id ? DB::table('fleet_people')->where('id', $truck->driver_id)->value('name') : null,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn('conductor_name');
        });
    }
};
