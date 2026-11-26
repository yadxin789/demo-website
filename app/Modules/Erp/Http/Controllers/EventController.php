<?php

namespace App\Modules\Erp\Http\Controllers;

use App\Modules\Erp\Models\Event\DomainEvent;
use App\Modules\Erp\Models\Event\IntegrationOutbox;

class EventController extends Controller
{
    public function outbox()
    {
        $rows = IntegrationOutbox::orderByDesc('id')->paginate(20);

        return view('erp::event.outbox', compact('rows'));
    }

    public function domainEvents()
    {
        $rows = DomainEvent::orderByDesc('id')->paginate(20);

        return view('erp::event.domain-events', compact('rows'));
    }
}
