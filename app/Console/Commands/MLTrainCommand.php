<?php

namespace App\Console\Commands;

use App\Services\FastAPIIntegration;
use Illuminate\Console\Command;

class MLTrainCommand extends Command
{
    protected $signature = 'ml:train
        {mode : Training mode - fast or full}
        {--learning-rate=0.001 : Learning rate for training}
        {--epochs=50 : Number of epochs}';

    protected $description = 'Run ML model training via FastAPI';

    public function handle(FastAPIIntegration $fastapi): int
    {
        $mode = $this->argument('mode');

        if (! in_array($mode, ['fast', 'full'])) {
            $this->error('Mode must be either "fast" or "full"');

            return 1;
        }

        $this->info("Starting {$mode} model training...");

        $options = [
            'learning_rate' => (float) $this->option('learning-rate'),
            'epochs' => (int) $this->option('epochs'),
        ];

        $result = $mode === 'fast'
            ? $fastapi->trainFastValidation($options)
            : $fastapi->trainFull($options);

        if (! $result['success']) {
            $this->error('Training failed: '.$result['error']);

            return 1;
        }

        $data = $result['data'];
        $this->info('✓ Training completed successfully');
        $this->info("Rows trained: {$data['training_rows']}");
        $this->info("Accuracy: {$data['summary']['accuracy']}");

        return 0;
    }
}
