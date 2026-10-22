<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f4f7fc">
    <title>Aggrio</title>
    <meta name="description" content="Administra fincas, lotes, actividades y lecturas de campo desde una plataforma clara y centralizada.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <header class="site-header" id="navbar">
        <a href="{{ url('/') }}" class="brand" aria-label="Aggrio, página principal">
            <span class="brand-mark" aria-hidden="true">A</span>
            <span class="brand-name">Aggrio</span>
        </a>

        <nav class="header-actions" aria-label="Navegación principal">
            <a class="nav-link" href="#features">Plataforma</a>
            <a class="button button-outline" href="{{ rtrim(config('app.frontend_url'), '/') }}/auth/login">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5m5 5H3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Iniciar sesión
            </a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-image" role="img" aria-label="Cultivo agrícola al amanecer"></div>
            <div class="hero-shade"></div>
            <div class="hero-content">
                <p class="eyebrow"><span></span> Gestión agrícola en un solo lugar</p>
                <h1>Tu finca, más clara.<br><span>Tu gestión, más simple.</span></h1>
                <p class="hero-copy">Organiza tus fincas, lotes y actividades; consulta las lecturas del campo y toma decisiones con información centralizada.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="{{ rtrim(config('app.frontend_url'), '/') }}/auth/login">Entrar a Aggrio <span aria-hidden="true">→</span></a>
                    <a class="button button-glass" href="#features">Conocer la plataforma</a>
                </div>
                <div class="hero-note"><span class="status-dot"></span> Información organizada para el trabajo diario en el campo</div>
            </div>
            <div class="hero-mark" aria-hidden="true"><span>AG</span><small>GESTIÓN AGRÍCOLA</small></div>
        </section>

        <section class="features section" id="features">
            <div class="section-heading">
                <p class="eyebrow">Una plataforma conectada</p>
                <h2>Todo lo que necesitas para<br><span>gestionar mejor tu operación</span></h2>
                <p>Consulta la información clave de tus terrenos y mantén las tareas organizadas desde un mismo panel.</p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <span class="feature-icon icon-blue" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M3 3v18h18M19 9l-5 5-4-4-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <h3>Lecturas del campo</h3>
                    <p>Revisa las mediciones recibidas y consulta su evolución para mantenerte al tanto de las condiciones de tus lotes.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-icon icon-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m3 10 9-7 9 7M5 9v11h14V9M9 20v-6h6v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <h3>Fincas y lotes</h3>
                    <p>Mantén la información de tus propiedades y parcelas organizada y fácil de consultar.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-icon icon-violet" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M8 4H6a2 2 0 0 0-2 2v14h16V6a2 2 0 0 0-2-2h-2M9 3h6v4H9zM8 13h8M8 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <h3>Actividades y evidencias</h3>
                    <p>Registra labores, consulta su historial y reúne archivos relacionados con el trabajo realizado.</p>
                </article>
            </div>
        </section>

        <section class="benefits section">
            <div class="benefits-visual">
                <img src="https://images.unsplash.com/photo-1605000797499-95a51c5269ae?auto=format&fit=crop&w=1100&q=85" alt="Cultivo verde en una finca">
                <div class="visual-caption"><span class="caption-mark">A</span><span><strong>Aggrio</strong><small>Gestión para el campo</small></span></div>
            </div>
            <div class="benefits-copy">
                <p class="eyebrow">Información que acompaña tu trabajo</p>
                <h2>Una vista más ordenada de tu operación</h2>
                <p class="benefits-intro">Centraliza los datos de tus fincas y consulta lo que necesitas para planear y dar seguimiento a las actividades.</p>
                <ul class="benefit-list">
                    <li><span class="check" aria-hidden="true">✓</span><span>Consulta fincas, lotes y actividades desde el mismo panel.</span></li>
                    <li><span class="check" aria-hidden="true">✓</span><span>Da seguimiento a lecturas y dispositivos IoT.</span></li>
                    <li><span class="check" aria-hidden="true">✓</span><span>Conserva archivos y evidencias asociados al trabajo de campo.</span></li>
                </ul>
                <a class="text-link" href="{{ rtrim(config('app.frontend_url'), '/') }}/auth/login">Acceder a la plataforma <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="closing-cta">
            <div>
                <p class="eyebrow">Aggrio</p>
                <h2>Empieza con una gestión más organizada.</h2>
                <p>Ingresa al panel para consultar la información de tu operación.</p>
            </div>
            <a class="button button-light" href="{{ rtrim(config('app.frontend_url'), '/') }}/auth/login">Ir al panel <span aria-hidden="true">→</span></a>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-main">
            <div class="footer-about">
                <a href="{{ url('/') }}" class="brand footer-brand" aria-label="Aggrio, página principal">
                    <span class="brand-mark" aria-hidden="true">A</span>
                    <span class="brand-name">Aggrio</span>
                </a>
                <p>Transformando el campo con tecnología de vanguardia y análisis predictivo para una agricultura sostenible.</p>
            </div>
            <div class="footer-contact">
                <h2>Contacto</h2>
                <p><span>Email:</span> <a href="mailto:contacto@agrotech.com">contacto@agrotech.com</a></p>
                <p><span>Teléfono:</span> <a href="tel:+573001234567">+57 300 123 4567</a></p>
                <p><span>Dirección:</span> Ocaña, Norte de Santander</p>
            </div>
            <div class="footer-institution">
                <img src="https://ufpso.edu.co/media/contenido/identidad/logo_vertical_blanco.png" alt="Universidad Francisco de Paula Santander Ocaña (UFPSO)">
            </div>
        </div>
        <div class="footer-bottom">
            <span>© UFPSO CIDIA 2026. Todos los derechos reservados.</span>
        </div>
    </footer>

    <script>
        const header = document.getElementById('navbar');
        const updateHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
        updateHeader();
        window.addEventListener('scroll', updateHeader, { passive: true });
    </script>
</body>
</html>
