<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return PermissionService::userCan($user, 'crm.company.view');
    }

    public function view(User $user, Company $company): bool
    {
        return PermissionService::userCan($user, 'crm.company.view');
    }

    public function create(User $user): bool
    {
        return PermissionService::userCan($user, 'crm.company.create');
    }

    public function update(User $user, Company $company): bool
    {
        return PermissionService::userCan($user, 'crm.company.edit');
    }

    public function delete(User $user, Company $company): bool
    {
        \Log::info('[CompanyPolicy] delete() appelée pour user ' . $user->id . ' et company ' . $company->id);

        if (!PermissionService::userCan($user, 'crm.company.delete')) {
            \Log::info('[CompanyPolicy] Permission refusée');
            return false;
        }

        $hasLocked = $this->hasLockedInvoices($company);
        \Log::info('[CompanyPolicy] hasLockedInvoices: ' . ($hasLocked ? 'true' : 'false'));

        if ($hasLocked) {
            \Log::info('[CompanyPolicy] Suppression refusée - factures verrouillées');
            return false;
        }

        \Log::info('[CompanyPolicy] Suppression autorisée');
        return true;
    }

    public function deleteAny(User $user): bool
    {
        \Log::info('[CompanyPolicy] deleteAny() appelée pour user ' . $user->id);

        if (!PermissionService::userCan($user, 'crm.company.delete')) {
            \Log::info('[CompanyPolicy] deleteAny - Permission refusée');
            return false;
        }

        \Log::info('[CompanyPolicy] deleteAny - Permission accordée');
        return true;
    }

    public function forceDelete(User $user, Company $company): bool
    {
        return $this->delete($user, $company);
    }

    protected function hasLockedInvoices(Company $company): bool
    {
        return $company->invoices()
            ->whereIn('state', ['submited', 'payed'])
            ->exists();
    }
}
