<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('plate', 255)->nullable();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('observed_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30);
            $table->string('reason')->nullable();
            $table->unique(['fleet_import_id', 'row_number']);
            $table->index(['fleet_import_id', 'status']);
            $table->index(['fleet_import_id', 'truck_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_import_rows');
    }
};
