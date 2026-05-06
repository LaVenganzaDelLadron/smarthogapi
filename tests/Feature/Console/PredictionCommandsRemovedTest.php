<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PredictionCommandsRemovedTest extends TestCase
{
    public function test_prediction_commands_are_not_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayNotHasKey('predict:hog-health', $commands);
        $this->assertArrayNotHasKey('predict:feed-demand', $commands);
        $this->assertArrayNotHasKey('predict:weight-growth', $commands);
        $this->assertArrayNotHasKey('predict:outbreak-risk', $commands);
        $this->assertArrayNotHasKey('predict:device-risk', $commands);
    }
}
