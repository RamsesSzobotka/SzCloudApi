<?php

namespace Database\Seeders;

use App\Models\Expansion;
use Illuminate\Database\Seeder;

class ExpansionSeeder extends Seeder
{
    public function run(): void
    {
        $expansions = [
            ["name" => "1GB",  "storage_bytes" => 1073741824,   "price_cents" => 199],
            ["name" => "10GB", "storage_bytes" => 10737418240,  "price_cents" => 1499],
            ["name" => "50GB", "storage_bytes" => 53687091200,  "price_cents" => 5999],
            ["name" => "100GB","storage_bytes" => 107374182400, "price_cents" => 9999],
        ];

        foreach ($expansions as $expansion) {
            Expansion::updateOrCreate(
                ["name" => $expansion["name"]],
                $expansion
            );
        }
    }
}
