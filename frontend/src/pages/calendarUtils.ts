import type { Team, User } from '../types';
import type {
  CalendarAnalytics,
  CalendarEvent,
  EmployeeSummary,
  MonthBreakdown,
  OverlapMonth,
  TeamStyle,
  TeamWorkloadSummary,
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

  const overlapByMonth = new Map<number, { days: number; eventIds: Set<string> }>();

  Object.entries(dailyCounts).forEach(([date, count]) => {
    if (count < 2) return;
    const monthIndex = Number(date.slice(5, 7)) - 1;
    if (monthIndex < 0 || monthIndex > 11) return;

    overlapDayCount += 1;
    const current = overlapByMonth.get(monthIndex) || { days: 0, eventIds: new Set<string>() };
    current.days += 1;

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
        days: data.days,
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
    densestMonth,
    monthlyCounts,
    teamSummary,
    topMonths,
    overlaps,
  };
}

export function buildEmployeeSummary(
  events: CalendarEvent[],
  users: User[],
  year: number
): EmployeeSummary[] {
  const userMap = new Map(users.map((user) => [user.id, user]));
  const summaries = new Map<
    number,
    { id: number; nama: string; jabatan?: string; eventMap: Map<string, CalendarEvent>; picEventIds: Set<string>; days: Set<string> }
  >();

  events.forEach((event) => {
    const days = eachDateInRange(event.start, event.end).filter(
      (date) => Number(date.slice(0, 4)) === year
    );
    if (days.length === 0) return;

    event.extendedProps.assigned_users.forEach((assignment) => {
      const existing = summaries.get(assignment.user_id) || {
        id: assignment.user_id,
        nama: assignment.nama || userMap.get(assignment.user_id)?.nama || 'Pegawai',
        jabatan: userMap.get(assignment.user_id)?.jabatan,
        eventMap: new Map<string, CalendarEvent>(),
        picEventIds: new Set<string>(),
        days: new Set<string>(),
      };

      existing.eventMap.set(event.id, event);
      days.forEach((day) => existing.days.add(day));

      const role = assignment.peran.toLowerCase();
      if (role.includes('pic') || role.includes('penanggung jawab') || role.includes('ketua')) {
        existing.picEventIds.add(event.id);
      }

      summaries.set(assignment.user_id, existing);
    });
  });

  return Array.from(summaries.values())
    .map((summary) => ({
      id: summary.id,
      nama: summary.nama,
      jabatan: summary.jabatan,
      events: Array.from(summary.eventMap.values()),
      picCount: summary.picEventIds.size,
      busyDays: summary.days.size,
    }))
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
  const yearStart = new Date(year, 0, 1).getTime();
  const yearEnd = new Date(year, 11, 31).getTime();
  const dayMs = 24 * 60 * 60 * 1000;
  const total = yearEnd - yearStart + dayMs;

  const rawStart = parseIsoDate(event.start).getTime();
  const rawEnd = parseIsoDate(getEventInclusiveEnd(event)).getTime();
  const start = Math.max(rawStart, yearStart);
  const end = Math.min(rawEnd, yearEnd);

  const left = ((start - yearStart) / total) * 100;
  const width = Math.max(((end - start + dayMs) / total) * 100, 0.8);

  return { left, width: Math.min(width, 100 - left) };
}
