<?php

namespace App\Policies;

use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return PermissionService::can($user, 'users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return PermissionService::can($user, 'users.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return PermissionService::can($user, 'users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return PermissionService::can($user, 'users.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return PermissionService::can($user, 'users.delete');
    }
}
