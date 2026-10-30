<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PublicAccessController extends Controller
{
    public function store(Request $r)
    {
        $data = $r->validate(['firstname' => 'required|string|max:100', 'lastname' => 'required|string|max:100',
            'id_type' => ['required', Rule::in(['CC', 'CE', 'TI', 'PA', 'PPT', 'OTRO'])],
            'id_number' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{3,30}\z/'],
            'email' => 'required|email|max:255', 'landname' => 'required|string|max:255',
            'country' => 'nullable|string|max:100', 'department' => 'nullable|string|max:100', 'city' => 'nullable|string|max:100',
            'consent' => 'required|accepted'], [
                'id_type.required' => 'Selecciona el tipo de documento.',
                'id_type.in' => 'Selecciona un tipo de documento válido.',
                'id_number.required' => 'Ingresa el número de documento.',
                'id_number.string' => 'El número de documento debe enviarse como texto.',
                'id_number.regex' => 'El documento debe tener entre 3 y 30 letras o números, sin puntos ni espacios.',
            ]);
        unset($data['consent']);
        $data['email'] = mb_strtolower(trim($data['email']));
        $data['id_number'] = strtoupper($data['id_number']);
        // Do not reveal whether an email already belongs to an account/request.
        DB::table('access_requests')->insertOrIgnore($data + ['country' => '', 'department' => '', 'city' => '',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Recibimos tu solicitud. Si corresponde, el equipo te enviará las instrucciones de acceso.'], 202);
    }
}
