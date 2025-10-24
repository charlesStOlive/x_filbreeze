<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ImageCloudinary extends Model
{
    protected $fillable = [
        'file_name',
        'url',
        'public_id',
        'model_type',
        'model_id',
        'collection',
    ];

    public function model()
    {
        return $this->morphTo();
    }
}