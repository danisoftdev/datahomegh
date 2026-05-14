@extends($layout)

@if ($layout === 'layouts.app')
    @section('nav_variant')
        {{ auth()->user()->role?->slug === \App\Models\Role::SLUG_AGENT ? 'agent' : 'buyer' }}
    @endsection
@endif

@section('title', __('Notifications') . ' — ' . config('app.name'))
@section('heading', __('Notifications'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Notifications') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-navy/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('When') }}</th>
                    <th class="px-3 py-2">{{ __('Title') }}</th>
                    <th class="px-3 py-2">{{ __('Message') }}</th>
                    <th class="px-3 py-2">{{ __('Type') }}</th>
                    <th class="px-3 py-2 w-28"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($notifications as $n)
                    <tr class="border-b border-white/5 {{ $n->isUnreadForUser(auth()->id()) ? 'bg-white/4' : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $n->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2 font-medium text-white">{{ $n->title }}</td>
                        <td class="px-3 py-2 max-w-md">{{ Str::limit($n->message, 200) }}</td>
                        <td class="px-3 py-2">{{ $n->type }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <form method="post" action="{{ route('notifications.destroy', $n) }}" class="inline" onsubmit="return confirm(@json(__('Remove this notification from your inbox?')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-300 hover:underline">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
@endsection
