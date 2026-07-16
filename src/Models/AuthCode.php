<?php

namespace EvolutionCMS\Espocrmevo\Models;

use Illuminate\Database\Eloquent\Model;

class AuthCode extends Model
{
    protected $table = 'oidc_auth_codes';
    public $timestamps = true;

    protected $fillable = [
        'code_id',
        'user_id',
        'client_id',
        'scopes',
        'nonce',
        'is_revoked',
        'expires_at',
    ];

    protected $casts = [
        'is_revoked' => 'boolean',
        'expires_at' => 'datetime',
    ];
}