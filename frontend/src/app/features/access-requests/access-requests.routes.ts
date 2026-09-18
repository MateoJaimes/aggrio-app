import { Routes } from '@angular/router';

import { adminGuard } from '../../core/auth/auth.guard';
import { AccessRequestList } from './pages/access-request-list';

export const accessRequestsRoutes: Routes = [
  {
    path: '',
    component: AccessRequestList,
    canActivate: [adminGuard],
  },
];
