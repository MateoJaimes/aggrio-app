import { Routes } from '@angular/router';

import { adminGuard } from '../../core/auth/auth.guard';
import { LotForm } from './pages/lot-form';
import { LotList } from './pages/lot-list';

export const lotsRoutes: Routes = [
  {
    path: '',
    component: LotList,
    canActivate: [adminGuard],
  },
  {
    path: 'new',
    component: LotForm,
    canActivate: [adminGuard],
  },
  {
    path: ':id/edit',
    component: LotForm,
    canActivate: [adminGuard],
  },
];
