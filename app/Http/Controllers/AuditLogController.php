<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\RoleLabel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\Like;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'role' => ['nullable', Rule::in(array_keys(RoleLabel::all()))],
        ]);

        $logs = AuditLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', (string) $request->input('action')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', (int) $request->input('user')))
            ->when($request->filled('role'), function ($q) use ($request) {
                $role = (string) $request->input('role');

                // Rows written before roles were recorded use the person's current role.
                $q->where(fn ($w) => $w->where('user_role', $role)
                    ->orWhere(fn ($old) => $old->whereNull('user_role')->whereHas('user', fn ($u) => $u->where('role', $role))));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('description', 'like', Like::contains($request->input('search')));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'users' => User::orderBy('name')->get(['id', 'name', 'role']),
            'roles' => RoleLabel::all(),
        ]);
    }
}
