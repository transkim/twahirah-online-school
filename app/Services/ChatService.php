<?php

namespace App\Services;

use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The school's own chat, in place of WhatsApp. Class groups open only while the student has an
 * active subscription. Admins can read every group, remove messages and mute or remove members.
 */
class ChatService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function isStaff(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true);
    }

    public function member(ChatGroup $group, User $user): ?object
    {
        return $group->members()->where('users.id', $user->id)->first()?->pivot;
    }

    /** Why the person cannot open the group, or null when they can. */
    public function blockedReason(ChatGroup $group, User $user): ?string
    {
        if ($this->isStaff($user)) {
            return null;
        }
        if (! $this->member($group, $user)) {
            return 'You are not in this group.';
        }
        if ($user->isStudent() && $group->kind === 'class' && $group->course
            && ! $this->subscriptions->hasAccess($user, $group->course->mode)) {
            return 'Your subscription has ended. Renew it to open your class group again.';
        }

        return null;
    }

    public function canModerate(ChatGroup $group, User $user): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $this->member($group, $user)?->role === 'teacher');
    }

    public function send(ChatGroup $group, User $sender, ?string $body, ?UploadedFile $file = null, ?int $replyToId = null, ?int $durationSeconds = null): ChatMessage
    {
        if ($reason = $this->blockedReason($group, $sender)) {
            throw new RuntimeException($reason);
        }
        if (! $this->isStaff($sender) && $this->member($group, $sender)?->muted) {
            throw new RuntimeException('You have been muted in this group.');
        }
        if (trim((string) $body) === '' && ! $file) {
            throw new RuntimeException('Write a message or add a file.');
        }

        $attachment = [];
        if ($file) {
            $mime = (string) $file->getMimeType();
            $attachment = [
                'attachment_path' => $file->store("chat/{$group->id}", 'local'),
                'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                'attachment_mime' => $mime,
            ];
        }

        return $group->messages()->create([
            'user_id' => $sender->id,
            'type' => $file ? $this->typeFor($mime) : 'text',
            'body' => trim((string) $body) ?: null,
            'reply_to_id' => $replyToId && $group->messages()->whereKey($replyToId)->exists() ? $replyToId : null,
            'duration_seconds' => $durationSeconds,
            ...$attachment,
        ]);
    }

    /** Forward a message to another group the sender can post in. The file is shared, not copied. */
    public function forward(ChatMessage $message, ChatGroup $to, User $sender): ChatMessage
    {
        if ($message->isRemoved() || $this->blockedReason($message->group, $sender)) {
            throw new RuntimeException('That message cannot be forwarded.');
        }
        if ($reason = $this->blockedReason($to, $sender)) {
            throw new RuntimeException($reason);
        }

        return $to->messages()->create([
            'user_id' => $sender->id,
            'type' => $message->type,
            'body' => $message->body,
            'attachment_path' => $message->attachment_path,
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $message->attachment_mime,
            'duration_seconds' => $message->duration_seconds,
            'forwarded_from_id' => $message->id,
        ]);
    }

    public function remove(ChatMessage $message, User $by): void
    {
        if (! $this->canModerate($message->group, $by)) {
            throw new RuntimeException('Only admins and the class teacher can remove messages.');
        }
        $message->update(['removed_by' => $by->id]);
    }

    public function markRead(ChatGroup $group, User $user): void
    {
        DB::table('chat_group_members')->where('chat_group_id', $group->id)->where('user_id', $user->id)->update(['last_read_at' => now()]);
    }

    private function typeFor(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'audio/') => 'voice',
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'file',
        };
    }
}
