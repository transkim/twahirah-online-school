<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every change staff and teachers make (not what they only look at) for the System Logs page.
 * Only the page and the record changed are kept, never what was typed, so passwords are not stored.
 */
class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && ! $request->isMethodSafe() && $user->role !== User::ROLE_STUDENT && $response->getStatusCode() < 400
            && ! ($request->hasSession() && $request->session()->has('errors'))) {
            $subject = collect($request->route()?->parameters() ?? [])
                ->map(fn ($value, $name) => $name.' '.($value instanceof Model ? ($value->name ?? $value->title ?? $value->reference ?? '#'.$value->getKey()) : $value))
                ->join(', ');

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => $request->route()?->getName() ?? 'unknown',
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 191),
                'subject' => mb_substr($subject, 0, 191) ?: null,
                'ip' => $request->ip(),
            ]);
        }

        return $response;
    }
}
