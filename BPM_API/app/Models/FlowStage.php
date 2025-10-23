<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_version_id',
        'name',
        'sequence',
        'is_start_stage',
        'is_end_stage',
    ];

    public function flowVersion()
    {
        return $this->belongsTo(FlowVersion::class);
    }

    public function tasks()
    {
        return $this->hasMany(StageTask::class);
    }

    public function rules()
    {
        return $this->hasMany(StageRule::class);
    }
}
