import { Component, Input, OnInit, inject, output, signal } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { Title } from '@angular/platform-browser';
import { SessionMenu } from '../core/auth/session-menu';
import { AuthService } from '../core/auth/auth.service';
import { ThemeService } from '../core/theme/theme.service';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, SessionMenu],
  templateUrl: './app-sidebar.html',
  styleUrl: './app-sidebar.scss',
})
export class AppSidebar implements OnInit {
  @Input() variant: 'admin' | 'producer' = 'admin';
  @Input() activeSection = 'resumen';
  @Input() disabled = false;
  readonly sectionChange = output<string>();
  readonly expanded = signal(false);
  readonly toolsExpanded = signal(false);
  readonly theme = inject(ThemeService);
  readonly auth = inject(AuthService);
  private title = inject(Title);
  readonly adminItems = [
    { path: '/dashboard', label: 'Inicio', icon: 'M3 3v18h18 M5 15l5-5 4 4 5-5' },
    { path: '/estates', label: 'Fincas', icon: 'm3 10 9-7 9 7 M5 9v11h14V9 M9 20v-6h6v6' },
    {
      path: '/lots',
      label: 'Lotes',
      icon: 'M4 4h7v7H4z M13 4h7v7h-7z M4 13h7v7H4z M13 13h7v7h-7z',
    },
    {
      path: '/activities',
      label: 'Actividades',
      icon: 'M8 4H6v16h14V4h-4 M9 3h6v4H9z M8 13h8 M8 17h5',
    },
    { path: '/iot', label: 'Lecturas IoT', icon: 'M7 3h10v18H7z M10 7h4 M10 17h4 M3 8v8 M21 8v8' },
    {
      path: '/multimedia',
      label: 'Archivos multimedia',
      icon: 'M3 4h18v16H3z M21 15l-5-5L5 20 M7 8h3v3H7z',
    },
    {
      path: '/access-requests',
      label: 'Solicitudes de acceso',
      icon: 'M4 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2 M10 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M16 11l2 2 4-4',
    },
    {
      path: '/accounts',
      label: 'Usuarios y acceso',
      icon: 'M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2 M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8',
    },
    {
      path: '/configuracion',
      label: 'Configuración',
      icon: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M12 2v3 M12 19v3 M2 12h3 M19 12h3 M5 5l2 2 M17 17l2 2 M5 19l2-2 M17 7l2-2',
    },
  ];
  readonly mainAdminItems = [
    '/dashboard',
    '/access-requests',
    '/estates',
    '/accounts',
    '/configuracion',
  ].map((path) => this.adminItems.find((item) => item.path === path)!);
  readonly supervisionItems = this.adminItems.filter((item) =>
    ['/lots', '/activities', '/iot', '/multimedia'].includes(item.path),
  );
  readonly producerItems: {
    id: string;
    label: string;
    icon: string;
    path?: string;
    area?: string;
  }[] = [
    { id: 'resumen', label: 'Inicio', icon: this.adminItems[0].icon },
    { id: 'fincas', label: 'Mi finca', icon: this.adminItems[1].icon },
    {
      id: 'cultivos',
      label: 'Mis cultivos',
      icon: this.adminItems[2].icon,
      path: '/produccion',
      area: 'cultivos',
    },
    {
      id: 'trabajo',
      label: 'Trabajo del día',
      icon: this.adminItems[3].icon,
      path: '/produccion',
      area: 'trabajo',
    },
    {
      id: 'sanidad',
      label: 'Sanidad del cultivo',
      icon: 'M12 3 3 7v6c0 4 9 8 9 8s9-4 9-8V7z M8 12h8 M12 8v8',
      path: '/produccion',
      area: 'sanidad',
    },
    {
      id: 'dinero',
      label: 'Mi dinero',
      icon: 'M12 3v18 M17 7H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H6',
      path: '/produccion',
      area: 'dinero',
    },
    {
      id: 'perfil',
      label: 'Mi cuenta',
      icon: 'M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2 M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8',
    },
  ];
  ngOnInit() {
    this.title.setTitle(
      this.variant === 'admin' ? 'Aggrio | Panel administrativo' : 'Aggrio | Mi campo',
    );
  }
  select(id: string) {
    this.sectionChange.emit(id);
    this.expanded.set(false);
  }
}
