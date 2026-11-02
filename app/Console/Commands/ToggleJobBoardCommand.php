<?php

namespace App\Console\Commands;

use App\Models\JobBoard;
use Illuminate\Console\Command;

class ToggleJobBoardCommand extends Command
{
    protected $signature = 'job-boards:toggle {host : The board\'s host, as job-boards:list shows it}';

    protected $description = 'Switch a job board on or off';

    public function handle(): int
    {
        $board = JobBoard::query()
            ->where('host', $this->argument('host'))
            ->first();

        if (! $board) {
            $this->error('No board with that host. See job-boards:list.');

            return self::FAILURE;
        }

        $board->update(['enabled' => ! $board->enabled]);

        $this->info("{$board->name} switched ".($board->enabled ? 'on' : 'off').'.');

        return self::SUCCESS;
    }
}
