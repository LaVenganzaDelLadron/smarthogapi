<?php

namespace Database\Seeders;

use App\Models\FeederFeedTypeMapping;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeederFeedTypeMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // For feeder_id=1, map 4 feed types to 4 GPIO pins on ESP32 relay module
        // GPIO pins: 12 (Relay 1), 14 (Relay 2), 27 (Relay 3), 26 (Relay 4)
        $feedTypes = [
            [
                'feed_type' => 'starter',
                'relay_pin' => 12,
                'max_duration_seconds' => 20,
            ],
            [
                'feed_type' => 'grower',
                'relay_pin' => 14,
                'max_duration_seconds' => 25,
            ],
            [
                'feed_type' => 'finisher',
                'relay_pin' => 27,
                'max_duration_seconds' => 30,
            ],
            [
                'feed_type' => 'maintenance',
                'relay_pin' => 26,
                'max_duration_seconds' => 15,
            ],
        ];

        foreach ($feedTypes as $feedType) {
            FeederFeedTypeMapping::create([
                'feeder_id' => 1,
                'feed_type' => $feedType['feed_type'],
                'relay_pin' => $feedType['relay_pin'],
                'max_duration_seconds' => $feedType['max_duration_seconds'],
                'is_active' => true,
            ]);
        }
    }
}
