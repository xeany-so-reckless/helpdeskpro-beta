<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Kategori awal (PRD FR-04). Aman dijalankan ulang dan juga di production,
 * karena kategori dibutuhkan sebelum tiket pertama dibuat.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Hardware' => 'Masalah perangkat keras seperti laptop, PC, dan monitor.',
            'Software' => 'Instalasi, error, dan lisensi aplikasi.',
            'Network' => 'Koneksi internet, Wi-Fi, VPN, dan jaringan kantor.',
            'Account & Access' => 'Akun, kata sandi, dan hak akses sistem.',
            'Printer & Peripheral' => 'Printer, scanner, keyboard, mouse, dan perangkat tambahan.',
            'Other' => 'Masalah IT lain yang tidak masuk kategori di atas.',
        ];

        foreach ($categories as $name => $description) {
            Category::updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
