import type { Team, User } from '../types';
import type {
  CalendarAnalytics,
  CalendarEvent,
  CalendarFilters,
  EmployeeConflict,
  EmployeeSummary,
  MonthBreakdown,
  OverlapMonth,
  StatusCounts,
  TeamStyle,
  TeamWorkloadSummary,
  WorkloadIndicator,
} from './calendarTypes';

export const PALETTE = {
  navy: '#12305C',
  blue: '#1F4E8C',
  sky: '#3B82C4',
  paleBlue: '#E3EEFA',
  teal: '#0B8F80',
  mint: '#DDF4EF',
  orange: '#F28C28',
  amber: '#F7B731',
  peach: '#FDEBD3',
  coral: '#E5604D',
  blush: '#FCE4E0',
  bg: '#F7F9FC',
  white: '#FFFFFF',
  slate: '#5B6676',
  ink: '#1F2937',
} as const;

export const MONTHS = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export const MONTHS_SHORT = [
  'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
  'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
];

export const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

const TEAM_STYLES: TeamStyle[] = [
  { solid: PALETTE.blue, soft: PALETTE.paleBlue, text: PALETTE.navy },
  { solid: PALETTE.teal, soft: PALETTE.mint, text: PALETTE.teal },
  { solid: PALETTE.sky, soft: PALETTE.paleBlue, text: PALETTE.navy },
  { solid: PALETTE.orange, soft: PALETTE.peach, text: PALETTE.navy },
  { solid: PALETTE.amber, soft: PALETTE.peach, text: PALETTE.ink },
  { solid: PALETTE.navy, soft: PALETTE.paleBlue, text: PALETTE.navy },
  { solid: PALETTE.coral, soft: PALETTE.blush, text: PALETTE.coral },
];

export const STATUS_STYLES: Record<string, { bg: string; text: string; border: string }> = {
  'Belum Mulai': { bg: PALETTE.bg, text: PALETTE.slate, border: PALETTE.paleBlue },
  Berjalan: { bg: PALETTE.paleBlue, text: PALETTE.navy, border: PALETTE.sky },
  Selesai: { bg: PALETTE.mint, text: PALETTE.teal, border: PALETTE.teal },
  Terlambat: { bg: PALETTE.blush, text: PALETTE.coral, border: PALETTE.coral },
};

export const JENIS_KEGIATAN = [
  'Survei',
  'Sensus',
  'Pengolahan Data',
  'Diseminasi',
  'Pembinaan',
  'Rapat dan Koordinasi',
  'Lainnya',
];

export const teamKey = (teamId: number) => `team_${teamId}`;

export function createTeamStyles(teams: Team[]): Map<number, TeamStyle> {
  const map = new Map<number, TeamStyle>();
  [...teams]
    .sort((a, b) => a.nama.localeCompare(b.nama))
    .forEach((team, index) => {
      map.set(team.id, TEAM_STYLES[index % TEAM_STYLES.length]);
    });
  return map;
}

export function getTeamStyle(styles: Map<number, TeamStyle>, teamId?: number): TeamStyle {
  return (teamId !== undefined && styles.get(teamId)) || TEAM_STYLES[0];
}

export function parseIsoDate(iso: string): Date {
  const [year, month, day] = iso.slice(0, 10).split('-').map(Number);
  return new Date(year, (month || 1) - 1, day || 1);
}

export function toIsoDate(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function getDaysInMonth(year: number, monthIndex: number): number {
  return new Date(year, monthIndex + 1, 0).getDate();
}

const pad2 = (value: number) => String(value).padStart(2, '0');

export function buildIsoDate(year: number, monthIndex: number, day: number): string {
  return `${year}-${pad2(monthIndex + 1)}-${pad2(day)}`;
}

/**
 * Offset hari pertama bulan dari hari Senin (0 = Senin ... 6 = Minggu).
 * Dihitung murni tanpa objek Date agar stabil saat render.
 */
export function getWeekdayOffset(year: number, monthIndex: number): number {
  const monthTable = [0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4];
  const adjustedYear = monthIndex < 2 ? year - 1 : year;
  const sundayBased =
    (adjustedYear +
      Math.floor(adjustedYear / 4) -
      Math.floor(adjustedYear / 100) +
      Math.floor(adjustedYear / 400) +
      monthTable[monthIndex] +
      1) %
    7;

  return (sundayBased + 6) % 7;
}

const MONTH_LENGTHS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

export function isLeapYear(year: number): boolean {
  return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}

export function getDaysInYear(year: number): number {
  return isLeapYear(year) ? 366 : 365;
}

/** Nomor hari dalam tahun (1-366) dari string ISO, dihitung tanpa objek Date. */
export function getDayOfYearNumber(iso: string): number {
  const [year, month, day] = iso.slice(0, 10).split('-').map(Number);
  let total = 0;

  for (let index = 0; index < month - 1; index += 1) {
    total += MONTH_LENGTHS[index];
    if (index === 1 && isLeapYear(year)) total += 1;
  }

  return total + day;
}

export function getMonthLength(year: number, monthIndex: number): number {
  if (monthIndex === 1 && isLeapYear(year)) return 29;
  return MONTH_LENGTHS[monthIndex] ?? 30;
}

export interface MonthSegment {
  index: number;
  label: string;
  days: number;
  widthPct: number;
}

/** Segmen bulan dengan lebar proporsional terhadap jumlah hari dalam tahun berjalan. */
export function buildMonthSegments(year: number): MonthSegment[] {
  const total = getDaysInYear(year);
  return MONTHS_SHORT.map((label, index) => {
    const days = getMonthLength(year, index);
    return { index, label, days, widthPct: (days / total) * 100 };
  });
}

export function eventOverlapsYear(event: CalendarEvent, year: number): boolean {
  const yearStart = `${year}-01-01`;
  const yearEnd = `${year}-12-31`;
  return event.start <= yearEnd && getEventInclusiveEnd(event) >= yearStart;
}

export function eachDateInclusive(startIso: string, endIso: string): string[] {
  const dates: string[] = [];
  const current = parseIsoDate(startIso);
  const end = parseIsoDate(endIso);

  while (current <= end) {
    dates.push(toIsoDate(current));
    current.setDate(current.getDate() + 1);
  }

  return dates;
}

export function getEventInclusiveEnd(event: CalendarEvent): string {
  const end = parseIsoDate(event.end);
  end.setDate(end.getDate() - 1);
  return toIsoDate(end);
}

export function eachDateInRange(startIso: string, endExclusiveIso: string): string[] {
  const dates: string[] = [];
  const current = parseIsoDate(startIso);
  const end = parseIsoDate(endExclusiveIso);

  while (current < end) {
    dates.push(toIsoDate(current));
    current.setDate(current.getDate() + 1);
  }

  return dates;
}

export function eventCoversDate(event: CalendarEvent, date: string): boolean {
  return event.start <= date && date < event.end;
}

export function eventOverlapsMonth(event: CalendarEvent, year: number, monthIndex: number): boolean {
  const monthStart = toIsoDate(new Date(year, monthIndex, 1));
  const monthEnd = toIsoDate(new Date(year, monthIndex + 1, 0));
  return event.start <= monthEnd && getEventInclusiveEnd(event) >= monthStart;
}

export function formatDateId(iso: string): string {
  const [year, month, day] = iso.slice(0, 10).split('-').map(Number);
  return `${day} ${MONTHS_SHORT[(month || 1) - 1]} ${year}`;
}

export function formatDateRange(startIso: string, endIso: string): string {
  if (startIso === endIso) return formatDateId(startIso);
  return `${formatDateId(startIso)} - ${formatDateId(endIso)}`;
}

export function buildDailyCounts(events: CalendarEvent[], year?: number): Record<string, number> {
  const counts: Record<string, number> = {};
  events.forEach((event) => {
    eachDateInRange(event.start, event.end).forEach((date) => {
      if (year !== undefined && Number(date.slice(0, 4)) !== year) return;
      counts[date] = (counts[date] || 0) + 1;
    });
  });
  return counts;
}

export function heatCellStyle(count: number): {
  backgroundColor: string;
  color: string;
  border: string;
} {
  if (count <= 0) {
    return { backgroundColor: PALETTE.white, color: 'transparent', border: `1px solid ${PALETTE.paleBlue}` };
  }
  if (count === 1) {
    return { backgroundColor: PALETTE.mint, color: PALETTE.teal, border: `1px solid ${PALETTE.mint}` };
  }
  if (count === 2) {
    return { backgroundColor: PALETTE.mint, color: PALETTE.teal, border: `1px solid ${PALETTE.teal}` };
  }
  if (count === 3) {
    return { backgroundColor: PALETTE.peach, color: PALETTE.ink, border: `1px solid ${PALETTE.peach}` };
  }
  if (count === 4) {
    return { backgroundColor: PALETTE.amber, color: PALETTE.navy, border: `1px solid ${PALETTE.amber}` };
  }
  if (count === 5) {
    return { backgroundColor: PALETTE.orange, color: PALETTE.navy, border: `1px solid ${PALETTE.orange}` };
  }
  if (count === 6) {
    return { backgroundColor: PALETTE.blush, color: PALETTE.coral, border: `1px solid ${PALETTE.blush}` };
  }
  return { backgroundColor: PALETTE.coral, color: PALETTE.white, border: `1px solid ${PALETTE.coral}` };
}

export function applyCalendarFilters(
  events: CalendarEvent[],
  filters: CalendarFilters
): CalendarEvent[] {
  const keyword = filters.search.trim().toLowerCase();

  return events.filter((event) => {
    if (filters.teamId && String(event.extendedProps.team_id) !== filters.teamId) return false;
    if (filters.status && event.extendedProps.status !== filters.status) return false;
    if (filters.jenis && event.extendedProps.jenis !== filters.jenis) return false;

    if (filters.userId) {
      const assigned = event.extendedProps.assigned_users.some(
        (assignment) => String(assignment.user_id) === filters.userId
      );
      if (!assigned) return false;
    }

    if (keyword && !event.title.toLowerCase().includes(keyword)) return false;

    return true;
  });
}

export function buildStatusCounts(events: CalendarEvent[]): StatusCounts {
  const counts: StatusCounts = { berjalan: 0, selesai: 0, belumMulai: 0, terlambat: 0 };

  events.forEach((event) => {
    switch (event.extendedProps.status) {
      case 'Berjalan':
        counts.berjalan += 1;
        break;
      case 'Selesai':
        counts.selesai += 1;
        break;
      case 'Terlambat':
        counts.terlambat += 1;
        break;
      default:
        counts.belumMulai += 1;
    }
  });

  return counts;
}

interface AssignmentPeriod {
  event: CalendarEvent;
  assignment: CalendarEvent['extendedProps']['assigned_users'][number];
  start: string;
  end: string;
}

/** Periode efektif seluruh penugasan (fallback ke periode kegiatan). */
function collectAssignmentPeriods(events: CalendarEvent[], year: number): AssignmentPeriod[] {
  const yearStart = `${year}-01-01`;
  const yearEnd = `${year}-12-31`;
  const periods: AssignmentPeriod[] = [];

  events.forEach((event) => {
    const eventEnd = getEventInclusiveEnd(event);

    event.extendedProps.assigned_users.forEach((assignment) => {
      const rawStart = assignment.mulai || event.start;
      const rawEnd = assignment.selesai || eventEnd;
      const start = rawStart > yearStart ? rawStart : yearStart;
      const end = rawEnd < yearEnd ? rawEnd : yearEnd;

      if (start > end) return;

      periods.push({ event, assignment, start, end });
    });
  });

  return periods;
}

/**
 * Konflik penugasan pegawai: hari ketika seorang pegawai memegang dua penugasan
 * atau lebih yang periodenya beririsan. Berbeda dari overlap kegiatan.
 */
export function buildEmployeeConflicts(events: CalendarEvent[], year: number): EmployeeConflict[] {
  const byUser = new Map<number, {
    nama: string;
    dates: Map<string, Set<string>>;
    titles: Map<string, string>;
  }>();

  collectAssignmentPeriods(events, year).forEach(({ event, assignment, start, end }) => {
    const entry = byUser.get(assignment.user_id) || {
      nama: assignment.nama || 'Pegawai',
      dates: new Map<string, Set<string>>(),
      titles: new Map<string, string>(),
    };

    entry.titles.set(event.id, event.title);

    eachDateInclusive(start, end).forEach((date) => {
      const set = entry.dates.get(date) || new Set<string>();
      set.add(event.id);
      entry.dates.set(date, set);
    });

    byUser.set(assignment.user_id, entry);
  });

  const conflicts: EmployeeConflict[] = [];

  byUser.forEach((entry, userId) => {
    const conflictDates = Array.from(entry.dates.entries())
      .filter(([, ids]) => ids.size >= 2)
      .sort(([a], [b]) => a.localeCompare(b));

    if (conflictDates.length === 0) return;

    const activityIds = new Set<string>();
    conflictDates.forEach(([, ids]) => ids.forEach((id) => activityIds.add(id)));

    conflicts.push({
      userId,
      nama: entry.nama,
      dates: conflictDates.map(([date]) => date),
      maxConcurrent: conflictDates.reduce((max, [, ids]) => Math.max(max, ids.size), 0),
      activities: Array.from(activityIds).map((id) => ({
        id,
        title: entry.titles.get(id) || 'Kegiatan',
      })),
    });
  });

  return conflicts.sort(
    (a, b) => b.maxConcurrent - a.maxConcurrent || b.dates.length - a.dates.length || a.nama.localeCompare(b.nama)
  );
}

/** Distribusi poin bulanan penugasan, konsisten dengan WorkloadService backend. */
function splitPointsByMonth(
  totalPoints: number,
  startIso: string,
  endIso: string
): Record<string, number> {
  const totalDays = eachDateInclusive(startIso, endIso).length;
  if (totalDays <= 0) return {};

  const result: Record<string, number> = {};
  const start = parseIsoDate(startIso);
  const end = parseIsoDate(endIso);
  const cursor = new Date(start.getFullYear(), start.getMonth(), 1);

  while (cursor <= end) {
    const monthStart = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const monthEnd = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
    const overlapStart = monthStart > start ? monthStart : start;
    const overlapEnd = monthEnd < end ? monthEnd : end;

    if (overlapStart <= overlapEnd) {
      const days = Math.round((overlapEnd.getTime() - overlapStart.getTime()) / 86400000) + 1;
      const key = `${cursor.getFullYear()}-${pad2(cursor.getMonth() + 1)}`;
      result[key] = Math.round(totalPoints * (days / totalDays) * 100) / 100;
    }

    cursor.setMonth(cursor.getMonth() + 1);
  }

  return result;
}

export function determineWorkloadIndicator(persentase: number): WorkloadIndicator {
  if (persentase < 80) return 'aman';
  if (persentase < 100) return 'hampir_penuh';
  return 'melebihi';
}

export const WORKLOAD_LABELS: Record<WorkloadIndicator, string> = {
  aman: 'Aman',
  hampir_penuh: 'Hampir penuh',
  melebihi: 'Melebihi ambang',
};

export function buildAnalytics(
  events: CalendarEvent[],
  teams: Team[],
  year: number
): CalendarAnalytics {
  const dailyCounts = buildDailyCounts(events, year);
  const activeMonths = new Set<number>();
  const teamActivitySets = new Map<number, Set<string>>();
  const teamDaySets = new Map<number, Set<string>>();

  teams.forEach((team) => {
    teamActivitySets.set(team.id, new Set());
    teamDaySets.set(team.id, new Set());
  });

  const monthlyCounts: Record<string, string | number>[] = MONTHS.map((_, index) => {
    const row: Record<string, string | number> = { month: MONTHS_SHORT[index] };
    teams.forEach((team) => {
      row[teamKey(team.id)] = 0;
    });
    row.Lainnya = 0;
    return row;
  });

  const monthEventIds: Set<string>[] = MONTHS.map(() => new Set<string>());
  const eventsInYear = new Set<string>();

  events.forEach((event) => {
    const days = eachDateInRange(event.start, event.end).filter(
      (date) => Number(date.slice(0, 4)) === year
    );
    if (days.length === 0) return;
    eventsInYear.add(event.id);
    const months = new Set<number>();
    const teamActivitySet = teamActivitySets.get(event.extendedProps.team_id);
    const teamDaySet = teamDaySets.get(event.extendedProps.team_id);

    days.forEach((date) => {
      const monthIndex = Number(date.slice(5, 7)) - 1;
      if (monthIndex >= 0 && monthIndex < 12) {
        activeMonths.add(monthIndex);
        months.add(monthIndex);
        teamDaySet?.add(date);
      }
    });

    months.forEach((monthIndex) => {
      if (monthEventIds[monthIndex].has(event.id)) return;
      monthEventIds[monthIndex].add(event.id);

      const key = teams.some((team) => team.id === event.extendedProps.team_id)
        ? teamKey(event.extendedProps.team_id)
        : 'Lainnya';
      monthlyCounts[monthIndex][key] = Number(monthlyCounts[monthIndex][key] || 0) + 1;
    });

    teamActivitySet?.add(event.title);
  });

  const teamSummary: TeamWorkloadSummary[] = teams
    .map((team) => ({
      id: team.id,
      nama: team.nama,
      jumlah: teamActivitySets.get(team.id)?.size || 0,
      activeDays: teamDaySets.get(team.id)?.size || 0,
      activities: Array.from(teamActivitySets.get(team.id) || []),
    }))
    .filter((team) => team.jumlah > 0)
    .sort((a, b) => b.jumlah - a.jumlah || a.nama.localeCompare(b.nama));

  const monthBreakdown: MonthBreakdown[] = MONTHS.map((label, index) => {
    const teamsInMonth = teams
      .map((team) => ({
        id: team.id,
        nama: team.nama,
        jumlah: Number(monthlyCounts[index][teamKey(team.id)] || 0),
      }))
      .filter((team) => team.jumlah > 0);

    return {
      index,
      label,
      total: teamsInMonth.reduce((sum, team) => sum + team.jumlah, 0),
      teams: teamsInMonth,
    };
  });

  const topMonths = [...monthBreakdown]
    .filter((month) => month.total > 0)
    .sort((a, b) => b.total - a.total || a.index - b.index)
    .slice(0, 5);

  const overlaps: OverlapMonth[] = [];
  let overlapDayCount = 0;

  const overlapByMonth = new Map<number, { dates: string[]; eventIds: Set<string> }>();

  Object.entries(dailyCounts).forEach(([date, count]) => {
    if (count < 2) return;
    const monthIndex = Number(date.slice(5, 7)) - 1;
    if (monthIndex < 0 || monthIndex > 11) return;

    overlapDayCount += 1;
    const current = overlapByMonth.get(monthIndex) || { dates: [], eventIds: new Set<string>() };
    current.dates.push(date);

    events.forEach((event) => {
      if (eventCoversDate(event, date)) {
        current.eventIds.add(event.id);
      }
    });

    overlapByMonth.set(monthIndex, current);
  });

  Array.from(overlapByMonth.entries())
    .sort(([a], [b]) => a - b)
    .forEach(([monthIndex, data]) => {
      overlaps.push({
        index: monthIndex,
        label: MONTHS[monthIndex],
        days: data.dates.length,
        dates: data.dates.sort((a, b) => a.localeCompare(b)),
        activities: events
          .filter((event) => data.eventIds.has(event.id))
          .map((event) => ({
            id: event.id,
            title: event.title,
            team_nama: event.extendedProps.team_nama,
          })),
      });
    });

  const densestMonth = topMonths[0] || null;

  return {
    total: eventsInYear.size,
    activeMonths: activeMonths.size,
    overlapDayCount,
    activeDays: Object.keys(dailyCounts).length,
    densestMonth,
    monthlyCounts,
    teamSummary,
    topMonths,
    overlaps,
    statusCounts: buildStatusCounts(events),
    employeeConflicts: buildEmployeeConflicts(events, year),
  };
}

export function buildEmployeeSummary(
  events: CalendarEvent[],
  users: User[],
  year: number,
  monthlyThreshold = 100
): EmployeeSummary[] {
  const userMap = new Map(users.map((user) => [user.id, user]));
  const summaries = new Map<
    number,
    {
      id: number;
      nama: string;
      jabatan?: string;
      teams: Map<number, string>;
      eventMap: Map<string, CalendarEvent>;
      picEventIds: Set<string>;
      days: Set<string>;
      assignmentDays: number;
      periodStart?: string;
      periodEnd?: string;
      dailyCounts: Map<string, number>;
      monthlyPoints: Record<string, number>;
    }
  >();

  collectAssignmentPeriods(events, year).forEach(({ event, assignment, start, end }) => {
    const user = userMap.get(assignment.user_id);
    const existing = summaries.get(assignment.user_id) || {
      id: assignment.user_id,
      nama: assignment.nama || user?.nama || 'Pegawai',
      jabatan: user?.jabatan,
      teams: new Map<number, string>(),
      eventMap: new Map<string, CalendarEvent>(),
      picEventIds: new Set<string>(),
      days: new Set<string>(),
      assignmentDays: 0,
      dailyCounts: new Map<string, number>(),
      monthlyPoints: {},
    };

    existing.eventMap.set(event.id, event);
    existing.teams.set(event.extendedProps.team_id, event.extendedProps.team_nama || 'Tanpa tim');

    const dates = eachDateInclusive(start, end);
    existing.assignmentDays += dates.length;

    dates.forEach((date) => {
      existing.days.add(date);
      existing.dailyCounts.set(date, (existing.dailyCounts.get(date) || 0) + 1);
    });

    if (!existing.periodStart || start < existing.periodStart) existing.periodStart = start;
    if (!existing.periodEnd || end > existing.periodEnd) existing.periodEnd = end;

    const role = assignment.peran.toLowerCase();
    if (role.includes('pic') || role.includes('penanggung jawab') || role.includes('ketua')) {
      existing.picEventIds.add(event.id);
    }

    const volume = assignment.volume ?? 1;
    const totalPoints = Math.round(volume * event.extendedProps.bobot * 100) / 100;
    const distribution = splitPointsByMonth(
      totalPoints,
      assignment.mulai || event.start,
      assignment.selesai || getEventInclusiveEnd(event)
    );

    Object.entries(distribution).forEach(([monthKey, points]) => {
      if (!monthKey.startsWith(`${year}-`)) return;
      existing.monthlyPoints[monthKey] = Math.round(((existing.monthlyPoints[monthKey] || 0) + points) * 100) / 100;
    });

    summaries.set(assignment.user_id, existing);
  });

  return Array.from(summaries.values())
    .map((summary) => {
      const peakEntry = Object.entries(summary.monthlyPoints).sort(([, a], [, b]) => b - a)[0];
      const points = peakEntry ? peakEntry[1] : 0;
      const persentase = monthlyThreshold > 0 ? (points / monthlyThreshold) * 100 : 0;
      const peakMonth = peakEntry
        ? {
            key: peakEntry[0],
            label: `${MONTHS[Number(peakEntry[0].slice(5, 7)) - 1]} ${peakEntry[0].slice(0, 4)}`,
            points: peakEntry[1],
          }
        : undefined;

      return {
        id: summary.id,
        nama: summary.nama,
        jabatan: summary.jabatan,
        teams: Array.from(summary.teams.entries())
          .map(([id, nama]) => ({ id, nama }))
          .sort((a, b) => a.nama.localeCompare(b.nama)),
        events: Array.from(summary.eventMap.values()),
        picCount: summary.picEventIds.size,
        busyDays: summary.days.size,
        assignmentDays: summary.assignmentDays,
        periodStart: summary.periodStart,
        periodEnd: summary.periodEnd,
        maxConcurrent: Math.max(0, ...Array.from(summary.dailyCounts.values())),
        points,
        peakMonth,
        indicator: determineWorkloadIndicator(persentase),
      };
    })
    .sort((a, b) => b.events.length - a.events.length || a.nama.localeCompare(b.nama));
}

export function buildUsersFromTeams(teams: Team[]): User[] {
  const map = new Map<number, User>();

  teams.forEach((team) => {
    team.members?.forEach((member) => {
      if (member.aktif !== false) map.set(member.id, member);
    });
    if (team.ketua && team.ketua.aktif !== false) {
      map.set(team.ketua.id, team.ketua);
    }
  });

  return Array.from(map.values()).sort((a, b) => a.nama.localeCompare(b.nama));
}

export function getYearOptions(): number[] {
  const current = new Date().getFullYear();
  return Array.from({ length: 7 }, (_, index) => current - 3 + index);
}

export function getTimelinePosition(event: CalendarEvent, year: number): { left: number; width: number } {
  const totalDays = getDaysInYear(year);
  const yearStart = `${year}-01-01`;
  const yearEnd = `${year}-12-31`;
  const inclusiveEnd = getEventInclusiveEnd(event);

  if (event.start > yearEnd || inclusiveEnd < yearStart) {
    return { left: 0, width: 0 };
  }

  const clamp = (value: number) => Math.min(Math.max(value, 1), totalDays);
  const startDay = clamp(getDayOfYearNumber(event.start < yearStart ? yearStart : event.start));
  const endDay = clamp(getDayOfYearNumber(inclusiveEnd > yearEnd ? yearEnd : inclusiveEnd));

  const left = ((startDay - 1) / totalDays) * 100;
  const width = ((endDay - startDay + 1) / totalDays) * 100;

  return { left, width: Math.min(width, 100 - left) };
}

/** Posisi persentase tanggal tertentu di dalam tahun (untuk penanda hari ini). */
export function getDayOfYearPercent(iso: string, year: number): number {
  const total = getDaysInYear(year);
  const day = Math.min(Math.max(getDayOfYearNumber(iso), 1), total);
  return ((day - 1) / total) * 100;
}
