<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use App\Services\PasswordCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    /** The person's groups, newest message first. Admins see every group. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q'));

        $groups = ($this->chat->isStaff($user) ? ChatGroup::query() : $user->chatGroups())
            ->withCount('members')
            ->addSelect(['last_message_at' => ChatMessage::selectRaw('max(created_at)')->whereColumn('chat_group_id', 'chat_groups.id')])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('last_message_at')->orderBy('name')
            ->paginate(40)->withQueryString();

        return view('chat.index', ['groups' => $groups, 'search' => $search, 'canCreate' => ! $user->isStudent()]);
    }

    public function show(Request $request, ChatGroup $group): View|Response
    {
        $user = $request->user();
        $blocked = $this->chat->blockedReason($group, $user);
        abort_if($blocked === 'You are not in this group.', 403, $blocked);

        $messages = $blocked ? collect() : $group->messages()->with('user', 'replyTo.user', 'forwardedFrom')
            ->latest('id')->limit(150)->get()->reverse()->values();

        if (! $blocked) {
            $this->chat->markRead($group, $user);
        }

        $data = [
            'group' => $group->load('course'),
            'messages' => $messages,
            'blocked' => $blocked,
            'me' => $user,
            'canModerate' => $this->chat->canModerate($group, $user),
            'members' => $group->members()->orderBy('name')->get(),
            'forwardTo' => $user->chatGroups()->whereKeyNot($group->id)->orderBy('name')->limit(50)->get(),
        ];

        // The page asks for just the messages every few seconds, so new ones appear without reloading.
        if ($request->boolean('partial')) {
            return response()->view('chat.messages', $data);
        }

        return view('chat.show', $data);
    }

    public function store(Request $request, ChatGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:25600', 'mimes:mp3,m4a,aac,ogg,oga,opus,wav,webm,amr,3gp,jpg,jpeg,png,gif,webp,heic,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,mp4,mov'],
            'reply_to_id' => ['nullable', 'integer'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:36000'],
        ]);

        try {
            $this->chat->send($group, $request->user(), $data['body'] ?? null, $request->file('file'), $data['reply_to_id'] ?? null, $data['duration_seconds'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return redirect()->to(route('chat.show', $group).'#end');
    }

    /** Files are only given to people who can open the group. */
    public function file(Request $request, ChatMessage $message): StreamedResponse
    {
        abort_if(! $message->attachment_path || $message->isRemoved(), 404);
        abort_if($this->chat->blockedReason($message->group, $request->user()) !== null, 403);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->attachment_mime ?? 'application/octet-stream',
        ]);
    }

    public function forward(Request $request, ChatMessage $message): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'exists:chat_groups,id']]);
        $to = ChatGroup::findOrFail($data['to']);

        try {
            $this->chat->forward($message, $to, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        return back()->with('status', "Forwarded to {$to->name}.");
    }

    public function destroy(Request $request, ChatMessage $message): RedirectResponse
    {
        try {
            $this->chat->remove($message, $request->user());
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('status', 'Message removed.');
    }

    /** Teachers and admins can make extra groups by hand. */
    public function create(Request $request): RedirectResponse
    {
        abort_if($request->user()->isStudent(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $group = ChatGroup::create(['name' => $data['name'], 'kind' => 'custom', 'created_by' => $request->user()->id]);
        $group->members()->attach($request->user()->id, ['role' => $request->user()->isTeacher() ? 'teacher' : 'admin']);

        return redirect()->route('chat.show', $group)->with('status', 'Group made. Add people below.');
    }

    /** Add someone by their student number, phone number or username. */
    public function addMember(Request $request, ChatGroup $group, PasswordCodeService $people): RedirectResponse
    {
        abort_unless($this->chat->canModerate($group, $request->user()) || $group->created_by === $request->user()->id, 403);
        $data = $request->validate(['login' => ['required', 'string', 'max:191']]);

        $found = $people->accountsFor($data['login']);
        if ($found->isEmpty()) {
            return back()->withErrors(['login' => 'Nobody found with that student number, phone or username.']);
        }

        $group->members()->syncWithoutDetaching($found->mapWithKeys(fn (User $u) => [$u->id => ['role' => $u->isTeacher() ? 'teacher' : 'member']])->all());

        return back()->with('status', 'Added '.$found->pluck('name')->join(', ').'.');
    }

    public function updateMember(Request $request, ChatGroup $group, User $member): RedirectResponse
    {
        abort_unless($this->chat->canModerate($group, $request->user()), 403);
        $data = $request->validate(['action' => ['required', 'in:mute,unmute,remove']]);

        $query = DB::table('chat_group_members')->where('chat_group_id', $group->id)->where('user_id', $member->id);
        match ($data['action']) {
            'mute' => $query->update(['muted' => true]),
            'unmute' => $query->update(['muted' => false]),
            'remove' => $query->delete(),
        };

        return back()->with('status', "{$member->name}: {$data['action']} done.");
    }
}
