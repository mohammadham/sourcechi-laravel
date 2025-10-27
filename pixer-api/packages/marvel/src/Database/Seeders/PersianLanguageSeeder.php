<?php

namespace Marvel\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Language;

class PersianLanguageSeeder extends Seeder
{
    /**
     * Seed Persian (Farsi) language to the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Check if Persian language already exists
        $persianExists = Language::where('language_code', 'fa')->exists();
        
        if (!$persianExists) {
            Language::create([
                'language_code' => 'fa',
                'language_name' => 'فارسی',
                'flag' => json_encode([
                    'thumbnail' => '',
                    'original' => 'https://flagcdn.com/w320/ir.png',
                    'id' => null
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info('Persian (Farsi) language added successfully!');
        } else {
            $this->command->info('Persian (Farsi) language already exists.');
        }
    }
}
