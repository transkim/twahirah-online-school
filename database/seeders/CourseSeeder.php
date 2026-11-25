<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

/**
 * The school's courses as listed in the current portal. Level counts that the portal did not show
 * default to one level; the data import from the old portal replaces them with the real levels.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            // name, modes, levels
            ['Quran', ['chat', 'zoom'], 1],
            ['Yassarna al Quran', ['chat', 'zoom'], 1],
            ['Arabic Language', ['chat', 'zoom'], 4],
            ['Tajweed', ['chat', 'zoom'], 3],
            ['Hadith', ['chat', 'zoom'], 2],
            ['Swalah', ['chat', 'zoom'], 1],
            ['Tafseer al-Quran', ['chat', 'zoom'], 2],
            ['Islamic Supplication', ['chat', 'zoom'], 1],
            ['English Language', ['zoom'], 3],
        ];

        foreach ($courses as [$name, $modes, $levels]) {
            foreach ($modes as $mode) {
                $course = Course::firstOrCreate(['name' => $name, 'mode' => $mode]);
                for ($i = 1; $i <= $levels; $i++) {
                    $course->levels()->firstOrCreate(['position' => $i], ['name' => 'Level '.$i]);
                }
            }
        }
    }
}
