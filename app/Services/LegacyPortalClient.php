<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads students and teachers from the old school portal. It only reads; it never changes anything there.
 */
class LegacyPortalClient
{
    private const PAGE_SIZE = 100;

    private ?PendingRequest $http = null;

    public function signIn(string $username, string $password): void
    {
        $this->http = Http::baseUrl(rtrim(config('school.legacy_portal.url'), '/'))
            ->withOptions(['cookies' => new CookieJar])
            ->timeout(60)
            ->retry(3, 2000, throw: false);

        $this->http->get('/Signin');
        $this->http->asForm()->post('/User_mgt/user_login_process', ['username' => $username, 'password' => $password]);

        if (! is_array($this->roster(0))) {
            throw new RuntimeException('Could not sign in to the old portal. Check the username and password.');
        }
    }

    /**
     * Every student record in the portal, studying or not, with their courses.
     *
     * @param  (callable(int): void)|null  $progress  called with the number fetched so far
     * @return list<array<string, mixed>>
     */
    public function students(?callable $progress = null): array
    {
        $students = [];
        for ($page = 0; ; $page++) {
            $rows = $this->roster($page)['rows'] ?? throw new RuntimeException("The old portal did not return student page {$page}.");
            array_push($students, ...$rows);
            if ($progress) {
                $progress(count($students));
            }
            if (count($rows) < self::PAGE_SIZE) {
                return $students;
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teachers(): array
    {
        return $this->ajax('/Teachers/directory', [])['teachers']
            ?? throw new RuntimeException('The old portal did not return the teacher list.');
    }

    /**
     * @return array{rows?: list<array<string, mixed>>, total?: int}|null
     */
    private function roster(int $page): ?array
    {
        return $this->ajax('/students/roster', [
            'tab' => 'all', 'flag' => '', 'q' => '', 'mode' => '', 'cid' => '', 'level' => '', 'tid' => '',
            'gender' => '', 'joined' => '', 'sort' => 'new', 'page' => $page, 'per' => self::PAGE_SIZE,
        ]);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<mixed>|null
     */
    private function ajax(string $path, array $form): ?array
    {
        if ($this->http === null) {
            throw new RuntimeException('Sign in to the old portal first.');
        }

        $data = $this->http->asForm()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post($path, $form)->json();

        return is_array($data) ? $data : null;
    }
}
