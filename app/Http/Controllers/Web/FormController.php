<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validar los datos que llegan del formulario blade
        $validated = $request->validate([
            'firstname'  => 'required|string|max:40',
            'lastname'   => 'required|string|max:40',
            'id_type'    => 'required|string',
            'id_number'  => 'required|string',
            'landname'   => 'required|string',
            'country'    => 'required|string',
            'department' => 'required|string',
            'city'       => 'required|string',
            'email'      => 'required|email|unique:access_requests,email',
        ]);

        // 2. Guardar en la base de datos
        AccessRequest::create($validated);

        // 3. Devolver al usuario a la página anterior con un mensaje de éxito
        return back()->with('success', 'Solicitud enviada con éxito. Nuestro equipo la revisará pronto.');
    }
}
