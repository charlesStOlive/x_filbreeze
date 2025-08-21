<x-filament::page>
    {{-- Formulaire Profil --}}
    <form wire:submit.prevent="saveProfile" class="space-y-6">
        {{ $this->editProfileForm }}
    </form>

    <hr class="my-4" />

    {{-- Formulaire Préférences régionales --}}
    <form wire:submit.prevent="saveLocaleSettings" class="space-y-6">
        {{ $this->editLocaleForm }}
    </form>

    <hr class="my-4" />

    {{-- Formulaire mot de passe --}}
    <form wire:submit.prevent="savePassword" class="space-y-6">
        {{ $this->editPasswordForm }}
    </form>

    @if (auth()->user()->roles->isNotEmpty() || auth()->user()->permissions->isNotEmpty())
        <hr class="my-4" />

        {{-- Section des autorisations (lecture seule) --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Mes autorisations
                </h3>
                <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                    Aperçu de vos rôles et permissions actuels.
                </p>
            </div>
            <div class="fi-section-content p-6">
                @include('filament.components.user-permissions', ['user' => auth()->user()])
            </div>
        </div>
    @endif
</x-filament::page>
