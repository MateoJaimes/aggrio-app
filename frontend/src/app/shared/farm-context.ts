import { Injectable, inject } from '@angular/core';
import { AuthService } from '../core/auth/auth.service';

/** One selected farm/plot across the producer's pages; contains no credentials. */
@Injectable({ providedIn: 'root' })
export class FarmContext {
  private auth = inject(AuthService);
  read(): { farmId: number; plotId: number } {
    try {
      return JSON.parse(sessionStorage.getItem(this.key()) ?? '{"farmId":0,"plotId":0}');
    } catch {
      return { farmId: 0, plotId: 0 };
    }
  }
  save(farmId: number, plotId = 0) {
    try {
      sessionStorage.setItem(
        this.key(),
        JSON.stringify({ farmId: Number(farmId) || 0, plotId: Number(plotId) || 0 }),
      );
    } catch {
      /* Navigation remains available without storage. */
    }
  }
  private key() {
    return `aggrio:field-context:${this.auth.currentUser()?.id}`;
  }
}
