<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function __invoke()
    {
        $plan = DB::table('plans as p')->join('plan_versions as v', 'v.id', '=', 'p.current_version_id')
            ->where('p.code', 'pro')->first(['v.price_idr', 'v.duration_days']);

        return Inertia::render('public/welcome', ['plan' => $plan ? [
            'price' => (int) $plan->price_idr, 'days' => (int) $plan->duration_days,
        ] : null]);
    }
}
