import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  BarChart3,
  CalendarDays,
  CheckCircle2,
  LayoutGrid,
  List,
  Plus,
  Users,
  X,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import type { Team, User } from '../types';
import { ActivityDetailModal } from './CalendarActivityDetailModal';
import { AddActivityModal } from './CalendarAddActivityModal';
import { EmployeeTab } from './CalendarEmployeeTab';
import { HeatmapTab } from './CalendarHeatmapTab';
import { MonthlyCalendarTab } from './CalendarMonthlyCalendarTab';
import { SummaryTab } from './CalendarSummaryTab';
import { TimelineTab } from './CalendarTimelineTab';
import type { CalendarEvent, CalendarTab } from './calendarTypes';
import {
  PALETTE,
  buildAnalytics,
  buildDailyCounts,
  buildEmployeeSummary,
  buildUsersFromTeams,
  createTeamStyles,
  getYearOptions,
} from './calendarUtils';

const selectClass =
  'h-10 rounded-lg border border-[#E3EEFA] bg-white px-3 text-xs font-medium outline-none transition focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]';

const PAGE_LOADED_AT = new Date();

export const CalendarPage: React.FC = () => {
  const { user } = useAuth();
  const peran = user?.peran;
  const now = PAGE_LOADED_AT;

  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth());
  const [teamFilter, setTeamFilter] = useState('');
  const [activeTab, setActiveTab] = useState<CalendarTab>('heatmap');

  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [adminUsers, setAdminUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reloadKey, setReloadKey] = useState(0);

  const [showAdd, setShowAdd] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const [notice, setNotice] = useState('');

  const loadData = useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      const [calendarResponse, teamResponse] = await Promise.all([
        api.get('/calendar', {
          params: {
            start: `${year}-01-01`,
            end: `${year}-12-31`,
            ...(teamFilter ? { team_id: Number(teamFilter) } : {}),
          },
        }),
        api.get('/teams', { params: { per_page: 100 } }),
      ]);

      setEvents((calendarResponse.data?.events || []) as CalendarEvent[]);
      setTeams((teamResponse.data?.data || []) as Team[]);

      if (peran === 'admin') {
        try {
          const userResponse = await api.get('/users', { params: { per_page: 100, aktif: true } });
          setAdminUsers((userResponse.data?.data || []) as User[]);
        } catch {
          setAdminUsers([]);
        }
      } else {
        setAdminUsers([]);
      }
    } catch (requestError: unknown) {
      const maybeError = requestError as { response?: { data?: { message?: string } }; message?: string };
      setError(
        maybeError.response?.data?.message ||
          maybeError.message ||
          'Gagal mengambil data kalender. Periksa koneksi ke server.'
      );
    } finally {
      setLoading(false);
    }
  }, [year, teamFilter, peran]);

  useEffect(() => {
    loadData();
  }, [loadData, reloadKey]);

  useEffect(() => {
    if (!notice) return;
    const timer = window.setTimeout(() => setNotice(''), 5000);
    return () => window.clearTimeout(timer);
  }, [notice]);

  const activeTeams = useMemo(() => teams.filter((team) => team.aktif !== false), [teams]);

  const users = useMemo(() => {
    if (adminUsers.length > 0) {
      return adminUsers.filter((item) => item.aktif !== false).sort((a, b) => a.nama.localeCompare(b.nama));
    }
    return buildUsersFromTeams(activeTeams);
  }, [adminUsers, activeTeams]);

  const canManage = useMemo(
    () => user?.peran === 'admin' || activeTeams.some((team) => team.ketua_id === user?.id),
    [user, activeTeams]
  );

  const manageableTeams = useMemo(() => {
    if (user?.peran === 'admin') return activeTeams;
    return activeTeams.filter((team) => team.ketua_id === user?.id);
  }, [user, activeTeams]);

  const teamStyles = useMemo(() => createTeamStyles(activeTeams), [activeTeams]);
  const dailyCounts = useMemo(() => buildDailyCounts(events, year), [events, year]);
  const analytics = useMemo(() => buildAnalytics(events, activeTeams, year), [events, activeTeams, year]);
  const employees = useMemo(() => buildEmployeeSummary(events, users, year), [events, users, year]);
  const eventsById = useMemo(() => new Map(events.map((event) => [event.id, event])), [events]);

  const defaultTeamId = useMemo(() => {
    if (teamFilter && manageableTeams.some((team) => String(team.id) === teamFilter)) {
      return Number(teamFilter);
    }
    return manageableTeams[0]?.id;
  }, [teamFilter, manageableTeams]);

  const handleYearChange = (value: number) => {
    setYear(value);
    setMonth(value === now.getFullYear() ? now.getMonth() : 0);
  };

  const changeMonth = (offset: number) => {
    const total = year * 12 + month + offset;
    const nextYear = Math.floor(total / 12);
    const nextMonth = ((total % 12) + 12) % 12;
    setYear(nextYear);
    setMonth(nextMonth);
  };

  const handleSaved = async (startYear?: number) => {
    setShowAdd(false);
    setNotice('Kegiatan berhasil ditambahkan.');

    if (startYear && startYear !== year) {
      setYear(startYear);
      setMonth(startYear === now.getFullYear() ? now.getMonth() : 0);
    }

    setReloadKey((current) => current + 1);
  };

  const tabs: { id: CalendarTab; label: string; icon: React.ReactNode }[] = [
    { id: 'heatmap', label: 'Heatmap', icon: <LayoutGrid className="h-3.5 w-3.5" /> },
    { id: 'timeline', label: 'Timeline', icon: <List className="h-3.5 w-3.5" /> },
    { id: 'monthly', label: 'Kalender Bulanan', icon: <CalendarDays className="h-3.5 w-3.5" /> },
    { id: 'summary', label: 'Ringkasan & Overlap', icon: <BarChart3 className="h-3.5 w-3.5" /> },
    { id: 'employees', label: 'Rekap Karyawan', icon: <Users className="h-3.5 w-3.5" /> },
  ];

  return (
    <div className="mx-auto max-w-[1500px] space-y-4">
      <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex items-start gap-3">
          <span
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
            style={{ backgroundColor: PALETTE.paleBlue, color: PALETTE.blue }}
          >
            <CalendarDays className="h-5 w-5" />
          </span>
          <div>
            <h1 className="text-lg font-bold leading-tight" style={{ color: PALETTE.navy }}>
              Kalender Kegiatan
            </h1>
            <p className="mt-0.5 text-xs" style={{ color: PALETTE.slate }}>
              Agenda kegiatan seluruh tim dalam satu tahun.
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <select
            aria-label="Pilih tahun"
            value={year}
            onChange={(event) => handleYearChange(Number(event.target.value))}
            className={selectClass}
            style={{ color: PALETTE.ink }}
          >
            {getYearOptions().map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>

          <select
            aria-label="Filter tim"
            value={teamFilter}
            onChange={(event) => setTeamFilter(event.target.value)}
            className={`${selectClass} max-w-[190px]`}
            style={{ color: PALETTE.ink }}
          >
            <option value="">Semua Tim</option>
            {activeTeams.map((team) => (
              <option key={team.id} value={team.id}>
                {team.nama}
              </option>
            ))}
          </select>

          {canManage && (
            <button
              type="button"
              onClick={() => setShowAdd(true)}
              className="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-4 text-xs font-semibold text-white transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2"
              style={{ backgroundColor: PALETTE.blue }}
            >
              <Plus className="h-4 w-4" />
              Tambah
            </button>
          )}
        </div>
      </div>

      <div
        className="flex gap-1 overflow-x-auto border-b border-[#E3EEFA] pb-2"
        role="tablist"
        aria-label="Tampilan kalender kegiatan"
      >
        {tabs.map((tab) => {
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              type="button"
              role="tab"
              aria-selected={isActive}
              onClick={() => setActiveTab(tab.id)}
              className={`inline-flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-md px-3 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 ${
                isActive ? 'text-white shadow-sm' : 'hover:bg-[#E3EEFA]'
              }`}
              style={
                isActive
                  ? { backgroundColor: PALETTE.blue }
                  : { color: PALETTE.slate }
              }
            >
              {tab.icon}
              {tab.label}
            </button>
          );
        })}
      </div>

      {notice && (
        <div
          role="status"
          className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-xs"
          style={{ backgroundColor: PALETTE.mint, borderColor: PALETTE.teal, color: PALETTE.teal }}
        >
          <span className="flex items-center gap-2 font-medium">
            <CheckCircle2 className="h-4 w-4" />
            {notice}
          </span>
          <button type="button" onClick={() => setNotice('')} aria-label="Tutup notifikasi">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      )}

      {error && !loading && (
        <div
          className="flex flex-col items-start gap-2 rounded-lg border px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between"
          style={{ backgroundColor: PALETTE.blush, borderColor: PALETTE.coral, color: PALETTE.coral }}
          role="alert"
        >
          <span>{error}</span>
          <button
            type="button"
            onClick={() => setReloadKey((current) => current + 1)}
            className="rounded-lg bg-white px-3 py-1.5 font-semibold"
            style={{ color: PALETTE.coral }}
          >
            Coba lagi
          </button>
        </div>
      )}

      {loading ? (
        <CalendarSkeleton />
      ) : error ? null : (
        <>
          {activeTab === 'heatmap' && <HeatmapTab year={year} dailyCounts={dailyCounts} />}

          {activeTab === 'timeline' && (
            <TimelineTab
              year={year}
              events={events}
              teams={activeTeams}
              teamStyles={teamStyles}
              onSelect={setSelectedEvent}
            />
          )}

          {activeTab === 'monthly' && (
            <MonthlyCalendarTab
              year={year}
              month={month}
              events={events}
              teams={activeTeams}
              teamStyles={teamStyles}
              onPrevMonth={() => changeMonth(-1)}
              onNextMonth={() => changeMonth(1)}
              onSelect={setSelectedEvent}
            />
          )}

          {activeTab === 'summary' && (
            <SummaryTab
              year={year}
              analytics={analytics}
              teams={activeTeams}
              teamStyles={teamStyles}
              onSelect={setSelectedEvent}
              eventsById={eventsById}
            />
          )}

          {activeTab === 'employees' && (
            <EmployeeTab
              year={year}
              employees={employees}
              teamStyles={teamStyles}
              onSelect={setSelectedEvent}
            />
          )}
        </>
      )}

      {canManage && (
        <AddActivityModal
          open={showAdd}
          teams={manageableTeams}
          users={users}
          defaultTeamId={defaultTeamId}
          onClose={() => setShowAdd(false)}
          onSaved={handleSaved}
        />
      )}

      {selectedEvent && (
        <ActivityDetailModal
          event={selectedEvent}
          teamStyles={teamStyles}
          onClose={() => setSelectedEvent(null)}
        />
      )}
    </div>
  );
};

const CalendarSkeleton: React.FC = () => (
  <div
    className="rounded-xl border border-[#E3EEFA] bg-white p-5"
    aria-busy="true"
    aria-label="Memuat data kalender"
  >
    <div className="mb-4 flex flex-col gap-2">
      <div className="h-4 w-40 animate-pulse rounded bg-[#E3EEFA]" />
      <div className="h-3 w-64 animate-pulse rounded bg-[#F7F9FC]" />
    </div>

    <div className="grid grid-cols-7 gap-1">
      {Array.from({ length: 35 }, (_, index) => (
        <div
          key={index}
          className="h-7 animate-pulse rounded-sm bg-[#F7F9FC]"
          style={{ animationDelay: `${index * 15}ms` }}
        />
      ))}
    </div>
  </div>
);
