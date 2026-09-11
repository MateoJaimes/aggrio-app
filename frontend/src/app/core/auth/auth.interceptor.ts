import { HttpHandlerFn, HttpInterceptorFn, HttpRequest } from '@angular/common/http';

// TODO(Héctor): reemplazar con la implementación real de HU001.
export const authInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next: HttpHandlerFn) => {
  const token = localStorage.getItem('token');

  if (!token) {
    return next(req);
  }

  const cloned = req.clone({
    setHeaders: {
      Authorization: `Bearer ${token}`,
    },
  });

  return next(cloned);
};
