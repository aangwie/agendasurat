<?php

namespace Database\Factories;

use App\Models\OutgoingLetter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutgoingLetter>
 */
class OutgoingLetterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = now()->year;
        $month = fake()->numberBetween(1, 12);
        $code = fake()->randomElement(OutgoingLetter::codesForMonth($month));
        $subCode = str_pad((string) fake()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT);
        $prefix = fake()->numerify('###.#.#');
        $kodeBidang = '408.37.06';

        return [
            'user_id' => User::factory(),
            'year' => $year,
            'month' => $month,
            'code' => $code,
            'sub_code' => $subCode,
            'letter_prefix' => $prefix,
            'kode_bidang' => $kodeBidang,
            'letter_number' => OutgoingLetter::composeNumber($prefix, $code, $subCode, $kodeBidang, $year),
            'subject' => fake()->sentence(4),
            'letter_date' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'bidang' => fake()->randomElement([
                'Subbagian Umum dan Kepegawaian / Tata Usaha',
                'Bidang Pembinaan Sekolah Dasar (SD)',
                'Bidang Pembinaan Sekolah Menengah Pertama (SMP)',
                'Bidang Pendidik dan Tenaga Kependidikan (PTK)',
            ]),
        ];
    }
}
