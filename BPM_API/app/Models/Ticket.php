<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'flow_version_id',
        'current_stage_id',
        'status',
        'ticket_data',
        'created_by_user_id',
    ];

    protected $casts = [
        'ticket_data' => 'array',
    ];

    public function flow()
    {
        return $this->belongsTo(Flow::class);
    }

    public function flowVersion()
    {
        return $this->belongsTo(FlowVersion::class);
    }

    public function currentStage()
    {
        return $this->belongsTo(FlowStage::class, 'current_stage_id');
    }

    public function taskStatuses()
    {
        return $this->hasMany(TicketTaskStatus::class);
    }

    public function history()
    {
        return $this->hasMany(TicketHistory::class);
    }
}
