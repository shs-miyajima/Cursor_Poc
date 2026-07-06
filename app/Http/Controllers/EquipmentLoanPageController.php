<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\View\View;

class EquipmentLoanPageController extends Controller
{
    public function __invoke(): View
    {
        return view('equipment-loans.index', [
            'users' => User::query()->orderBy('id')->get(),
            'equipments' => Equipment::query()->orderBy('id')->get(),
        ]);
    }
}
