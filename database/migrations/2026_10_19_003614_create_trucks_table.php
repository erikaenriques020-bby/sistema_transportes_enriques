<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('plate')->unique();
            $table->string('gps_asset_identifier')->nullable()->index();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('fleet_people')->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->unique()->constrained('fleet_people')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
