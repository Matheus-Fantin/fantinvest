<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTargetsRequest;
use Illuminate\Http\RedirectResponse;

class AllocationTargetController extends Controller
{
    public function update(UpdateTargetsRequest $request): RedirectResponse
    {
        $user = $request->user();

        foreach ($request->validated('metas') as $classe => $percentual) {
            $user->allocationTargets()->updateOrCreate(['classe' => $classe], ['percentual' => $percentual]);
        }

        return back()->with('sucesso', 'Metas de alocação salvas.');
    }
}
