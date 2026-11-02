<?php

namespace App\Console\Commands;

use App\Models\JobBoard;
use Illuminate\Console\Command;

class ListJobBoardsCommand extends Command
{
    protected $signature = 'job-boards:list';

    protected $description = 'Show the job boards and how reading them is going';

    public function handle(): int
    {
        $this->table(
            ['Name', 'Host', 'Reader', 'On', 'Jobs', 'Last listed', 'Last error'],
            JobBoard::query()
                ->withCount('postings')
                ->orderBy('name')
                ->get()
                ->map(fn (JobBoard $board) => [
                    $board->name,
                    $board->host,
                    $board->reader,
                    $board->enabled ? 'yes' : 'no',
                    $board->postings_count,
                    $board->last_listed_at?->diffForHumans() ?? 'never',
                    $board->last_error ?? '',
                ])
                ->all(),
        );

        return self::SUCCESS;
    }
}
