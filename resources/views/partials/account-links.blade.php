@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $role = $user->primaryRole();
@endphp

{{-- Blok użytkownika i menu konta (desktop: stały blok w lewej kolumnie, telefon: lista pod przyciskiem "Konto"). --}}
<div class="space-y-2">
    @if ($showIdentity ?? true)
        <div class="px-1">
            <p class="truncate font-semibold text-ink-900">{{ $user->name }}</p>
            <p class="truncate text-sm text-ink-600">{{ $user->email }}</p>
            @if ($role)
                <p class="text-sm text-ink-600">{{ __('ui.role.'.$role->value) }}</p>
            @endif
        </div>
    @endif

    @unless ($minimal ?? false)
        <ul class="space-y-1">
            <li>
                <span class="flex min-h-11 items-center px-1 text-ink-600" aria-disabled="true">{{ __('dashboard.account_menu.data') }} ({{ __('dashboard.soon') }})</span>
            </li>
            <li>
                <a class="flex min-h-11 items-center rounded-md px-1 text-ink-900 hover:bg-paper-2 {{ request()->routeIs('account.security') ? 'font-semibold text-brand-700' : '' }}" href="{{ route('account.security') }}" @if (request()->routeIs('account.security')) aria-current="page" @endif>
                    {{ __('dashboard.account_menu.security') }}
                </a>
            </li>
            <li>
                <span class="flex min-h-11 items-center px-1 text-ink-600" aria-disabled="true">{{ __('dashboard.account_menu.privacy') }} ({{ __('dashboard.soon') }})</span>
            </li>
        </ul>
    @endunless

    <form method="POST" action="{{ route('logout') }}" class="border-t border-line pt-2">
        @csrf
        <button type="submit" class="flex min-h-11 w-full items-center gap-2 rounded-md px-1 text-left font-semibold text-brand-700 hover:bg-paper-2">
            <x-icon name="logout" />{{ __('dashboard.account_menu.logout') }}
        </button>
    </form>
</div>
