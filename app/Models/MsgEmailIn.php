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

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Récupérer les services depuis ServiceRegistry
        $servicesConfig = EmailsProcessorRegisterServices::getAll();

        // Générer les casts pour `services`
        $this->casts = array_merge(
            $this->casts,
            DynamicEmailServicesCast::generateCasts($servicesConfig, 'services')
        );

        // Générer les casts pour `results`
        $this->casts = array_merge(
            $this->casts,
            DynamicEmailServicesCast::generateCasts($servicesConfig, 'results')
        );
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
