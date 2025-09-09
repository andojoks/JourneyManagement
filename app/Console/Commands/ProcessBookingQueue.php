<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookingQueueService;

class ProcessBookingQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:process-bookings {--trip-id= : Process queue for specific trip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending booking requests in the queue';

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
        $tripId = $this->option('trip-id');

        if ($tripId) {
            $this->info("Processing booking queue for trip ID: {$tripId}");
            $results = $this->queueService->processQueueForTrip(\App\Models\Trip::findOrFail($tripId));
        } else {
            $this->info('Processing all booking queues...');
            $results = $this->queueService->processAllQueues();
        }

        $this->displayResults($results);
    }

    private function displayResults(array $results)
    {
        if (isset($results['total_processed'])) {
            // Bulk processing results
            $this->info("Total processed: {$results['total_processed']}");
            $this->info("Successful: {$results['total_successful']}");
            $this->info("Failed: {$results['total_failed']}");

            if (!empty($results['trip_results'])) {
                $this->table(
                    ['Trip ID', 'Processed', 'Successful', 'Failed'],
                    collect($results['trip_results'])->map(function ($tripResult, $tripId) {
                        return [
                            $tripId,
                            $tripResult['processed'],
                            $tripResult['successful'],
                            $tripResult['failed'],
                        ];
                    })
                );
            }
        } else {
            // Single trip results
            $this->info("Processed: {$results['processed']}");
            $this->info("Successful: {$results['successful']}");
            $this->info("Failed: {$results['failed']}");

            if (!empty($results['errors'])) {
                $this->error('Errors encountered:');
                foreach ($results['errors'] as $error) {
                    $this->error("Queue ID {$error['queue_id']}: {$error['error']}");
                }
            }
        }
    }
}
