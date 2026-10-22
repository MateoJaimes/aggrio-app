import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

import { SessionMenu } from '../core/auth/session-menu';
import { ThemeService } from '../core/theme/theme.service';

@Component({
  selector: 'app-admin-sidebar',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, SessionMenu],
  template: `
    <aside class="sidebar">
      <div class="brand-row">
        <a class="brand" routerLink="/dashboard" aria-label="Aggrio, ir al dashboard">
          <span class="brand-mark" aria-hidden="true">A</span>
          <span class="brand-name"><small>Panel</small><strong>Aggrio</strong></span>
        </a>
        <button
          class="theme-toggle"
          [class.dark]="theme.isDark()"
          type="button"
          (click)="theme.toggle()"
          role="switch"
          [attr.aria-checked]="theme.isDark()"
          [attr.aria-label]="theme.isDark() ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
        >
          <span class="toggle-thumb" aria-hidden="true">
            @if (theme.isDark()) {
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
            } @else {
              <svg viewBox="0 0 24 24"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>
            }
          </span>
        </button>
      </div>

      <nav class="nav" aria-label="Administración">
        <a class="nav-item" routerLink="/dashboard" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-5 5"/></svg>
          <span>Dashboard ejecutivo</span>
        </a>
        <a class="nav-item" routerLink="/estates" routerLinkActive="active">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/></svg>
          <span>Fincas</span>
        </a>
        <a class="nav-item" routerLink="/lots" routerLinkActive="active">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
          <span>Lotes</span>
        </a>
        <a class="nav-item" routerLink="/activities" routerLinkActive="active">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4H6a2 2 0 0 0-2 2v14h16V6a2 2 0 0 0-2-2h-2"/><path d="M9 3h6v4H9zM8 13h8M8 17h5"/></svg>
          <span>Actividades</span>
        </a>
        <a class="nav-item" routerLink="/iot" routerLinkActive="active">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M10 7h4M10 17h4M3 8a13 13 0 0 0 0 8M21 8a13 13 0 0 1 0 8"/></svg>
          <span>Lecturas IoT</span>
        </a>
        <a class="nav-item" routerLink="/multimedia" routerLinkActive="active">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>
          <span>Archivos multimedia</span>
        </a>
        <a class="nav-item" routerLink="/access-requests" routerLinkActive="active">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg>
          <span>Solicitudes de acceso</span>
        </a>
      </nav>

      <app-session-menu />
    </aside>
  `,
  styles: [`
    :host { position: sticky; top: 0; z-index: 5; display: block; flex: 0 0 280px; width: 280px; height: 100vh; }
    .sidebar { box-sizing: border-box; display: flex; flex-direction: column; gap: 1.25rem; width: 100%; height: 100%; padding: 1.5rem 1rem; overflow-y: auto; color: #fff; background: var(--aggrio-sidebar-background); }
    .brand-row { display: flex; flex: none; align-items: center; gap: .5rem; }
    .brand { display: flex; flex: 1; align-items: center; min-width: 0; gap: .8rem; padding: .5rem .65rem; border-radius: 1rem; color: inherit; text-decoration: none; background: rgba(255, 255, 255, .06); }
    .brand-mark { display: grid; flex: none; width: 2.2rem; height: 2.2rem; place-items: center; border-radius: .75rem; color: #fff; font-weight: 800; background: linear-gradient(135deg, #60a5fa, var(--aggrio-primary)); }
    .brand-name { display: grid; }
    .brand-name small { color: rgba(255, 255, 255, .7); font-size: .68rem; }
    .brand-name strong { font-size: 1rem; }
    .nav { display: grid; flex: 0 0 auto; gap: .4rem; }
    .nav-item { display: flex; align-items: center; gap: .75rem; min-height: 2.75rem; padding: .75rem .9rem; border-radius: .85rem; color: rgba(255, 255, 255, .85); font-size: .9rem; font-weight: 600; text-decoration: none; transition: background .18s ease, color .18s ease; }
    .nav-item svg { flex: none; width: 1.15rem; height: 1.15rem; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; opacity: .88; }
    .nav-item span { min-width: 0; }
    .nav-item:hover { color: #fff; background: rgba(255, 255, 255, .08); }
    .nav-item.active { color: #fff; background: linear-gradient(135deg, var(--aggrio-primary), var(--aggrio-primary-dark)); box-shadow: 0 10px 22px rgba(37, 99, 235, .24); }
    .theme-toggle { display: flex; flex: 0 0 auto; align-items: center; justify-content: flex-start; width: 3.1rem; height: 1.8rem; padding: .16rem; border: 1px solid rgba(255, 255, 255, .2); border-radius: 999px; background: rgba(255, 255, 255, .1); cursor: pointer; transition: background .18s ease, border-color .18s ease; }
    .theme-toggle.dark { justify-content: flex-end; background: rgba(37, 99, 235, .4); }
    .theme-toggle:hover { border-color: rgba(255, 255, 255, .4); }
    .toggle-thumb { display: grid; flex: none; width: 1.4rem; height: 1.4rem; place-items: center; border-radius: 50%; color: #1e293b; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .2); transition: transform .18s ease; }
    .toggle-thumb svg { width: .9rem; height: .9rem; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    @media (max-width: 980px) {
      :host { position: static; flex: 0 0 auto; width: 100%; height: auto; }
      .sidebar { height: auto; min-height: 100vh; overflow: visible; }
    }
  `],
})
export class AdminSidebar {
  readonly theme = inject(ThemeService);
}
