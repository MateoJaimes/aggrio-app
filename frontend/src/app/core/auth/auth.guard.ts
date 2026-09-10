import { CanActivateFn } from '@angular/router';

// TODO(Héctor): reemplazar con la implementación real de HU001.
// Temporarily allow access so the Angular admin estate pages can render while auth is still being implemented.
export const adminGuard: CanActivateFn = () => {
  return true;
};
