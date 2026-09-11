import { Routes } from '@angular/router';

export const routes: Routes = [
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
