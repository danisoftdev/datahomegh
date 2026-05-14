<x-mail::message>
# {{ __('Account approved') }}

{{ __('Hello :name,', ['name' => $user->name]) }}

{{ __('Your agent account on :app has been approved. You can sign in with your username and start using your shop.', ['app' => config('app.name')]) }}

**{{ __('Shop link') }}:** [{{ $shopSlug }}]({{ url('/'.$shopSlug) }})

<x-mail::button :url="route('login')">
{{ __('Sign in') }}
</x-mail::button>

{{ __('Thank you for joining us.') }}

{{ config('app.name') }}
</x-mail::message>
