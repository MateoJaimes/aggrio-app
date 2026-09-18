export type UserRole = 'superadmin' | 'productor' | 'tecnico';

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole | null;
  is_superadmin: boolean;
  two_factor_enabled: boolean;
}

/** Par de tokens que entrega el backend al completar el login (RF004). */
export interface TokenPair {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
  expires_at: string;
  refresh_expires_in: number;
  refresh_expires_at: string;
}

/** Login que terminó en un solo paso. */
export interface SessionPayload extends TokenPair {
  requires_two_factor: false;
  user: User;
  /** Sólo llega la primera vez, al dar de alta el 2FA. */
  recovery_codes?: string[];
}

/** Login que quedó a la espera del segundo factor (RF003). */
export interface TwoFactorChallengePayload {
  requires_two_factor: true;
  two_factor_enrolled: boolean;
  challenge_token: string;
  expires_in: number;
}

export type LoginPayload = SessionPayload | TwoFactorChallengePayload;

export interface TwoFactorSetupPayload {
  qr_code: string;
  otpauth_uri: string;
  secret: string;
}

export interface TwoFactorStatusPayload {
  required: boolean;
  enabled: boolean;
  confirmed_at: string | null;
  recovery_codes_remaining: number;
}

export function requiresTwoFactor(payload: LoginPayload): payload is TwoFactorChallengePayload {
  return payload.requires_two_factor === true;
}
