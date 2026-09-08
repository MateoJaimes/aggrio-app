<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Gestión | AgroTech</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tom Select for better select UI -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <!-- Custom Styles -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        /* Base Tom Select Fixes */
        .ts-control {
            padding: 1rem !important;
            border-radius: 0.75rem !important;
            border: 1px solid #CBD5E1 !important;
            font-family: var(--font-sans) !important;
            font-size: 1rem !important;
            min-height: 56px;
            display: flex;
            align-items: center;
            background-color: #f8fafc !important;
            transition: all 0.3s ease !important;
        }
        .ts-control.focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(232, 93, 4, 0.15) !important;
            background-color: #ffffff !important;
        }
        
        /* Expert UI/UX Form Aesthetics */
        .form-control {
            background-color: #f8fafc !important;
            border: 1px solid #CBD5E1;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-control:focus {
            background-color: #ffffff !important;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(232, 93, 4, 0.15);
            outline: none;
        }

        .form-group {
            position: relative;
            transition: all 0.3s ease;
        }

        /* Validation Highlights */
        .form-group.is-valid .form-control,
        .form-group.is-valid .ts-control {
            border-color: var(--success) !important;
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.15) !important;
            background-color: #f0fdf4 !important;
        }
        .form-group.is-valid .form-label {
            color: var(--success);
            font-weight: 600;
        }

        .form-group.is-invalid .form-control,
        .form-group.is-invalid .ts-control {
            border-color: var(--danger, #ef4444) !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15) !important;
            background-color: #fef2f2 !important;
        }
        .form-group.is-invalid .form-label {
            color: var(--danger, #ef4444);
            font-weight: 600;
        }
        
        .invalid-feedback {
            display: none;
            color: var(--danger, #ef4444);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            font-weight: 500;
            animation: fadeInDown 0.3s ease forwards;
        }
        .form-group.is-invalid .invalid-feedback {
            display: block;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Animated Checkmark */
        .form-group::after {
            content: '✓';
            position: absolute;
            right: 15px;
            top: 42px;
            color: var(--success);
            font-size: 1.2rem;
            font-weight: bold;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
            z-index: 10;
        }
        .form-group.is-valid::after {
            opacity: 1;
            transform: scale(1);
        }
        .form-group:has(.ts-control)::after {
            right: 40px; /* Don't overlap with select arrow */
        }

        /* Container Improvements */
        .form-container {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0,0,0,0.05);
            padding: 3.5rem;
            border-radius: 2.5rem;
        }

        /* Button Styling */
        .btn-primary {
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            padding: 1.25rem;
            border-radius: 1rem;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header id="navbar">
        <a href="{{ url('/') }}" class="logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 22 22 2 M22 22 2 2" opacity="0.2"/>
                <path d="M12 2L2 22h20L12 2z"/>
                <path d="M12 22V10"/>
                <path d="M6 16h12"/>
            </svg>
            Agro<span>Tech</span>
        </a>
        <a href="{{ url('/') }}" class="btn btn-outline">Volver al Inicio</a>
    </header>

    <!-- Form Section -->
    <section class="form-section">
        <div class="form-container">
            <h2>Se Parte de Este Entorno</h2>
            <p>Complete el formulario y nuestro equipo de expertos en gestión agrícola se pondrá en contacto con usted.</p>
            <!-- Server Validation Messages -->
            @if(session('success'))
                <div class="server-alert alert-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="server-alert alert-danger">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Por favor, verifica los siguientes errores:
                    </div>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('solicitud.store') }}" method="POST">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="firstname" class="form-label">Nombres</label>
                        <input type="text" id="firstname" name="firstname" class="form-control" placeholder="Ej. Juan" required pattern="[A-Za-zÀ-ÿ\s]{3,40}">
                        <div class="invalid-feedback">Debe contener entre 3 y 40 letras (sin números ni símbolos).</div>
                    </div>
                    <div class="form-group">
                        <label for="lastname" class="form-label">Apellidos</label>
                        <input type="text" id="lastname" name="lastname" class="form-control" placeholder="Ej. García" required pattern="[A-Za-zÀ-ÿ\s]{3,40}">
                        <div class="invalid-feedback">Debe contener entre 3 y 40 letras (sin números ni símbolos).</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="id_type" class="form-label">Tipo de Identidad</label>
                        <select id="id_type" name="id_type" required placeholder="Seleccione...">
                            <option value="">Seleccione...</option>
                            <option value="CC">Cédula de Ciudadanía (CC)</option>
                            <option value="TI">Tarjeta de Identidad (TI)</option>
                            <option value="Pasaporte">Pasaporte</option>
                        </select>
                        <div class="invalid-feedback">Por favor, seleccione un tipo de identidad.</div>
                    </div>
                    <div class="form-group">
                        <label for="id_number" class="form-label">Número de Identidad</label>
                        <input type="text" id="id_number" name="id_number" class="form-control" placeholder="Ej. 1090123456" required pattern="[0-9A-Za-z]{5,20}">
                        <div class="invalid-feedback">Debe contener entre 5 y 20 caracteres (números o letras sin espacios).</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="landname" class="form-label">Nombre de la Finca</label>
                    <input type="text" id="landname" name="landname" class="form-control" placeholder="Ej. Finca Morelos" required minlength="3">
                    <div class="invalid-feedback">Debe tener al menos 3 caracteres.</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="country" class="form-label">País</label>
                        <select id="country" name="country" required placeholder="Seleccione un país..."></select>
                        <div class="invalid-feedback">Por favor, seleccione un país.</div>
                    </div>
                    <div class="form-group">
                        <label for="department" class="form-label">Departamento / Estado</label>
                        <select id="department" name="department" required placeholder="Seleccione un departamento..."></select>
                        <div class="invalid-feedback">Por favor, seleccione un departamento.</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="city" class="form-label">Ciudad</label>
                        <select id="city" name="city" required placeholder="Seleccione una ciudad..."></select>
                        <div class="invalid-feedback">Por favor, seleccione una ciudad.</div>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="juan@ejemplo.com" required>
                        <div class="invalid-feedback">Ingrese un correo electrónico válido (ej. correo@dominio.com).</div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Enviar Solicitud</button>
            </form>
        </div>
    </section>
    <script>
        let tsCountry, tsState, tsCity, tsIdType;

        document.addEventListener('DOMContentLoaded', async () => {
            // Live validation logic
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    input.classList.add('touched');
                    validateField(input);
                });
                input.addEventListener('blur', () => {
                    input.classList.add('touched');
                    validateField(input);
                });
            });

            // Initialize Tom Select for id_type
            tsIdType = new TomSelect('#id_type', {
                create: false,
                onChange: function(value) {
                    this.blur();
                    const group = document.getElementById('id_type').closest('.form-group');
                    if(value) { group.classList.add('is-valid'); group.classList.remove('is-invalid'); }
                    else { group.classList.remove('is-valid'); group.classList.add('is-invalid'); }
                }
            });

            // Initialize Tom Select for search and elegant dropdowns
            tsCountry = new TomSelect('#country', {
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                onChange: function(value) { 
                    this.blur();
                    const group = document.getElementById('country').closest('.form-group');
                    if(value) { group.classList.add('is-valid'); group.classList.remove('is-invalid'); }
                    else { group.classList.remove('is-valid'); group.classList.add('is-invalid'); }
                    loadStates(); 
                }
            });
            
            tsState = new TomSelect('#department', {
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                onChange: function(value) { 
                    this.blur();
                    const group = document.getElementById('department').closest('.form-group');
                    if(value) { group.classList.add('is-valid'); group.classList.remove('is-invalid'); }
                    else { group.classList.remove('is-valid'); group.classList.add('is-invalid'); }
                    loadCities(); 
                }
            });
            
            tsCity = new TomSelect('#city', {
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                onChange: function(value) {
                    this.blur();
                    const group = document.getElementById('city').closest('.form-group');
                    if(value) { group.classList.add('is-valid'); group.classList.remove('is-invalid'); }
                    else { group.classList.remove('is-valid'); group.classList.add('is-invalid'); }
                }
            });

            try {
                // Fetch countries without flags
                const res = await fetch('https://restcountries.com/v3.1/all?fields=name');
                let countries = await res.json();
                
                countries.forEach(c => {
                    tsCountry.addOption({value: c.name.common, text: c.name.common});
                });
            } catch (error) {
                console.error('Error loading countries:', error);
            }
        });

        async function loadStates() {
            const country = tsCountry.getValue();
            tsState.clearOptions();
            tsCity.clearOptions();
            tsState.clear();
            tsCity.clear();
            
            if (!country) return;

            try {
                const res = await fetch('https://countriesnow.space/api/v0.1/countries/states', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ country: country })
                });
                const data = await res.json();
                
                if (!data.error && data.data.states && data.data.states.length > 0) {
                    data.data.states.forEach(s => {
                        let cleanName = s.name.replace(/ Department$/i, '').trim();
                        tsState.addOption({value: s.name, text: cleanName});
                    });
                }
            } catch (error) {
                console.error('Error loading states:', error);
            }
        }

        async function loadCities() {
            const country = tsCountry.getValue();
            const state = tsState.getValue();
            tsCity.clearOptions();
            tsCity.clear();
            
            if (!country || !state) return;

            try {
                const res = await fetch('https://countriesnow.space/api/v0.1/countries/state/cities', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ country: country, state: state })
                });
                const data = await res.json();
                
                if (!data.error && data.data && data.data.length > 0) {
                    data.data.forEach(c => {
                        tsCity.addOption({value: c, text: c});
                    });
                }
            } catch (error) {
                console.error('Error loading cities:', error);
            }
        }

        function validateField(input) {
            const group = input.closest('.form-group');
            if(input.checkValidity() && input.value.trim() !== '') {
                group.classList.add('is-valid');
                group.classList.remove('is-invalid');
            } else if(input.value.trim() !== '' || input.classList.contains('touched')) {
                group.classList.remove('is-valid');
                group.classList.add('is-invalid');
            }
        }
    </script>
</body>
</html>
