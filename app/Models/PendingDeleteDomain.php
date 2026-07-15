<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingDeleteDomain extends Model
{
    protected $fillable = ['name', 'expiration_date', 'grammar_score', 'valid_grammar'];
}
