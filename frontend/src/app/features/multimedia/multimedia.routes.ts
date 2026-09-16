import { Routes } from '@angular/router';

import { adminGuard } from '../../core/auth/auth.guard';
import { MultimediaGallery } from './pages/multimedia-gallery';

export const multimediaRoutes: Routes = [
  {
    path: '',
    component: MultimediaGallery,
    canActivate: [adminGuard],
  },
];
