import { Routes } from '@angular/router';

import { producerGuard } from '../../core/auth/auth.guard';
import { Overview } from './pages/overview';
import { Profile } from './pages/profile';

export const accountRoutes: Routes = [
  {
    path: '',
    redirectTo: 'overview',
    pathMatch: 'full',
  },
  {
    path: 'profile',
    component: Profile,
    canActivate: [producerGuard],
  },
  {
    path: 'overview',
    component: Overview,
    canActivate: [producerGuard],
  },
];
