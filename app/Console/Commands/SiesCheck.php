<?php

namespace App\Console\Commands;

use App\Services\ReadinessCheck;
use Illuminate\Console\Command;

class SiesCheck extends Command
{
    protected $signature = 'sies:check';

    protected $description = 'Check that this installation is safe to put on the school network';

    public function handle(): int
    {
        $checks = ReadinessCheck::run();
        $symbols = ['ok' => 'OK  ', 'warn' => 'WARN', 'fail' => 'FAIL'];

        foreach ($checks as $check) {
            $line = sprintf('[%s] %-20s %s', $symbols[$check['level']], $check['label'], $check['detail']);

            match ($check['level']) {
                'ok' => $this->line($line),
                'warn' => $this->warn($line),
                default => $this->error($line),
            };
        }

        $failures = collect($checks)->where('level', 'fail')->count();
        $warnings = collect($checks)->where('level', 'warn')->count();
        $this->newLine();

        if ($failures > 0) {
            $this->error("Not ready for go-live: {$failures} must-fix, {$warnings} should-fix.");

            return self::FAILURE;
        }

        $warnings > 0
            ? $this->warn("No must-fix problems. {$warnings} thing(s) to improve.")
            : $this->info('Everything checked is in order.');

        return self::SUCCESS;
    }
}
