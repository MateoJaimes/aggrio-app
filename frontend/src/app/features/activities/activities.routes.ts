import { Routes } from '@angular/router';

import { adminGuard } from '../../core/auth/auth.guard';
import { ActivityForm } from './pages/activity-form';
import { ActivityList } from './pages/activity-list';

export const activitiesRoutes: Routes = [
  {
    path: '',
    component: ActivityList,
    canActivate: [adminGuard],
  },
  {
    path: 'new',
    component: ActivityForm,
    canActivate: [adminGuard],
  },
  {
    path: ':id/edit',
    component: ActivityForm,
    canActivate: [adminGuard],
  },
];
