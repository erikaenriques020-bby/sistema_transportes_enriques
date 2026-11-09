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
        Schema::create('fleet_people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_number')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_driver')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('fleet_people')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_people');
    }
};
