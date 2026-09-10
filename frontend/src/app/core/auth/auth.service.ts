import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';

// TODO(Héctor): reemplazar con la implementación real de HU001.
export interface User {
  id: number;
  name: string;
  email: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  getToken(): string | null {
    return localStorage.getItem('token');
  }

  isSuperAdmin(): boolean {
    const currentUser = this.currentUserValue();
    return currentUser?.id === 1;
  }

  currentUser$(): Observable<User | null> {
    return of(this.currentUserValue());
  }

  private currentUserValue(): User | null {
    const raw = localStorage.getItem('currentUser');

    if (!raw) {
      return null;
    }

    try {
      return JSON.parse(raw) as User;
    } catch {
      return null;
    }
  }
}
