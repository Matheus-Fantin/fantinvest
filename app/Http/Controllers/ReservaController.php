<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReservaRequest;
use Illuminate\Http\RedirectResponse;

class ReservaController extends Controller
{
    public function update(UpdateReservaRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('sucesso', 'Reserva de emergência atualizada.');
    }
}
