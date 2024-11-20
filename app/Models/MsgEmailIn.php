<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Casts\MsGraph\EmailInCast;

class MsgEmailIn extends Model
{
    use HasFactory;

    protected $protected = ['id'];

     protected $casts = [
        'data_mail' => 'json',
        'tos' => 'json',

        // Champs dans datas_metas (globaux)
        'is_rejected' => EmailInCast::class . ':is_rejected',
        'reject_info' => EmailInCast::class . ':reject_info',
        'is_mail_response' => EmailInCast::class . ':is_mail_response',
        'is_from_commercial' => EmailInCast::class . ':is_from_commercial',
        'regex_key_value' => EmailInCast::class . ':regex_key_value',

        // Champs dans datas_metas.selssy
        'data_sellsy' => EmailInCast::class . ':selssy.data_sellsy',
        'has_sellsy_call' => EmailInCast::class . ':selssy.has_sellsy_call',
        'has_client' => EmailInCast::class . ':selssy.has_client',
        'has_contact' => EmailInCast::class . ':selssy.has_contact',
        'has_staff' => EmailInCast::class . ':selssy.has_staff',
        'has_contact_job' => EmailInCast::class . ':selssy.has_contact_job',

        // Champs dans datas_metas.rewrite_subject
        'new_subject' => EmailInCast::class . ':rewrite_subject.new_subject',

        // Champs dans datas_metas.score
        'has_score' => EmailInCast::class . ':score.has_score',
        'score' => EmailInCast::class . ':score.score',
        'score_job' => EmailInCast::class . ':score.score_job',
        'category' => EmailInCast::class . ':score.category',

        // Champs dans datas_metas.forward
        'forwarded_to' => EmailInCast::class . ':forward.forwarded_to',
        'move_to_folder' => EmailInCast::class . ':forward.move_to_folder',
    ];


    public function msg_email_user()
    {
        return $this->belongsTo(MsgUser::class);
    }

    public function getStatusAttribute() {
        if($this->is_rejected) {
            return $this->reject_info;
        } else if($this->move_to_folder) {
            return 'Transferé';
        } else {
            return $this->category;
        }
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getContentTypeAttribute() {
        return \Arr::get($this->data_mail, 'body.contentType', 'text');
    }
}
