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
  created_at: string;
  updated_at: string;
}

export type AccessRequestAction = 'approve' | 'waitlist' | 'deny';
