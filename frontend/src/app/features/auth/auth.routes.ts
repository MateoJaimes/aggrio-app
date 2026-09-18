import { Routes } from '@angular/router';

import { guestGuard, twoFactorGuard } from '../../core/auth/auth.guard';
import { ForgotPassword } from './pages/forgot-password';
import { Login } from './pages/login';
import { ResetPassword } from './pages/reset-password';
import { TwoFactor } from './pages/two-factor';

export const authRoutes: Routes = [
  {
    path: 'login',
    component: Login,
    canActivate: [guestGuard],
  },
  {
    path: 'two-factor',
    component: TwoFactor,
    canActivate: [twoFactorGuard],
  },
  {
    path: 'forgot-password',
    component: ForgotPassword,
  },
  {
    path: 'reset-password',
    component: ResetPassword,
  },
  {
    path: '',
    redirectTo: 'login',
    pathMatch: 'full',
  },
];
