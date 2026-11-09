<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->restrictOnDelete();
            $table->dateTime('observed_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->foreignId('fleet_import_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('source_row')->nullable();
            $table->timestamps();
            $table->unique(['truck_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_positions');
    }
};
