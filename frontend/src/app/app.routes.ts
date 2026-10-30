import { Routes } from '@angular/router';
import { inject } from '@angular/core';
import { AuthService } from './core/auth/auth.service';
import { adminGuard, authGuard } from './core/auth/auth.guard';

export const routes: Routes = [
  {
    path: 'configuracion',
    loadComponent: () => import('./features/settings/settings').then((m) => m.Settings),
    canActivate: [adminGuard],
  },
  {
    path: 'produccion',
    loadComponent: () => import('./features/production/production').then((m) => m.Production),
    canActivate: [authGuard],
  },
  {
    path: 'accounts',
    loadComponent: () => import('./features/accounts/accounts').then((m) => m.Accounts),
    canActivate: [adminGuard],
  },
  {
    path: 'guia',
    loadComponent: () => import('./features/access-guide/access-guide').then((m) => m.AccessGuide),
    canActivate: [authGuard],
  },
  {
    path: 'campo',
    loadComponent: () => import('./features/campo/campo').then((m) => m.Campo),
    canActivate: [authGuard],
  },
  {
    path: 'solicitar-acceso',
    loadComponent: () => import('./features/campo/access-request').then((m) => m.AccessRequestPage),
  },
  {
    path: 'dashboard',
    loadComponent: () => import('./features/dashboard/dashboard').then((m) => m.Dashboard),
    canActivate: [adminGuard],
  },
  {
    path: 'auth',
    loadChildren: () => import('./features/auth/auth.routes').then((m) => m.authRoutes),
  },
  {
    path: 'access-requests',
    loadChildren: () =>
      import('./features/access-requests/access-requests.routes').then(
        (m) => m.accessRequestsRoutes,
      ),
  },
  {
    path: 'estates',
    loadChildren: () => import('./features/estates/estates.routes').then((m) => m.estatesRoutes),
  },
  {
    path: 'lots',
    loadChildren: () => import('./features/lots/lots.routes').then((m) => m.lotsRoutes),
  },
  {
    path: 'activities',
    loadChildren: () =>
      import('./features/activities/activities.routes').then((m) => m.activitiesRoutes),
  },
  {
    path: 'iot',
    loadChildren: () => import('./features/iot/iot.routes').then((m) => m.iotRoutes),
  },
  {
    path: 'multimedia',
    loadChildren: () =>
      import('./features/multimedia/multimedia.routes').then((m) => m.multimediaRoutes),
  },
  {
    path: '',
    redirectTo: () => (inject(AuthService).isSuperAdmin() ? 'dashboard' : 'campo'),
    pathMatch: 'full',
  },
  {
    path: '**',
    redirectTo: '',
  },
];
