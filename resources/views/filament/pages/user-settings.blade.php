<div class="fi-page fi-resource-page">
    <div class="fi-header">
        <div class="fi-header-wrapper">
            <div class="fi-header-content">
                <h1 class="fi-header-heading text-2xl font-bold text-gray-950 dark:text-white">
                    Paramètres utilisateur
                </h1>
            </div>
        </div>
    </div>

    <div class="fi-main">
        <div class="fi-page-content">
            <div class="space-y-6">
                <!-- Formulaire de profil -->
                <div
                    class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                        <h3
                            class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Mon profil
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            Gérez vos informations personnelles et vos paramètres de compte.
                        </p>
                    </div>

                    <div class="fi-section-content p-6">
                        <form wire:submit="updateProfile" class="space-y-6">
                            {{ $this->profileForm }}

                            <div class="flex justify-end">
                                <x-filament::button type="submit">
                                    Sauvegarder le profil
                                </x-filament::button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Formulaire de mot de passe -->
                <div
                    class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                        <h3
                            class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Modifier le mot de passe
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            Assurez-vous que votre compte utilise un mot de passe long et aléatoire pour rester
                            sécurisé.
                        </p>
                    </div>

                    <div class="fi-section-content p-6">
                        <form wire:submit="updatePassword" class="space-y-6">
                            {{ $this->passwordForm }}

                            <div class="flex justify-end">
                                <x-filament::button type="submit" color="warning">
                                    Modifier le mot de passe
                                </x-filament::button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Informations sur les rôles et permissions (lecture seule) -->
                @if (auth()->user()->roles->isNotEmpty() || auth()->user()->permissions->isNotEmpty())
                    <div
                        class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                            <h3
                                class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                Mes autorisations
                            </h3>
                            <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                                Aperçu de vos rôles et permissions actuels.
                            </p>
                        </div>

                        <div class="fi-section-content p-6 space-y-4">
                            @if (auth()->user()->roles->isNotEmpty())
                                <div>
                                    <h4 class="text-sm font-medium text-gray-950 dark:text-white mb-2">Rôles</h4>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (auth()->user()->roles as $role)
                                            <span
                                                class="fi-badge inline-flex items-center justify-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                                                {{ $role->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (auth()->user()->permissions->isNotEmpty())
                                <div>
                                    <h4 class="text-sm font-medium text-gray-950 dark:text-white mb-2">Permissions
                                        directes</h4>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (auth()->user()->permissions as $permission)
                                            <span
                                                class="fi-badge inline-flex items-center justify-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-600 dark:bg-gray-400/10 dark:text-gray-400">
                                                {{ $permission->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
