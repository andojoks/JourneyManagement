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
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('base_price', 8, 2)->default(0)->after('distance');
            $table->decimal('surge_multiplier', 3, 2)->default(1.00)->after('base_price');
            $table->decimal('final_price', 8, 2)->default(0)->after('surge_multiplier');
            $table->json('route_waypoints')->nullable()->after('final_price'); // Store optimal route waypoints
            $table->integer('priority_score')->default(0)->after('route_waypoints'); // For queue priority
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'surge_multiplier', 'final_price', 'route_waypoints', 'priority_score']);
        });
    }
};
