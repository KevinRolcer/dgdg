<?php

namespace App\Http\Controllers;

use App\Services\Home\HomeService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $service)
    {
    }

    public function index(): View
    {
        return view('home', $this->service->indexData());
    }
}
