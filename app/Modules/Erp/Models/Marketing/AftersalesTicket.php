<?php

namespace App\Modules\Erp\Models\Marketing;


class AftersalesTicket extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['ticket_no', 'ecom_order_no', 'customer_name', 'customer_phone', 'type', 'description', 'status', 'handler_id'];
}
