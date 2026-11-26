<?php

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use App\Services\LegacyPortalClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('school:import-legacy
    {folder? : Folder holding students.json and teachers.json from the old portal (default: storage/app/private/legacy)}
    {--from-portal : Download the latest students and teachers from the old portal into the folder first}')]
#[Description('Bring students, teachers, courses and fee balances over from the old school portal')]
class ImportLegacyPortal extends Command
{
    public function handle(LegacyImporter $importer, LegacyPortalClient $portal): int
    {
        $folder = rtrim($this->argument('folder') ?? storage_path('app/private/legacy'), '/\\');

        if ($this->option('from-portal') && ! $this->download($portal, $folder)) {
            return self::FAILURE;
        }

        $students = $this->readJson($folder.'/students.json');
        $teachers = $this->readJson($folder.'/teachers.json');

        if ($students === null || $teachers === null) {
            return self::FAILURE;
        }

        $this->info('Importing '.count($teachers['teachers'] ?? $teachers).' teachers and '.count($students).' students...');
        $counts = $importer->import($teachers['teachers'] ?? $teachers, $students);

        $this->table(['What', 'Count'], [
            ['Teachers', number_format($counts['teachers'])],
            ['Students', number_format($counts['students'])],
            ['Course enrolments', number_format($counts['enrolments'])],
            ['Studying now', number_format($counts['active_enrolments'])],
            ['Students owing', number_format($counts['owing_students'])],
            ['Total owed (UGX)', number_format($counts['owing_ugx'])],
            ['Courses not matched', number_format($counts['unknown_courses'])],
            ['Shared student numbers renamed', number_format($counts['shared_numbers'])],
        ]);

        return self::SUCCESS;
    }

    /**
     * Save the old portal's students and teachers as JSON files. The portal's sign-in comes from
     * LEGACY_PORTAL_USERNAME and LEGACY_PORTAL_PASSWORD, or is asked for here. It is never saved.
     */
    private function download(LegacyPortalClient $portal, string $folder): bool
    {
        $username = config('school.legacy_portal.username') ?: $this->ask('Old portal username');
        $password = config('school.legacy_portal.password') ?: $this->secret('Old portal password');

        try {
            $this->info('Signing in to the old portal...');
            $portal->signIn((string) $username, (string) $password);
            $teachers = $portal->teachers();
            $students = $portal->students(function (int $count): void {
                if ($count % 1000 === 0) {
                    $this->line("  {$count} students so far");
                }
            });
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return false;
        }

        File::ensureDirectoryExists($folder);
        File::put($folder.'/teachers.json', json_encode(['teachers' => $teachers]));
        File::put($folder.'/students.json', json_encode($students));
        $this->info('Downloaded '.count($teachers).' teachers and '.count($students)." students into {$folder}");

        return true;
    }

    /**
     * @return array<mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            $this->error("Missing file: {$path}");

            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            $this->error("Not valid JSON: {$path}");

            return null;
        }

        return $data;
    }
}
