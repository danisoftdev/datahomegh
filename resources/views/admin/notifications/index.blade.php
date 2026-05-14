@extends('layouts.admin')

@section('title', __('Notifications'))
@section('heading', __('Notifications'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Notifications') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="mb-8 rounded-xl border border-white/10 bg-[#16213E]/80 p-6">
        <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Broadcast message') }}</h2>
        <p class="mb-4 text-sm text-slate-400">{{ __('Leave all roles unchecked to send one global in-app notification to buyers and agents. Select roles to create per-user inbox rows for buyers and/or agents only. Recipients with a valid email on file also receive this message by email.') }}</p>
        <form id="admin-broadcast-form" class="max-w-xl space-y-3">
            @csrf
            <div>
                <span class="mb-2 block text-sm text-slate-400">{{ __('Target roles') }}</span>
                <div class="flex flex-wrap gap-4 text-sm text-slate-300">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="target_roles[]" value="buyer" class="rounded border-white/20 bg-[#1A1A2E]">{{ __('Buyers') }}</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="target_roles[]" value="agent" class="rounded border-white/20 bg-[#1A1A2E]">{{ __('Agents') }}</label>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Type') }}</label>
                <input type="text" name="type" value="admin_broadcast" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Title') }}</label>
                <input type="text" name="title" required maxlength="200" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-400">{{ __('Message') }}</label>
                <textarea name="message" required rows="4" class="w-full rounded-lg border border-white/10 bg-[#1A1A2E] px-3 py-2 text-white"></textarea>
            </div>
            <p id="admin-broadcast-status" class="hidden text-sm text-emerald-300"></p>
            <p id="admin-broadcast-error" class="hidden text-sm text-red-300"></p>
            <button type="submit" class="rounded-lg bg-[#FFD700] px-4 py-2 font-semibold text-[#1A1A2E]">{{ __('Send') }}</button>
        </form>
        <script>
            document.getElementById('admin-broadcast-form')?.addEventListener('submit', async function (e) {
                e.preventDefault();
                var statusEl = document.getElementById('admin-broadcast-status');
                var errEl = document.getElementById('admin-broadcast-error');
                statusEl.classList.add('hidden');
                errEl.classList.add('hidden');
                var fd = new FormData(this);
                var roles = fd.getAll('target_roles[]');
                var body = {
                    title: fd.get('title'),
                    message: fd.get('message'),
                    type: fd.get('type') || 'broadcast',
                    target_roles: roles,
                };
                try {
                    var res = await fetch(@json(route('notifications.admin-broadcast')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(body),
                        credentials: 'same-origin',
                    });
                    var data = await res.json().catch(function () { return {}; });
                    if (!res.ok) {
                        var msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : '{{ __('Request failed.') }}');
                        errEl.textContent = msg;
                        errEl.classList.remove('hidden');
                        return;
                    }
                    statusEl.textContent = '{{ __('Broadcast sent.') }}';
                    statusEl.classList.remove('hidden');
                    this.reset();
                    var typeInput = this.querySelector('input[name="type"]');
                    if (typeInput) typeInput.value = 'admin_broadcast';
                } catch {
                    errEl.textContent = '{{ __('Request failed.') }}';
                    errEl.classList.remove('hidden');
                }
            });
        </script>
    </div>

    <h2 class="mb-4 text-lg font-semibold text-white">{{ __('Recent notifications') }}</h2>
    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('When') }}</th>
                    <th class="px-3 py-2">{{ __('Recipient') }}</th>
                    <th class="px-3 py-2">{{ __('Type') }}</th>
                    <th class="px-3 py-2">{{ __('Title') }}</th>
                    <th class="px-3 py-2 w-28"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($notifications as $n)
                    <tr class="border-b border-white/5">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $n->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $n->user_id ? $n->user_id.' '.($n->user?->username ? '('.$n->user->username.')' : '') : __('All users') }}</td>
                        <td class="px-3 py-2">{{ $n->type }}</td>
                        <td class="px-3 py-2">{{ $n->title }}</td>
                        <td class="px-3 py-2">
                            <form method="post" action="{{ route('admin.notifications.destroy', $n) }}" class="inline" onsubmit="return confirm(@json(__('Delete this notification? If it is a broadcast, all copies for every recipient will be removed.')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-300 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $notifications->links() }}
@endsection
