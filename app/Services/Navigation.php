<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Builds the side menu from config/navigation.php for the signed-in person.
 */
class Navigation
{
    /**
     * @return list<array{label: string, icon: ?string, url: ?string, active: bool, badge: ?int, children: list<array{label: string, url: string, active: bool, soon: bool}>}>
     */
    public function menuFor(User $user): array
    {
        $items = match (true) {
            in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true) => config('navigation.staff'),
            $user->role === User::ROLE_TEACHER => config('navigation.teacher'),
            default => config('navigation.student'),
        };

        return array_map(function (array $item): array {
            $children = array_map(fn (array $child): array => $this->link($child), $item['children'] ?? []);
            $link = isset($item['children']) ? ['url' => null, 'active' => false] : $this->link($item);

            return [
                'label' => $item['label'],
                'icon' => $item['icon'] ?? null,
                'url' => $link['url'],
                'active' => $link['active'] || in_array(true, array_column($children, 'active'), true),
                'badge' => isset($item['badge']) ? $this->badge($item['badge']) : null,
                'children' => $children,
            ];
        }, $items);
    }

    /**
     * Find a "coming soon" section by its slug, with the name of the menu group it sits in.
     *
     * @return array{label: string, about: string, features: list<string>, group: ?string}|null
     */
    public function section(string $slug): ?array
    {
        foreach (config('navigation.staff') as $item) {
            foreach ([$item, ...($item['children'] ?? [])] as $entry) {
                if (($entry['slug'] ?? null) === $slug) {
                    return [
                        'label' => $entry['label'],
                        'about' => $entry['about'] ?? '',
                        'features' => $entry['features'] ?? [],
                        'group' => $entry === $item ? null : $item['label'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array{label: string, route?: string, slug?: string}  $entry
     * @return array{label: string, url: string, active: bool, soon: bool}
     */
    private function link(array $entry): array
    {
        if (isset($entry['route'])) {
            $pattern = str_ends_with($entry['route'], '.index') ? substr($entry['route'], 0, -6).'.*' : $entry['route'];
            $active = request()->routeIs($pattern) && ! ($entry['route'] === 'admin.students.index' && request()->routeIs('admin.students.create'));

            return ['label' => $entry['label'], 'url' => route($entry['route']), 'active' => $active, 'soon' => false];
        }

        $active = request()->routeIs('admin.section') && Route::current()?->parameter('slug') === $entry['slug'];

        return ['label' => $entry['label'], 'url' => route('admin.section', $entry['slug']), 'active' => $active, 'soon' => true];
    }

    private function badge(string $name): ?int
    {
        return match ($name) {
            'classes_today' => ClassSession::whereBetween('starts_at', [
                now(config('school.timezone'))->startOfDay()->utc(),
                now(config('school.timezone'))->endOfDay()->utc(),
            ])->where('status', '!=', 'cancelled')->count(),
            default => null,
        };
    }
}
