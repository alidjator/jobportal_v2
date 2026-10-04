<x-mail::message>
{{ __('identity.password_reset.greeting', ['name' => $name]) }}

{{ __('identity.password_reset.intro') }}

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

{{ __('identity.password_reset.expiry', ['minutes' => $minutes]) }}

{{ __('identity.password_reset.ignore') }}
</x-mail::message>
