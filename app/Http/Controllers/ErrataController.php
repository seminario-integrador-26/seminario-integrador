<?php

namespace App\Http\Controllers;

use App\Services\ErrataService;

/**
 * Web (Inertia). Alta de "fe de errata" sobre un Evento. Controller fino.
 */
class ErrataController extends Controller
{
    public function __construct(
        private ErrataService $erratas,
    ) {}

    // TODO: create(Evento $evento) -> Inertia::render('Errata/Create', [...])
    // TODO: store(StoreErrataRequest $request, Evento $evento) -> $this->erratas->registrar(...)
}
