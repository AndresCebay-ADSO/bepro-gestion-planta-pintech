<?php

namespace App\Http\Controllers;

use App\Concerns\RedirectsWithContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use RedirectsWithContext;
}
