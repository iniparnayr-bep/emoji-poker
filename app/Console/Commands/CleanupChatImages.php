<?php

namespace App\Console\Commands;

use App\Models\ChatImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupChatImages extends Command
{
    protected $signature   = 'images:cleanup';
    protected $description = 'Delete chat images that have exceeded their 1-hour TTL';

    public function handle(): int
    {
        $expired = ChatImage::where('expires_at', '<=', now())->get();

        $count = 0;
        foreach ($expired as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
            $count++;
        }

        $this->info("Cleaned up {$count} expired chat image(s).");
        return Command::SUCCESS;
    }
}
