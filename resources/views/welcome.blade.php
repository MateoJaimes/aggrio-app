<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Fincas y Terrenos | Premium</title>
    <meta name="description" content="Plataforma profesional para la gestión avanzada de fincas, terrenos y recursos agrícolas.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

    <!-- Header -->
    {{-- <header id="navbar">
        <a href="{{ url('/') }}" class="logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 22 22 2 M22 22 2 2" opacity="0.2"/>
                <path d="M12 2L2 22h20L12 2z"/>
                <path d="M12 22V10"/>
                <path d="M6 16h12"/>
            </svg>
            Agro<span>Tech</span>
        </a>
    </header> --}}
    <header id="navbar" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 5%; transition: all 0.3s ease;">
        <a href="{{ url('/') }}" class="logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 22 22 2 M22 22 2 2" opacity="0.2"/>
                <path d="M12 2L2 22h20L12 2z"/>
                <path d="M12 22V10"/>
                <path d="M6 16h12"/>
            </svg>
            Agro<span>Tech</span>
        </a>

        <a href="{{ url('/sistema/login') }}" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 50px; padding: 0.5rem 1.25rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Ingresar
        </a>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Gestión Inteligente de <br>Fincas y Terrenos</h1>
            <p>Optimice sus recursos, monitorice el estado de sus tierras en tiempo real y tome decisiones basadas en datos para maximizar el rendimiento agrícola.</p>
            <div class="hero-buttons">
                <a href="{{ url('/formulario') }}" class="btn btn-primary">Solicitar Acceso</a>
                <a href="#features" class="btn btn-outline">Explorar Plataforma</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="section-title">
            <h2>Capacidades Avanzadas</h2>
            <p style="margin: 0 auto;">Todo lo que necesita para el control integral de su explotación agraria.</p>
        </div>
        
        <div class="grid">
            <div class="card">
                <div class="card-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h4l3-9 5 18 3-9h5"/></svg>
                </div>
                <h3>Monitorización en Tiempo Real</h3>
                <p>Controle sensores de humedad, temperatura y nutrientes desde un único panel de control interactivo.</p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                </div>
                <h3>Gestión Catastral</h3>
                <p>Administre lindes, parcelas y documentación legal de todos sus terrenos de forma unificada y segura.</p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <h3>Optimización de Recursos</h3>
                <p>Reduzca costes de agua y fertilizantes mediante algoritmos predictivos basados en IA.</p>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits-section" style="padding: 8rem 5%; display: flex; gap: 4rem; align-items: center; background: white; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 300px;">
            <img src="https://images.unsplash.com/photo-1605000797499-95a51c5269ae?auto=format&fit=crop&w=800&q=80" alt="Beneficios del cultivo" style="width: 100%; border-radius: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
        </div>
        <div style="flex: 1; min-width: 300px;">
            <h2 style="font-size: 2.5rem; margin-bottom: 1.5rem; color: var(--text-main);">Beneficios de Nuestro Servicio de Monitoreo</h2>
            <p style="font-size: 1.125rem; color: var(--text-muted); margin-bottom: 1.5rem;">Implementar un sistema de monitoreo avanzado en su campo le permite maximizar el rendimiento de sus cultivos reduciendo el desperdicio de recursos.</p>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>Reducción de costos operativos hasta en un 30%</span>
                </li>
                <li style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>Prevención de plagas mediante alertas tempranas</span>
                </li>
                <li style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>Optimización del uso de fertilizantes y agua</span>
                </li>
            </ul>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: var(--text-main); color: white; padding: 4rem 5% 2rem;">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2rem; margin-bottom: 3rem;">
            <div style="max-width: 300px;">
                <a href="#" style="color: white; font-size: 1.5rem; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 22 22 2 M22 22 2 2" opacity="0.2"/>
                        <path d="M12 2L2 22h20L12 2z"/>
                        <path d="M12 22V10"/>
                        <path d="M6 16h12"/>
                    </svg>
                    Agro<span style="color: var(--primary);">Tech</span>
                </a>
                <p style="color: #cbd5e1; margin-top: 1rem;">Transformando el campo con tecnología de vanguardia y análisis predictivo para una agricultura sostenible.</p>
            </div>
            <div>
                <h3 style="color: white; margin-bottom: 1.5rem;">Contacto</h3>
                <p style="color: #cbd5e1; margin-bottom: 0.5rem;">Email: contacto@agrotech.com</p>
                <p style="color: #cbd5e1; margin-bottom: 0.5rem;">Teléfono: +57 300 123 4567</p>
                <p style="color: #cbd5e1;">Dirección: Ocaña, Norte de Santander</p>
            </div>
            <div>
                <img src="https://ufpso.edu.co/media/contenido/identidad/logo_vertical_blanco.png" alt="UFPSO Logo" style="height: 140px; padding: 10px;">
            </div>
        </div>
        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2rem; text-align: center; color: #94a3b8;">
            &copy; UFPSO CIDIA 2026. Todos los derechos reservados.
        </div>
    </footer>

    <script>
        // Simple micro-animation for navbar on scroll
        window.addEventListener('scroll', () => {
            const header = document.getElementById('navbar');
            if (window.scrollY > 50) {
                header.style.padding = '0.5rem 5%';
                header.style.background = 'rgba(255, 255, 255, 0.95)';
            } else {
                header.style.padding = '1rem 5%';
                header.style.background = 'rgba(255, 255, 255, 0.85)';
            }
        });
    </script>
</body>
</html>
