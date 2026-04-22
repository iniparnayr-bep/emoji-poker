<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** GET /billing — subscription dashboard */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Billing/Index', [
            'isPro'       => $user->is_pro,
            'plan'        => $user->is_pro ? 'Pro ($9/month)' : 'Free',
            'nextBilling' => null, // TODO: pull from Stripe
        ]);
    }

    /** POST /billing/subscribe — mock Stripe checkout (sets is_pro=true) */
    public function subscribe(Request $request)
    {
        $user = $request->user();
        // TODO: replace with real Stripe checkout:
        // $checkout = $user->newSubscription('default', env('STRIPE_PRO_PRICE_ID'))->checkout([...]);
        // return redirect($checkout->url);

        // Mock: just grant Pro immediately
        $user->update(['is_pro' => true]);

        return back()->with('success', 'You\'re now a Pro member! 🎉');
    }

    /** POST /billing/cancel */
    public function cancel(Request $request)
    {
        $user = $request->user();
        // TODO: $user->subscription('default')->cancel();
        $user->update(['is_pro' => false]);
        return back()->with('success', 'Your subscription has been cancelled.');
    }
}
