@extends('layouts.admin')

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 space-y-8">

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">

    <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                User management
            </h1>
            <p class="text-slate-500 mt-2 max-w-xl">
                Platform logins, roles, and access for Parrot Canada Visa Consultant admins and clients.
            </p>
    </div>

    <a href="{{ route('admin.users.create') }}"
       class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-md shadow-blue-500/25 transition hover:from-blue-700 hover:to-indigo-700 shrink-0">
        <span class="text-lg leading-none">+</span>
        Create user
    </a>
</div>

@if(session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
        {{ session('error') }}
    </div>
@endif

<div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">

            <thead>
                <tr class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <th class="px-6 py-4">ID</th>
                    <th class="px-6 py-4">Name</th>
                    <th class="px-6 py-4">Email</th>
                    <th class="px-6 py-4">WhatsApp</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Created</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse($users as $user)

                    <tr class="transition hover:bg-slate-50/80">

                        <td class="px-6 py-4 font-mono text-xs text-slate-500">
                            #{{ $user->id }}
                        </td>

                        <td class="px-6 py-4 font-semibold text-slate-900">
                            {{ $user->name }}
                        </td>

                        <td class="px-6 py-4 text-slate-600">
                            {{ $user->email }}
                        </td>

                        <td class="px-6 py-4 text-slate-600">
                            {{ $user->whatsapp_number ?? '—' }}
                        </td>

                        <td class="px-6 py-4">
                            @php
                                $roleColor = match($user->role) {
                                    'super_admin' => 'bg-violet-100 text-violet-800 ring-violet-600/20',
                                    'agent' => 'bg-blue-100 text-blue-800 ring-blue-600/20',
                                    'client' => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
                                    default => 'bg-slate-100 text-slate-700 ring-slate-500/10',
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $roleColor }}">
                                {{ Str::headline($user->role) }}
                            </span>
                        </td>

                        <td class="px-6 py-4">
                            @if($user->status === 'active')
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800 ring-1 ring-inset ring-red-600/20">
                                    Suspended
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-xs text-slate-500">
                            {{ $user->created_at?->format('d M Y') }}
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end flex-wrap gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="inline-flex rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800">
                                    Edit
                                </a>
                                <form method="POST"
                                      action="{{ route('admin.users.destroy', $user) }}"
                                      onsubmit="return confirm('Delete this user permanently?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center">
                            <div class="mx-auto max-w-sm">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl">👥</div>
                                <p class="mt-4 text-slate-600 font-medium">No users yet</p>
                                <a href="{{ route('admin.users.create') }}"
                                   class="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                    Create the first user
                                </a>
                            </div>
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>
    </div>

    @if($users->hasPages())
        <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-3">
            {{ $users->links() }}
        </div>
    @endif

</div>

</div>

@endsection
