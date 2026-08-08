<?php

namespace Xn\Orchestrator\Commands;

use Illuminate\Console\Command;

class OrchestratorCommand extends Command
{
    public $signature = 'xn-orchestrator';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
