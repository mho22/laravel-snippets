<?php

declare( strict_types = 1 );

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class OrderShipped
{
	use Dispatchable, SerializesModels;

	public function __construct( public ?Order $order = null ) {}
}
