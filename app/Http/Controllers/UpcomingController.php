<?php

namespace App\Http\Controllers;

use App\Services\Reporting\UpcomingObligationsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Spec sections 14 and 21 — what is already committed, and what that actually
 * leaves the household to spend.
 */
class UpcomingController extends Controller
{
    public function __construct(
        private readonly UpcomingObligationsService $upcoming,
    ) {}

    public function index(Request $request): View
    {
        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [7, 30, 60, 90], true) ? $days : 30;

        return view('upcoming.index', [
            'days' => $days,
            'obligations' => $this->upcoming->forNextDays($days),
            'reality' => $this->upcoming->financialReality($days),
            'next7' => $this->upcoming->totalFor(7),
            'overdue' => $this->upcoming->overdue(),
        ]);
    }
}
