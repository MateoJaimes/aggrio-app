import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AppSidebar } from './app-sidebar';
import { AuthService } from '../core/auth/auth.service';

describe('Navegación y tema compartidos', () => {
  beforeEach(() => {
    localStorage.removeItem('aggrio-theme');
    TestBed.configureTestingModule({
      imports: [AppSidebar],
      providers: [
        provideRouter([]),
        {
          provide: AuthService,
          useValue: {
            currentUser: () => ({ name: 'Productor', email: 'producer@example.test' }),
            isSuperAdmin: () => false,
          },
        },
      ],
    });
  });
  afterEach(() => {
    localStorage.removeItem('aggrio-theme');
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.style.removeProperty('color-scheme');
  });

  it('conserva los destinos del Superadmin en el menú compartido', () => {
    const fixture = TestBed.createComponent(AppSidebar);
    fixture.detectChanges();
    const links = Array.from(
      fixture.nativeElement.querySelectorAll('nav a'),
    ) as HTMLAnchorElement[];
    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/dashboard',
      '/access-requests',
      '/estates',
      '/accounts',
      '/configuracion',
      '/produccion',
      '/produccion?area=sanidad',
      '/guia',
    ]);
    fixture.componentInstance.toolsExpanded.set(true);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('a[href="/activities"]')).toBeTruthy();
    fixture.destroy();
  });
  it('muestra solo las secciones del productor y cierra el menú al elegir Mi cuenta', () => {
    const fixture = TestBed.createComponent(AppSidebar);
    fixture.componentRef.setInput('variant', 'producer');
    fixture.componentRef.setInput('activeSection', 'perfil');
    fixture.detectChanges();
    const emitted = vi.fn();
    fixture.componentInstance.sectionChange.subscribe(emitted);
    const toggle = fixture.nativeElement.querySelector('.menu-toggle') as HTMLButtonElement;
    toggle.click();
    fixture.detectChanges();
    expect(toggle.getAttribute('aria-expanded')).toBe('true');
    const active = fixture.nativeElement.querySelector(
      'button[aria-current=page]',
    ) as HTMLButtonElement;
    expect(active.textContent).toContain('Mi cuenta');
    expect(fixture.nativeElement.textContent).not.toContain('Dashboard ejecutivo');
    active.click();
    fixture.detectChanges();
    expect(emitted).toHaveBeenCalledWith('perfil');
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
    fixture.destroy();
  });
  it('permite al productor usar y conservar el mismo tema oscuro del Superadmin', () => {
    const fixture = TestBed.createComponent(AppSidebar);
    fixture.componentRef.setInput('variant', 'producer');
    fixture.detectChanges();
    const toggle = fixture.nativeElement.querySelector('[role=switch]') as HTMLButtonElement;
    toggle.click();
    fixture.detectChanges();
    expect(document.documentElement.dataset['theme']).toBe('dark');
    expect(localStorage.getItem('aggrio-theme')).toBe('dark');
    expect(toggle.getAttribute('aria-checked')).toBe('true');
    fixture.destroy();
  });
});
