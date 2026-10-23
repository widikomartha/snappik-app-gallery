<?php

namespace App\Http\Middleware;

use App\Services\Operations\PublicDocuments;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => fn () => $request->user()?->only('name')],
            'status' => fn () => $request->session()->get('status'),
            'publicDocuments' => PublicDocuments::links(),
        ];
    }
}
