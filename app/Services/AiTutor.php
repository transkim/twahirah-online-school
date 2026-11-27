<?php

namespace App\Services;

use App\Models\AiMessage;
use App\Models\Lesson;
use App\Models\Mark;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The AI learning helper. It answers questions about a lesson, makes practice quizzes and suggests
 * revision from the student's weaker marks. It calls Claude through the Anthropic Messages API.
 * The school's monthly token limit and a daily question limit per student keep the cost capped.
 */
class AiTutor
{
    private const SYSTEM = <<<'TXT'
You are the learning helper of Twahirah Online School, an Islamic school in Uganda that teaches Quran, Yassarna al Quran, Tajweed, Arabic, Hadith, Swalah and Tafseer, mostly to children and adult beginners.

How to help:
- Explain simply and kindly, in short paragraphs, in the language the student writes in (often English or Luganda; use Arabic script for Arabic words and add a transliteration).
- Base answers on the lesson notes you are given. When the notes do not cover something, say so and give the commonly accepted mainstream Sunni understanding, and suggest the student also asks their teacher.
- Do not issue fatwas or rule on disputed matters; for personal religious rulings, point the student to their teacher or a qualified scholar.
- For Quran recitation and Tajweed, describe the rule and give a short example; remind the student that their teacher checks recitation by listening.
- When asked for a practice quiz, write 5 numbered multiple-choice questions with options A to D, and give the answers at the end under "Answers".
- Never ask for personal details, and keep everything suitable for children.
TXT;

    public function enabled(): bool
    {
        return (bool) config('school.ai.enabled');
    }

    /** @return Collection<int, AiMessage> */
    public function history(User $student, ?Lesson $lesson)
    {
        return AiMessage::where('user_id', $student->id)->where('lesson_id', $lesson?->id)->orderBy('id')->get();
    }

    /** Reasons the student cannot ask now, or null. */
    public function blockedReason(User $student): ?string
    {
        if (! $this->enabled()) {
            return 'The AI helper is not switched on yet.';
        }

        $today = AiMessage::where('user_id', $student->id)->where('role', 'user')->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= config('school.ai.daily_questions')) {
            return 'You have asked the most questions for today. Please try again tomorrow.';
        }

        $month = AiMessage::where('created_at', '>=', now()->startOfMonth())->selectRaw('coalesce(sum(input_tokens + output_tokens), 0) as total')->value('total');
        if ($month >= config('school.ai.monthly_tokens')) {
            return 'The school\'s AI helper limit for this month is used up. It opens again next month.';
        }

        return null;
    }

    /** Ask a question (or a request such as "make a quiz") and get the reply text. */
    public function ask(User $student, string $question, ?Lesson $lesson = null): AiMessage
    {
        if ($reason = $this->blockedReason($student)) {
            throw new RuntimeException($reason);
        }

        $messages = [
            ['role' => 'user', 'content' => $this->context($student, $lesson)],
            ['role' => 'assistant', 'content' => 'Thank you. I have read this. What would you like to learn?'],
        ];
        foreach ($this->history($student, $lesson)->take(-20) as $old) {
            $messages[] = $old->role === 'assistant' && $old->content
                ? ['role' => 'assistant', 'content' => $old->content]
                : ['role' => 'user', 'content' => $old->text];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $asked = AiMessage::create(['user_id' => $student->id, 'lesson_id' => $lesson?->id, 'role' => 'user', 'text' => $question]);

        $response = Http::withHeaders([
            'x-api-key' => config('school.ai.api_key'),
            'anthropic-version' => '2026-10-21',
            'anthropic-beta' => 'server-side-fallback-2026-07-01',
        ])->timeout(120)->retry(2, 2000, throw: false)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('school.ai.model'),
            'max_tokens' => 16000,
            'output_config' => ['effort' => config('school.ai.effort')],
            // If a safety check declines, the API retries on Anthropic's recommended fallback model.
            'fallbacks' => 'default',
            // The instructions never change, so they are cached and cost less on every question.
            'system' => [['type' => 'text', 'text' => self::SYSTEM, 'cache_control' => ['type' => 'ephemeral']]],
            'messages' => $messages,
        ]);

        if (! $response->successful()) {
            $asked->delete();
            report(new RuntimeException('AI helper request failed: HTTP '.$response->status().' '.$response->json('error.type')));

            throw new RuntimeException('The AI helper could not answer just now. Please try again in a minute.');
        }

        $content = $response->json('content') ?? [];
        $text = collect($content)->where('type', 'text')->pluck('text')->join("\n\n");
        if ($response->json('stop_reason') === 'refusal' || trim($text) === '') {
            $text = 'I cannot help with that question. Please ask your teacher.';
        }

        $usage = $response->json('usage') ?? [];

        return AiMessage::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson?->id,
            'role' => 'assistant',
            'text' => $text,
            'content' => $content,
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
        ]);
    }

    /** What the helper is told about the student's lesson and weaker areas at the start of a conversation. */
    private function context(User $student, ?Lesson $lesson): string
    {
        $parts = ['The student is '.explode(' ', $student->name)[0].'.'];

        if ($lesson) {
            $parts[] = "They are studying the lesson \"{$lesson->title}\" in {$lesson->course->name}"
                .($lesson->level ? " ({$lesson->level->name})" : '').". The lesson notes are:\n<lesson>\n"
                .mb_substr((string) $lesson->body, 0, 30000)."\n</lesson>";
        } else {
            $courses = $student->enrolments()->with('course', 'level')->where('status', 'active')->get()
                ->map(fn ($e) => $e->course->name.($e->level ? " ({$e->level->name})" : ''))->unique()->join(', ');
            $parts[] = 'Their courses: '.($courses ?: 'none yet').'.';
        }

        $weak = Mark::with('course')->where('student_id', $student->id)->where('score', '<', 60)->latest('given_on')->limit(8)->get();
        if ($weak->isNotEmpty()) {
            $parts[] = 'Recent marks below 60%, which are good revision topics: '
                .$weak->map(fn (Mark $m) => "{$m->title} in {$m->course->name} ({$m->score}%)")->join('; ').'.';
        }

        return implode("\n\n", $parts);
    }
}
