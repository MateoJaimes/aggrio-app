import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: 'access-requests',
    loadChildren: () => import('./features/access-requests/access-requests.routes').then((m) => m.accessRequestsRoutes),
  },
  {
    path: 'estates',
    loadChildren: () => import('./features/estates/estates.routes').then((m) => m.estatesRoutes),
  },
  {
    path: '',
    redirectTo: 'estates',
    pathMatch: 'full',
  },
];
