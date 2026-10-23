import { Component, inject } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { RouterLink, RouterLinkActive } from '@angular/router';

import { SessionMenu } from '../../core/auth/session-menu';
import { ThemeService } from '../../core/theme/theme.service';

@Component({
  selector: 'app-producer-sidebar',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, SessionMenu],
  template: `
    <aside class="sidebar">
      <div class="brand-row">
        <a class="brand" routerLink="/account" aria-label="Aggrio, ir a mi producción">
          <span class="brand-mark" aria-hidden="true">A</span>
          <span class="brand-name"><small>Mi cuenta</small><strong>Aggrio</strong></span>
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

      <nav class="nav" aria-label="Mi cuenta">
        <a class="nav-item" routerLink="/account" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/></svg>
          <span>Mi producción</span>
        </a>
        <a class="nav-item" routerLink="/account/profile" routerLinkActive="active">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
          <span>Mi perfil</span>
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
    .nav-item:hover { color: #fff; background: rgba(255, 255, 255, .08); }
    .nav-item.active { color: #fff; background: linear-gradient(135deg, var(--aggrio-primary), var(--aggrio-primary-dark)); box-shadow: 0 10px 22px rgba(37, 99, 235, .24); }
    .theme-toggle { display: flex; flex: 0 0 auto; align-items: center; justify-content: flex-start; width: 3.1rem; height: 1.8rem; padding: .16rem; border: 1px solid rgba(255, 255, 255, .2); border-radius: 999px; background: rgba(255, 255, 255, .1); cursor: pointer; transition: background .18s ease, border-color .18s ease; }
    .theme-toggle.dark { justify-content: flex-end; background: rgba(37, 99, 235, .4); }
    .theme-toggle:hover { border-color: rgba(255, 255, 255, .4); }
    .toggle-thumb { display: grid; flex: none; width: 1.4rem; height: 1.4rem; place-items: center; border-radius: 50%; color: #1e293b; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .2); }
    .toggle-thumb svg { width: .9rem; height: .9rem; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    @media (max-width: 980px) { :host { position: static; flex: 0 0 auto; width: 100%; height: auto; } .sidebar { height: auto; min-height: auto; overflow: visible; } }
  `],
})
export class ProducerSidebar {
  private readonly title = inject(Title);
  readonly theme = inject(ThemeService);

  constructor() {
    this.title.setTitle('Aggrio | Mi cuenta');
  }
}
