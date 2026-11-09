<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_conductor_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->restrictOnDelete();
            $table->foreignId('old_driver_id')->nullable()->constrained('fleet_people')->nullOnDelete();
            $table->foreignId('new_driver_id')->nullable()->constrained('fleet_people')->nullOnDelete();
            $table->string('old_name')->nullable();
            $table->string('new_name')->nullable();
            $table->dateTime('observed_at')->nullable();
            $table->foreignId('fleet_import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        foreach (DB::table('trucks')->whereNotNull('conductor_name')->get() as $truck) {
            DB::table('truck_conductor_changes')->insert(['truck_id' => $truck->id, 'new_driver_id' => $truck->driver_id, 'new_name' => $truck->conductor_name, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_conductor_changes');
    }
};
