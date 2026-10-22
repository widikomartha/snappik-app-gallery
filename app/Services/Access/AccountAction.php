<?php

namespace App\Services\Access;

enum AccountAction: string
{
    case Read = 'read';
    case Billing = 'billing';
    case Mutate = 'mutate';
    case Checkout = 'checkout';
    case ClientRead = 'client-read';
    case ClientWrite = 'client-write';
    case Download = 'download';
    case Admin = 'admin';
}
