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
        Schema::create('route_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_waypoint_id')->constrained('waypoints')->onDelete('cascade');
            $table->foreignId('to_waypoint_id')->constrained('waypoints')->onDelete('cascade');
            $table->decimal('distance', 8, 2); // in kilometers
            $table->integer('estimated_time'); // in minutes
            $table->decimal('base_price', 8, 2); // base price for this segment
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['from_waypoint_id', 'to_waypoint_id']);
            $table->index(['from_waypoint_id', 'to_waypoint_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_segments');
    }
};
