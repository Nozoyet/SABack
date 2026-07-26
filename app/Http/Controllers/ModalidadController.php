<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Modalidad;

class ModalidadController extends Controller
{
    public function index()
    {
        $modalidades = Modalidad::all();

        return response()->json([
            'success' => true,
            'data'    => $modalidades,
        ]);
    }
}