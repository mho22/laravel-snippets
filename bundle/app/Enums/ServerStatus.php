<?php

declare( strict_types = 1 );

namespace App\Enums;


enum ServerStatus : string
{
	case Provisioning = 'provisioning';
	case Active = 'active';
	case Stopped = 'stopped';
	case Failed = 'failed';
}
