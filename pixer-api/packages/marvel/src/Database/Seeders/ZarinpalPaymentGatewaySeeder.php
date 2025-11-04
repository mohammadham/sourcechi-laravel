<?php

namespace Marvel\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Settings;

class ZarinpalPaymentGatewaySeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Get all language settings
        $settingsCollection = Settings::all();

        foreach ($settingsCollection as $settings) {
            $currentOptions = $settings->options ?? [];
            
            // Check if paymentGateway exists
            if (!isset($currentOptions['paymentGateway'])) {
                $currentOptions['paymentGateway'] = [];
            }

            // Check if ZarinPal already exists
            $zarinpalExists = false;
            foreach ($currentOptions['paymentGateway'] as $gateway) {
                if (isset($gateway['name']) && $gateway['name'] === 'ZARINPAL') {
                    $zarinpalExists = true;
                    break;
                }
            }

            // Add ZarinPal if it doesn't exist
            if (!$zarinpalExists) {
                $currentOptions['paymentGateway'][] = [
                    'name' => 'ZARINPAL',
                    'title' => 'زرین‌پال',
                ];

                $settings->options = $currentOptions;
                $settings->save();

                $this->command->info("ZarinPal payment gateway added to settings (Language: {$settings->language})");
            } else {
                $this->command->info("ZarinPal payment gateway already exists (Language: {$settings->language})");
            }
        }

        $this->command->info('ZarinPal payment gateway seeding completed!');
    }
}
