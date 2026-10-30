export type AccessRequestStatus = 'pending' | 'approved' | 'waitlisted' | 'denied';

export interface AccessRequest {
  id: number;
  firstname: string;
  lastname: string;
  id_type: string;
  id_number: string;
  landname: string;
  country: string;
  department: string;
  city: string;
  email: string;
  status: AccessRequestStatus;
  invitation_status?: string | null;
  invitation_sent_at?: string | null;
  created_at: string;
  updated_at: string;
}

export type AccessRequestAction = 'approve' | 'waitlist' | 'deny';
