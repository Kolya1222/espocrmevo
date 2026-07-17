<?php

namespace roilafx\Espocrmevo\Models;

use Illuminate\Database\Eloquent\Model;

class OidcClient extends Model
{
    protected $table = 'oidc_clients';
    public $timestamps = true;

    protected $fillable = [
        'client_id',
        'client_secret',
        'redirect_uri',
        'grant_types',
        'scope',
        'user_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'user_id' => 'integer',
    ];
}