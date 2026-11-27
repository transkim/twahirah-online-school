<?php

namespace App\Services;

use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Lets people set their own password with a one-time code sent to their phone by SMS.
 * Students and teachers brought over from the old portal use this to sign in for the first time.
 */
class PasswordCodeService
{
    public const MINUTES_VALID = 10;

    public const MAX_TRIES = 5;

    public function __construct(private SmsSender $sms) {}

    /**
     * Active accounts that match a phone number, student number, username or email.
     *
     * @return Collection<int, User>
     */
    public function accountsFor(string $login): Collection
    {
        $login = trim($login);
        $lower = mb_strtolower($login);

        // Usernames, emails and student numbers match whatever the capital letters, so "Akram" finds "akram".
        return User::where('active', true)
            ->where(fn ($query) => $query
                ->whereRaw('lower(student_number) = ?', [$lower])
                ->orWhereRaw('lower(username) = ?', [$lower])
                ->orWhereRaw('lower(email) = ?', [$lower])
                ->orWhereIn('phone', $this->phoneVariants($login)))
            ->get();
    }

    public function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($user), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(self::MINUTES_VALID));

        $this->sms->send(
            $this->internationalPhone((string) $user->phone),
            "Your Twahirah Online School code is {$code}. It works for ".self::MINUTES_VALID.' minutes. Do not share it.'
        );
    }

    /** Check a code. A code works once, and stops working after too many wrong tries. */
    public function checkCode(User $user, string $code): bool
    {
        $saved = Cache::get($this->key($user));
        if (! is_array($saved)) {
            return false;
        }

        if (Hash::check($code, $saved['hash'])) {
            Cache::forget($this->key($user));

            return true;
        }

        $saved['tries']++;
        $saved['tries'] >= self::MAX_TRIES
            ? Cache::forget($this->key($user))
            : Cache::put($this->key($user), $saved, now()->addMinutes(self::MINUTES_VALID));

        return false;
    }

    /** "0700123456" is shown as "07•• ••• 456". */
    public function maskedPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return substr($digits, 0, 2).'•• ••• '.substr($digits, -3);
    }

    /**
     * The same Ugandan number can be stored as 0700123456, 256700123456 or +256700123456.
     *
     * @return list<string>
     */
    public function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 7) {
            return [];
        }

        $local = str_starts_with($digits, '256') ? '0'.substr($digits, 3) : $digits;
        $international = str_starts_with($local, '0') ? '256'.substr($local, 1) : $digits;

        return array_values(array_unique([$phone, $digits, $local, $international, '+'.$international]));
    }

    private function internationalPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return str_starts_with($digits, '0') ? '256'.substr($digits, 1) : $digits;
    }

    private function key(User $user): string
    {
        return "password-code:{$user->id}";
    }
}
