import { Routes } from '@angular/router';

import { adminGuard } from '../../core/auth/auth.guard';
import { IotReadingForm } from './pages/iot-reading-form';
import { IotReadingList } from './pages/iot-reading-list';

export const iotRoutes: Routes = [
  {
    path: '',
    component: IotReadingList,
    canActivate: [adminGuard],
  },
  {
    path: 'new',
    component: IotReadingForm,
    canActivate: [adminGuard],
  },
  {
    path: ':id/edit',
    component: IotReadingForm,
    canActivate: [adminGuard],
  },
];
