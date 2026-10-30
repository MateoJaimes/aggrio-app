import { Component, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AppSidebar } from '../../shared/app-sidebar';
import { AuthService } from '../../core/auth/auth.service';
@Component({
  standalone: true,
  imports: [RouterLink, AppSidebar],
  styleUrls: ['../lots/pages/lots-shell.scss', './access-guide.scss'],
  template: `<div class="page-shell">
    <app-sidebar
      activeSection="ayuda"
      [variant]="auth.isSuperAdmin() ? 'admin' : 'producer'"
      (sectionChange)="open($event)"
    />
    <main class="content">
      <header class="topbar">
        <div>
          <p class="eyebrow">Ayuda paso a paso</p>
          <h1>¿Cómo empiezo?</h1>
          <p>
            El administrador aprueba el acceso. El productor organiza su finca y lleva el registro
            del campo.
          </p>
        </div>
        <a class="button secondary" [routerLink]="auth.isSuperAdmin() ? '/dashboard' : '/campo'"
          >Volver al inicio</a
        >
      </header>
      @if (auth.isSuperAdmin()) {
        <section class="guide-card">
          <h2>Tu trabajo como administrador</h2>
          <ol class="flow-list">
            <li>
              <strong>Aprueba la solicitud de acceso</strong>
              <p>
                La solicitud contiene el nombre de la primera finca. Al aprobarla se vinculan la
                persona y esa finca en una sola acción. Se envían las instrucciones de acceso.
              </p>
              <a routerLink="/access-requests">Revisar solicitudes →</a>
            </li>
            <li>
              <strong>Acompaña la preparación de la finca</strong>
              <p>
                El productor cambia su contraseña, completa la superficie y ubicación, crea sus
                lotes y registra los cultivos. Puedes ayudarle a corregir los datos.
              </p>
              <a routerLink="/estates">Supervisar fincas →</a>
            </li>
            <li>
              <strong>Revisa el trabajo y los resultados</strong>
              <p>
                El productor registra actividades, gastos, cosechas y ventas. Tú puedes consultarlos
                desde Producción. Las herramientas para correcciones especializadas están en
                Supervisión.
              </p>
              <a routerLink="/produccion" [queryParams]="{ area: 'trabajo' }">Ver producción →</a>
            </li>
          </ol>
        </section>
        <section class="guide-card">
          <h2>¿Cuándo apruebo una finca por separado?</h2>
          <p>
            Cuando un productor solicita vincular otra finca. Su primera finca ya queda vinculada al
            aprobar el acceso; no hay que crearla dos veces.
          </p>
        </section>
        <section class="guide-card">
          <h2>Protege tu cuenta</h2>
          <p>
            Desde Configuración puedes añadir aplicaciones autenticadoras, cambiar la principal y
            administrar navegadores recordados.
          </p>
          <a routerLink="/configuracion">Abrir configuración →</a>
        </section>
      } @else {
        <section class="guide-card">
          <h2>Tu recorrido como productor</h2>
          <ol class="flow-list">
            <li>
              <strong>Recibe tu acceso</strong>
              <p>
                El administrador revisa tu solicitud y vincula la finca que registraste. Al entrar
                por primera vez, cambia la contraseña temporal.
              </p>
            </li>
            <li>
              <strong>Completa tu finca</strong>
              <p>
                En Mi finca, escribe su nombre, superficie y ubicación. Los datos de agua, suelo y
                documentos se pueden completar después.
              </p>
              <a routerLink="/campo" [queryParams]="{ seccion: 'fincas' }">Preparar mi finca →</a>
            </li>
            <li>
              <strong>Añade los lotes</strong>
              <p>
                Un lote es una parte de tu finca. Dale un nombre que reconozcas, como La huerta, y
                registra cuántas hectáreas tiene.
              </p>
              <a routerLink="/campo" [queryParams]="{ seccion: 'fincas' }">Ver mis lotes →</a>
            </li>
            <li>
              <strong>Registra lo que sembraste</strong>
              <p>
                Elige el lote, el cultivo y su duración. Si es una siembra por temporada, su primera
                siembra se guarda en el mismo recorrido.
              </p>
              <a routerLink="/produccion" [queryParams]="{ area: 'cultivos' }"
                >Ir a mis cultivos →</a
              >
            </li>
            <li>
              <strong>Anota el trabajo del día</strong>
              <p>
                Registra riego, poda, siembra u otra actividad. También puedes planear una tarea
                para después. Añade las fotos desde el detalle del trabajo.
              </p>
              <a routerLink="/produccion" [queryParams]="{ area: 'trabajo' }">Anotar trabajo →</a>
            </li>
            <li>
              <strong>Lleva tus cuentas</strong>
              <p>
                Anota los gastos una sola vez, registra lo cosechado y sus ventas, y completa los
                pagos que recibas después.
              </p>
              <a routerLink="/produccion" [queryParams]="{ area: 'dinero' }">Ver mi dinero →</a>
            </li>
          </ol>
        </section>
        <section class="guide-card">
          <h2>¿Tienes otra finca?</h2>
          <p>
            En Mi finca abre “¿Tienes otra finca?” y solicita vincularla. Espera la aprobación del
            administrador; no necesitas crear otra cuenta.
          </p>
        </section>
      }
      <details class="guide-card">
        <summary>¿Qué significa cada cosa?</summary>
        <dl>
          <div>
            <dt>Finca</dt>
            <dd>El terreno completo que administras.</dd>
          </div>
          <div>
            <dt>Lote</dt>
            <dd>Una parte de la finca con un nombre y un tamaño.</dd>
          </div>
          <div>
            <dt>Cultivo</dt>
            <dd>Lo que tienes sembrado en un lote: café, maíz, cacao u otra especie.</dd>
          </div>
          <div>
            <dt>Siembra o corte</dt>
            <dd>Un período de producción. Permite separar una temporada de la siguiente.</dd>
          </div>
          <div>
            <dt>Actividad</dt>
            <dd>El trabajo que hiciste o planeas hacer en el cultivo.</dd>
          </div>
          <div>
            <dt>Cosecha</dt>
            <dd>La cantidad que recogiste. Desde allí se registra lo vendido.</dd>
          </div>
        </dl>
      </details>
      <details class="guide-card">
        <summary>Quién puede hacer cada cosa</summary>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Acción</th>
                <th>Productor</th>
                <th>Administrador</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Aprobar accesos y vinculación de fincas</td>
                <td>Solicita</td>
                <td>Aprueba o rechaza</td>
              </tr>
              <tr>
                <td>Completar finca y crear lotes</td>
                <td>Lo hace en su finca</td>
                <td>Supervisa y ayuda</td>
              </tr>
              <tr>
                <td>Registrar cultivos, trabajo y cuentas</td>
                <td>Lo hace en su finca</td>
                <td>Consulta y corrige cuando hace falta</td>
              </tr>
              <tr>
                <td>Consultar datos de otras personas</td>
                <td>No</td>
                <td>Según su función administrativa</td>
              </tr>
            </tbody>
          </table>
        </div>
      </details>
    </main>
  </div>`,
})
export class AccessGuide {
  readonly auth = inject(AuthService);
  private router = inject(Router);
  open(section: string) {
    void this.router.navigate(['/campo'], { queryParams: { seccion: section } });
  }
}
