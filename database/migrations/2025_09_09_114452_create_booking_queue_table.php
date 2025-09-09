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
        Schema::create('booking_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->integer('seats_requested');
            $table->integer('priority_score')->default(0); // Higher score = higher priority
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('queued_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'priority_score', 'queued_at']);
            $table->index(['trip_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_queue');
    }
};
