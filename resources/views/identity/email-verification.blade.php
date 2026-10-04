<x-mail::message>
{{ __('identity.email_verification.greeting', ['name' => $name]) }}

{{ __('identity.email_verification.intro') }}

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

{{ __('identity.email_verification.expiry', ['minutes' => $minutes]) }}

{{ __('identity.email_verification.ignore') }}
</x-mail::message>
