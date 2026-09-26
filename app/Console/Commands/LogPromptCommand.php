<?php

namespace App\Console\Commands;

use App\Support\PromptLogger;
use Illuminate\Console\Command;

class LogPromptCommand extends Command
{
    protected $signature = 'prompts:log
                            {prompt?* : The prompt text to append}
                            {--module= : Log as a module summary}
                            {--context= : Optional context note}';

    protected $description = 'Append an entry to prompt.log (never overwrites).';

    public function handle(): int
    {
        $prompts = (array) $this->argument('prompt');

        if ($prompts === []) {
            $this->error('No prompt text supplied.');

            return self::FAILURE;
        }

        $prompt = trim(implode(' ', $prompts));

        if ($this->option('module') !== null) {
            PromptLogger::module($this->option('module'), $prompt);
        } else {
            PromptLogger::log($prompt, $this->option('context'));
        }

        $this->info('Appended to '.PromptLogger::path());

        return self::SUCCESS;
    }
}
