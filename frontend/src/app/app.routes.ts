import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: 'auth',
    loadChildren: () => import('./features/auth/auth.routes').then((m) => m.authRoutes),
  },
  {
    path: 'access-requests',
    loadChildren: () => import('./features/access-requests/access-requests.routes').then((m) => m.accessRequestsRoutes),
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
    loadChildren: () => import('./features/activities/activities.routes').then((m) => m.activitiesRoutes),
  },
  {
    path: 'iot',
    loadChildren: () => import('./features/iot/iot.routes').then((m) => m.iotRoutes),
  },
  {
    path: 'multimedia',
    loadChildren: () => import('./features/multimedia/multimedia.routes').then((m) => m.multimediaRoutes),
  },
  {
    path: '',
    redirectTo: 'estates',
    pathMatch: 'full',
  },
  {
    path: '**',
    redirectTo: 'estates',
  },
];
