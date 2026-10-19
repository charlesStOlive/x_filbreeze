<?php

namespace App\Models;

use CharlesStOlive\FilamentPermissionManager\Models\User as BaseUser;
use CharlesStOlive\MsGraphFilament\Models\MsgUserDraft;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends BaseUser
{
    public function msgUserDraft(): HasOne
    {
        return $this->hasOne(MsgUserDraft::class);
    }
}
