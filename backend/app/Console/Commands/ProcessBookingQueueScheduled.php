<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookingQueueService;
use App\Models\Trip;
use Illuminate\Support\Facades\Log;

class ProcessBookingQueueScheduled extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:process-scheduled {--limit=100 : Maximum number of trips to process}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically process booking queues for trips with available seats';

    protected $queueService;

    public function __construct(BookingQueueService $queueService)
    {
        parent::__construct();
        $this->queueService = $queueService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("Processing booking queues for trips with available seats...");
        
        // Get trips that have pending queue items and available seats
        $tripsWithQueues = Trip::whereHas('bookingQueues', function ($query) {
            $query->where('status', 'pending');
        })
        ->where('available_seats', '>', 0)
        ->where('status', 'in-progress')
        ->limit($limit)
        ->get();

        if ($tripsWithQueues->isEmpty()) {
            $this->info('No trips found with pending queues and available seats.');
            return;
        }

        $totalProcessed = 0;
        $totalSuccessful = 0;
        $totalFailed = 0;

        foreach ($tripsWithQueues as $trip) {
            try {
                $this->info("Processing queue for trip ID: {$trip->id} (Origin: {$trip->origin} → Destination: {$trip->destination})");
                
                $results = $this->queueService->processQueueForTrip($trip);
                
                $totalProcessed += $results['processed'];
                $totalSuccessful += $results['successful'];
                $totalFailed += $results['failed'];
                
                if ($results['processed'] > 0) {
                    $this->info("  → Processed: {$results['processed']}, Successful: {$results['successful']}, Failed: {$results['failed']}");
                }
                
            } catch (\Exception $e) {
                $this->error("Error processing trip {$trip->id}: " . $e->getMessage());
                Log::error('Scheduled queue processing error', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Total processed: {$totalProcessed}");
        $this->info("Successful: {$totalSuccessful}");
        $this->info("Failed: {$totalFailed}");
        
        // Clean up old queue items
        $cleanedUp = $this->queueService->cleanupOldQueueItems(7); // 7 days old
        if ($cleanedUp > 0) {
            $this->info("Cleaned up {$cleanedUp} old queue items");
        }
    }
}
