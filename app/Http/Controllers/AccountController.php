<?php

namespace App\Http\Controllers;

use App\Models\DigitalEntitlement;
use App\Models\Order;
use Illuminate\Contracts\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('account.index', [
            'orders' => Order::query()->where('user_id', auth()->id())->with('items')->latest()->paginate(10),
            'entitlements' => DigitalEntitlement::query()->where('user_id', auth()->id())
                ->with(['asset.offering.catalogItem', 'orderItem'])->latest()->get(),
        ]);
    }
}
