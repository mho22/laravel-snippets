<?php

declare( strict_types = 1 );

namespace App\Models;

use Illuminate\Foundation\Auth\User as FoundationAuthUser;


class Authenticatable extends FoundationAuthUser
{
	protected $guarded = [];
}
