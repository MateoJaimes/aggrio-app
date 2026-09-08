<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\MenuItem;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('sistema')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->profile()
            ->font('Inter') // Tipografía principal de tu PDF
            ->colors([
                'primary' => Color::hex('#1B4D3E'), // Verde oscuro corporativo
                'danger'  => Color::hex('#E85D04'), // Naranja para alertas y botones de "Detener"
                'success' => Color::hex('#4CAF50'), // Verde claro para indicadores de éxito
                'info'    => Color::hex('#0284C7'), // Azul para procesos y sincronización
                // 'gray'    => Color::hex('#1E293B'), // Gris oscuro azulado para textos y fondos
                //  EL GRIS HÍBRIDO: Slate para el Día, Negro Absoluto para la Noche
                'gray' => [
                    //  MODO CLARO (Intacto, igual a la matemática de tu color #1E293B)
                    50  => '#f8fafc', 
                    100 => '#f1f5f9',
                    200 => '#e2e8f0',
                    300 => '#cbd5e1',
                    400 => '#94a3b8',
                    500 => '#64748b',
                    600 => '#475569',
                    
                    // MODO OSCURO (Negro puro y grises neutros de alto contraste)
                    700 => '#262626', // Textos secundarios y bordes sutiles
                    800 => '#171717', // Hover (cuando pasas el mouse por las filas de la tabla)
                    900 => '#111111', // Fondo de Tarjetas y Tablas (Gris muy oscuro para mantener el relieve)
                    950 => '#000000', // Fondo principal de la pantalla (Negro Puro absoluto)
                ],
                
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            
            ->sidebarFullyCollapsibleOnDesktop()
            // 2.EL SCRIPT MÁGICO PARA AUTO-CERRAR EL MENÚ
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn() => \Illuminate\Support\Facades\Blade::render('
                    <script>
                        document.addEventListener("livewire:navigated", () => {
                            // Verificamos si Alpine (el motor de animaciones) y el menú están activos
                            if (window.Alpine && Alpine.store("sidebar")?.isOpen) {
                                Alpine.store("sidebar").close(); // Cerramos el menú
                            }
                        });
                    </script>
                ')
            )
            // 2. eSTILOS PARA SOMBRAS Y BORDES EN MODO CLARO
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn() => \Illuminate\Support\Facades\Blade::render('
                    <style>
                        /* Solo aplicamos esto en el Modo Claro: html:not(.dark) */
                        
                        html:not(.dark) .fi-ta-ctn,    /* Contenedores de Tablas */
                        html:not(.dark) .fi-wi-widget, /* Contenedores de Gráficas y Tarjetas numéricas */
                        html:not(.dark) .fi-section {  /* Contenedores de Formularios */
                            
                            /* 1. Sombra suave, amplia y difuminada */
                            /* 2. Borde (ring) color gris-azulado sutil para enmarcar el blanco */
                            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 
                                        0 4px 6px -4px rgba(0, 0, 0, 0.04) !important;
                            
                            transition: all 0.3s ease; /* Transición suave */
                        }
                        
                        /* BONUS: Efecto al pasar el mouse por encima de los widgets del Dashboard */
                        html:not(.dark) .fi-wi-widget:hover {
                            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 
                                        0 8px 10px -6px rgba(0, 0, 0, 0.05)!important;
                            transform: translateY(-3px); /* Se levanta un poquito */
                        }
                    </style>
                ')
            );
        
    }
}
