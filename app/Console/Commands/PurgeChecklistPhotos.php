<?php

namespace App\Console\Commands;

use App\Models\ChecklistRunItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes checklist proof photos older than 24 hours, both the file on
 * disk and the path string on the row. Storage on this server is tight
 * — the audit trail (timestamp, who checked, note) is kept; only the
 * heavy image bytes go.
 *
 * Schedule entry in app/Console/Kernel.php (or routes/console.php on
 * Laravel 12) runs this hourly. Idempotent + safe to re-run.
 */
class PurgeChecklistPhotos extends Command
{
    protected $signature = 'checklist:purge-photos {--dry-run : Show what would be deleted without touching anything}';
    protected $description = 'Delete checklist run-item photos older than 24 hours.';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subHours(24);
        $dryRun = (bool) $this->option('dry-run');

        $items = ChecklistRunItem::query()
            ->whereNotNull('photo_path')
            ->where('checked_at', '<=', $cutoff)
            ->get(['id', 'photo_path', 'checked_at']);

        if ($items->isEmpty()) {
            $this->info('No checklist photos older than 24h. Nothing to purge.');
            return self::SUCCESS;
        }

        $this->info("Found {$items->count()} item(s) with photos older than 24h.");
        if ($dryRun) {
            foreach ($items as $i) {
                $this->line("  would delete #{$i->id} → {$i->photo_path} (checked {$i->checked_at})");
            }
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($items as $i) {
            try {
                if (Storage::disk('public')->exists($i->photo_path)) {
                    Storage::disk('public')->delete($i->photo_path);
                }
                $i->update(['photo_path' => null]);
                $deleted++;
            } catch (\Throwable $e) {
                $this->warn("  failed on item #{$i->id}: {$e->getMessage()}");
            }
        }
        $this->info("Purged $deleted photo(s).");
        return self::SUCCESS;
    }
}
