<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventBillingService;
use Illuminate\Http\Request;

class EventPaymentController extends Controller
{
    public function pay(Request $request, Event $event, EventRegistration $registration, EventBillingService $billing)
    {
        $charge = $billing->receive($request, $event, $registration);

        return response()->json(['status' => $charge->status]);
    }
}
