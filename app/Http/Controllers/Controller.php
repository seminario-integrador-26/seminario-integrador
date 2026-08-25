<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controller base. Placeholder hasta instalar Laravel; en el proyecto real
 * este archivo lo genera el framework.
 */
class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
