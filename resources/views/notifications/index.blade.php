@extends($layout)

@section('title', __('Notifications'))
@section('heading', __('Notifications'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-white">{{ __('Notifications') }}</h1>

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#16213E]/80">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="border-b border-white/10 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">{{ __('When') }}</th>
                    <th class="px-3 py-2">{{ __('Title') }}</th>
                    <th class="px-3 py-2">{{ __('Message') }}</th>
                    <th class="px-3 py-2">{{ __('Type') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($notifications as $n)
                    <tr class="border-b border-white/5 {{ $n->isUnreadForUser(auth()->id()) ? 'bg-white/4' : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $n->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2 font-medium text-white">{{ $n->title }}</td>
                        <td class="px-3 py-2 max-w-md">{{ Str::limit($n->message, 200) }}</td>
                        <td class="px-3 py-2">{{ $n->type }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
@endsection
