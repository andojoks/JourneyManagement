<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;

class CacheManagementCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:manage {action} {--force : Force the action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage application caches (warm, clear, stats)';

    /**
     * Execute the console command.
     */
    public function handle(CacheService $cacheService)
    {
        $action = $this->argument('action');
        $force = $this->option('force');

        switch ($action) {
            case 'warm':
                $this->warmCaches($cacheService);
                break;
                
            case 'clear':
                $this->clearCaches($cacheService, $force);
                break;
                
            case 'stats':
                $this->showCacheStats($cacheService);
                break;
                
            case 'invalidate':
                $this->invalidateSpecificCaches($cacheService);
                break;
                
            default:
                $this->error('Invalid action. Available actions: warm, clear, stats, invalidate');
                return 1;
        }

        return 0;
    }

    /**
     * Warm up caches
     */
    private function warmCaches(CacheService $cacheService): void
    {
        $this->info('🔥 Warming up caches...');
        
        $progressBar = $this->output->createProgressBar(4);
        $progressBar->start();

        try {
            // Warm up popular routes
            $this->line('  📍 Warming up popular routes...');
            $cacheService->warmUpCaches();
            $progressBar->advance();

            // Warm up recent trips
            $this->line('  🚗 Warming up recent trips...');
            $progressBar->advance();

            // Warm up available trips
            $this->line('  ✅ Warming up available trips...');
            $progressBar->advance();

            // Warm up pricing data
            $this->line('  💰 Warming up pricing data...');
            $progressBar->advance();

            $progressBar->finish();
            $this->newLine();
            $this->info('✅ Cache warming completed successfully!');
            
        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error('❌ Cache warming failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear all caches
     */
    private function clearCaches(CacheService $cacheService, bool $force): void
    {
        if (!$force) {
            if (!$this->confirm('Are you sure you want to clear all caches? This will impact performance temporarily.')) {
                $this->info('Cache clearing cancelled.');
                return;
            }
        }

        $this->info('🧹 Clearing all caches...');
        
        try {
            $cacheService->clearAllCaches();
            $this->info('✅ All caches cleared successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to clear caches: ' . $e->getMessage());
        }
    }

    /**
     * Show cache statistics
     */
    private function showCacheStats(CacheService $cacheService): void
    {
        $this->info('📊 Cache Statistics');
        $this->line('==================');

        try {
            $stats = $cacheService->getCacheStats();
            
            if (isset($stats['error'])) {
                $this->error('❌ Failed to get cache stats: ' . $stats['error']);
                return;
            }

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Memory Used', $stats['memory_used']],
                    ['Connected Clients', $stats['connected_clients']],
                    ['Total Commands Processed', number_format($stats['total_commands_processed'])],
                    ['Keyspace Hits', number_format($stats['keyspace_hits'])],
                    ['Keyspace Misses', number_format($stats['keyspace_misses'])],
                    ['Hit Rate', $stats['hit_rate']],
                ]
            );

            // Show hit rate status
            $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
            if ($hitRate >= 80) {
                $this->info('🎯 Excellent cache hit rate!');
            } elseif ($hitRate >= 60) {
                $this->warn('⚠️  Good cache hit rate, but could be improved.');
            } else {
                $this->error('❌ Low cache hit rate. Consider warming up caches.');
            }

        } catch (\Exception $e) {
            $this->error('❌ Failed to get cache statistics: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate specific caches
     */
    private function invalidateSpecificCaches(CacheService $cacheService): void
    {
        $this->info('🗑️  Cache Invalidation Options');
        $this->line('=============================');

        $options = [
            'trips' => 'Invalidate all trip-related caches',
            'pricing' => 'Invalidate all pricing caches',
            'routes' => 'Invalidate all route caches',
            'waypoints' => 'Invalidate all waypoint caches',
            'all' => 'Invalidate all application caches',
        ];

        $choice = $this->choice('Which caches would you like to invalidate?', array_keys($options));

        try {
            switch ($choice) {
                case 'trips':
                    $this->invalidatePatternCaches('trips:*');
                    $this->invalidatePatternCaches('available_trips:*');
                    $this->invalidatePatternCaches('user_trips:*');
                    $this->info('✅ Trip caches invalidated!');
                    break;

                case 'pricing':
                    $this->invalidatePatternCaches('pricing:*');
                    $this->invalidatePatternCaches('bulk_pricing:*');
                    $this->info('✅ Pricing caches invalidated!');
                    break;

                case 'routes':
                    $this->invalidatePatternCaches('route:*');
                    $this->invalidatePatternCaches('route_cities:*');
                    $this->info('✅ Route caches invalidated!');
                    break;

                case 'waypoints':
                    $this->invalidatePatternCaches('waypoints:*');
                    $this->invalidatePatternCaches('waypoint:*');
                    $this->info('✅ Waypoint caches invalidated!');
                    break;

                case 'all':
                    $cacheService->clearAllCaches();
                    $this->info('✅ All caches invalidated!');
                    break;
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to invalidate caches: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to invalidate pattern-based caches
     */
    private function invalidatePatternCaches(string $pattern): void
    {
        try {
            $redis = \Cache::getRedis();
            $keys = $redis->keys($pattern);
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to invalidate cache pattern: {$pattern}", ['error' => $e->getMessage()]);
        }
    }
}
