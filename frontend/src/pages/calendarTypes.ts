import type { Team, User } from '../types';

export interface AssignedUser {
  assignment_id?: number;
  user_id: number;
  nama: string;
  peran: string;
  volume?: number | null;
  satuan?: string | null;
  mulai?: string;
  selesai?: string;
  metrics_visible?: boolean;
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
  keterangan?: string | null;
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

export interface CalendarFilters {
  year: number;
  teamId: string;
  userId: string;
  status: string;
  jenis: string;
  search: string;
}

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
  dates: string[];
  activities: { id: string; title: string; team_nama?: string }[];
}

export interface StatusCounts {
  berjalan: number;
  selesai: number;
  belumMulai: number;
  terlambat: number;
}

export interface EmployeeConflict {
  userId: number;
  nama: string;
  dates: string[];
  maxConcurrent: number;
  activities: { id: string; title: string }[];
}

export interface CalendarAnalytics {
  total: number;
  activeMonths: number;
  overlapDayCount: number;
  activeDays: number;
  densestMonth: MonthBreakdown | null;
  monthlyCounts: Record<string, string | number>[];
  teamSummary: TeamWorkloadSummary[];
  topMonths: MonthBreakdown[];
  overlaps: OverlapMonth[];
  statusCounts: StatusCounts;
  employeeConflicts: EmployeeConflict[];
}

export type WorkloadIndicator = 'aman' | 'hampir_penuh' | 'melebihi';

export interface EmployeeSummary {
  id: number;
  nama: string;
  jabatan?: string;
  teams: { id: number; nama: string }[];
  events: CalendarEvent[];
  picCount: number;
  busyDays: number;
  assignmentDays: number;
  periodStart?: string;
  periodEnd?: string;
  maxConcurrent: number;
  points: number;
  peakMonth?: { key: string; label: string; points: number };
  indicator: WorkloadIndicator;
  metricsHidden?: boolean;
}

export interface AddAssignmentForm {
  key: string;
  user_id: string;
  peran: string;
  volume: string;
  satuan: string;
  mulai: string;
  selesai: string;
}

export interface AddActivityForm {
  team_id: string;
  nama: string;
  jenis: string;
  mulai: string;
  selesai: string;
  bobot: string;
  keterangan: string;
  kegiatan_besar: boolean;
  assignments: AddAssignmentForm[];
}

export interface CalendarPageData {
  teams: Team[];
  users: User[];
  canManage: boolean;
  manageableTeams: Team[];
  teamStyles: Map<number, TeamStyle>;
}
