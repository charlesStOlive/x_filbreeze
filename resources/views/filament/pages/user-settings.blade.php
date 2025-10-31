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
                @php $user = auth()->user(); @endphp
                @if ($user->roles->isNotEmpty() || $user->permissions->isNotEmpty())
                    <div class="space-y-4">
                        @if ($user->roles->isNotEmpty())
                            <div class="space-y-2">
                                <h4 class="text-sm font-medium text-gray-950 dark:text-white">Rôles</h4>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($user->roles as $role)
                                        <span
                                            class="fi-badge inline-flex items-center justify-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($user->permissions->isNotEmpty())
                            <div class="space-y-2">
                                <h4 class="text-sm font-medium text-gray-950 dark:text-white">Permissions directes</h4>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($user->permissions as $permission)
                                        <span
                                            class="fi-badge inline-flex items-center justify-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-600 dark:bg-gray-400/10 dark:text-gray-400">
                                            {{ $permission->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400">Aucun rôle ou permission assigné.</p>
                @endif

            </div>
        </div>
    @endif
</x-filament::page>
