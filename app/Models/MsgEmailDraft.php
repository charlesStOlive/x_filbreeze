<?php

namespace App\Models;

use App\Models\MsgUserDraft;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Services\EmailsProcessorRegisterServices;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class MsgEmailDraft extends Model
{
    use HasFactory;

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        // Générer les casts pour `services`
        $this->casts = array_merge(
            $this->casts,
            DynamicEmailServicesCast::generateCasts('email-draft', 'options',  'services_options' ),
            DynamicEmailServicesCast::generateCasts('email-draft', 'results',  'services_results' ),
        );
    }


    public function msg_email_user()
    {
        return $this->belongsTo(MsgUserDraft::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
