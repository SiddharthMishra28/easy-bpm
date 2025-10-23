<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description'];

    public function versions()
    {
        return $this->hasMany(FlowVersion::class);
    }

    public function latestVersion()
    {
        return $this->belongsTo(FlowVersion::class, 'latest_version_id');
    }
}
