<?php

namespace App\Jobs;

use App\Models\TemporaryUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DeleteStaleTemporaryUploadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Delete temporary uploads older than one day.
     */
    public function handle()
    {
        TemporaryUpload::where('created_at', '<', now()->subDay())
        // TemporaryUpload::where('created_at', '<', now()->subMinute())
            ->chunkById(100, function ($temporaryUploads) {
                foreach ($temporaryUploads as $temporaryUpload) {
                    $disk = $temporaryUpload->disk ?: 'public';
                    Storage::disk($disk)->delete($temporaryUpload->path);
                    $temporaryUpload->delete();
                }
            });
    }
}
