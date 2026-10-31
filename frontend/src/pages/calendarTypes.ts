import type { Team, User } from '../types';

export interface AssignedUser {
  user_id: number;
  nama: string;
  peran: string;
  mulai?: string;
  selesai?: string;
}

export interface CalendarEventExtendedProps {
  activity_id: number;
  team_id: number;
  team_nama?: string;
  jenis: string;
  status: string;
  bobot: number;
  kegiatan_besar: boolean;
  selesai_manual: boolean;
  assignments_count: number;
  assigned_users: AssignedUser[];
}

export interface CalendarEvent {
  id: string;
  title: string;
  start: string;
  end: string;
  allDay?: boolean;
  extendedProps: CalendarEventExtendedProps;
}

export type CalendarTab = 'heatmap' | 'timeline' | 'monthly' | 'summary' | 'employees';

export interface TeamStyle {
  solid: string;
  soft: string;
  text: string;
}

export interface MonthBreakdown {
  index: number;
  label: string;
  total: number;
  teams: { id: number; nama: string; jumlah: number }[];
}

export interface TeamWorkloadSummary {
  id: number;
  nama: string;
  jumlah: number;
  activeDays: number;
  activities: string[];
}

export interface OverlapMonth {
  index: number;
  label: string;
  days: number;
  activities: { id: string; title: string; team_nama?: string }[];
}

export interface CalendarAnalytics {
  total: number;
  activeMonths: number;
  overlapDayCount: number;
  densestMonth: MonthBreakdown | null;
  monthlyCounts: Record<string, string | number>[];
  teamSummary: TeamWorkloadSummary[];
  topMonths: MonthBreakdown[];
  overlaps: OverlapMonth[];
}

export interface EmployeeSummary {
  id: number;
  nama: string;
  jabatan?: string;
  events: CalendarEvent[];
  picCount: number;
  busyDays: number;
}

export interface AddActivityForm {
  team_id: string;
  nama: string;
  jenis: string;
  mulai: string;
  selesai: string;
  pic_id: string;
  member_ids: string[];
  keterangan: string;
  kegiatan_besar: boolean;
}

export interface CalendarPageData {
  teams: Team[];
  users: User[];
  canManage: boolean;
  manageableTeams: Team[];
  teamStyles: Map<number, TeamStyle>;
}
