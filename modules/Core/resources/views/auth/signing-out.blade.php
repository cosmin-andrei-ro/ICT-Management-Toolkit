<x-core::layouts.guest :title="__('core::sso.signing_out.title')">
    <p class="text-sm text-gray-700">{{ __('core::sso.signing_out.line') }}</p>
    <p class="mt-4 text-sm"><a href="{{ $url }}" class="underline">{{ __('core::sso.signing_out.continue') }}</a></p>
</x-core::layouts.guest>
