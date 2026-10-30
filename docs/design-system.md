# Diseño compartido de AGGRIO

La referencia visual es el panel Superadmin: rojo `#ec1c21` como color principal, barra lateral oscura, fondo claro con degradado, tarjetas de superficie neutra, bordes suaves y tipografía Nunito. Los estados de éxito, advertencia y error conservan colores propios. La paleta se comparte con el panel del productor y la portada pública.

| Elemento | Referencia | Comportamiento |
|---|---|---|
| Tema común | `public/css/aggrio-theme.css` | Colores, superficies, bordes, radios, sombras, estados y fuente compartidos entre Laravel y Angular. |
| Tipografía | `public/fonts/aggrio/` | Nunito local con licencia OFL; no necesita Google Fonts para compilar. |
| Barra lateral | `frontend/src/app/shared/app-sidebar.*` | Marca, iconos, selección, tema y sesión comunes; destinos propios de cada rol. La API conserva la autorización. |
| Navegación adaptable | AppSidebar | Cabecera compacta y menú desplegable en pantallas pequeñas. |
| Formularios | Acceso, recuperación, cambio de contraseña y solicitud | Superficies, controles, acciones y mensajes coherentes. Login con el fondo de plantas de la portada. |
| Módulos de campo | Fincas, lotes, actividades, IoT y multimedia | Paleta común y estados de carga, error y éxito, también en tema oscuro. |
| Guía | `/guia` | Explica permisos, datos y siguientes pasos sin modificar el alcance de cada rol. |

La página pública mantiene su composición de portada y fotografías; comparte la marca, la tipografía y la paleta con la aplicación. Las pantallas conservan los contenidos y acciones propios de su función.
