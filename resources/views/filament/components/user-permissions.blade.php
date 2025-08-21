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
