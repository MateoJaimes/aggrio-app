import { Routes } from '@angular/router';

import { adminGuard } from '../../core/auth/auth.guard';
import { EstateForm } from './pages/estate-form';
import { EstateList } from './pages/estate-list';

export const estatesRoutes: Routes = [
  {
    path: '',
    component: EstateList,
    canActivate: [adminGuard],
  },
  {
    path: 'new',
    component: EstateForm,
    canActivate: [adminGuard],
  },
  {
    path: ':id/edit',
    component: EstateForm,
    canActivate: [adminGuard],
  },
];
