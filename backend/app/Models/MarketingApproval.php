<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingApproval extends Model
{
    protected $fillable = ['campaign_name', 'content', 'channel', 'status'];
}
