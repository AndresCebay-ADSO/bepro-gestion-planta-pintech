<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Operator/Dashboard');
    }
}
