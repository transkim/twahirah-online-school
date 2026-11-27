<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/** Student numbers look like T/26/0001: the year, then a running number. */
class StudentNumbers
{
    /** @return Collection<int, string> numbers not in use this year, up to the highest one given */
    public function free(?int $year = null): Collection
    {
        $prefix = $this->prefix($year);
        $used = User::where('student_number', 'like', $prefix.'%')->pluck('student_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix), 4))->filter()->unique();

        if ($used->isEmpty()) {
            return collect();
        }

        return collect(range(1, $used->max()))->diff($used)->map(fn ($n) => $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT))->values();
    }

    /** The number to give a new student: a free one first, otherwise the next one. */
    public function next(?int $year = null): string
    {
        if ($free = $this->free($year)->first()) {
            return $free;
        }
        $prefix = $this->prefix($year);
        $max = User::where('student_number', 'like', $prefix.'%')->pluck('student_number')->map(fn ($n) => (int) substr($n, strlen($prefix), 4))->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function prefix(?int $year): string
    {
        return 'T/'.substr((string) ($year ?? now(config('school.timezone'))->year), -2).'/';
    }
}
