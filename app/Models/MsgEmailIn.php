<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\DynamicEmailServicesCast;
use App\Services\EmailsProcessorRegisterServices;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class MsgEmailIn extends Model
{
    use HasFactory;

    protected $casts = [];

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Générer les casts pour `services`
        $this->casts = array_merge(
            $this->casts,
            DynamicEmailServicesCast::generateCasts('email-in', 'options',  'services_options' ),
            DynamicEmailServicesCast::generateCasts('email-in', 'results',  'services_results' ),
        );

        // Générer les casts pour `results`
        
    }


    public function msg_email_user()
    {
        return $this->belongsTo(MsgUserIn::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
