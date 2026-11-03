import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  BarChart3,
  CalendarDays,
  LayoutGrid,
  List,
  Search,
  Users,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import type { Team, User } from '../types';
import { ActivityDetailModal } from './CalendarActivityDetailModal';
import { EmployeeTab } from './CalendarEmployeeTab';
import { HeatmapTab } from './CalendarHeatmapTab';
import { MonthlyCalendarTab } from './CalendarMonthlyCalendarTab';
import { SummaryTab } from './CalendarSummaryTab';
import { TimelineTab } from './CalendarTimelineTab';
import type { CalendarEvent, CalendarFilters, CalendarTab } from './calendarTypes';
import {
  JENIS_KEGIATAN,
  PALETTE,
  applyCalendarFilters,
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

const STATUS_OPTIONS = ['Belum Mulai', 'Berjalan', 'Selesai', 'Terlambat'];

export const CalendarPage: React.FC = () => {
  const { user } = useAuth();
  const peran = user?.peran;
  const now = PAGE_LOADED_AT;

  const [filters, setFilters] = useState<CalendarFilters>({
    year: now.getFullYear(),
    teamId: '',
    userId: '',
    status: '',
    jenis: '',
    search: '',
  });
  const [month, setMonth] = useState(now.getMonth());
  const [activeTab, setActiveTab] = useState<CalendarTab>('heatmap');

  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [adminUsers, setAdminUsers] = useState<User[]>([]);
  const [monthlyThreshold, setMonthlyThreshold] = useState(100);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reloadKey, setReloadKey] = useState(0);

  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const year = filters.year;

  const loadData = useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      // Satu permintaan kalender per tahun; filter tim/pegawai/status/jenis/search
      // diterapkan di sisi klien agar seluruh tab selalu konsisten.
      const [calendarResponse, teamResponse] = await Promise.all([
        api.get('/calendar', {
          params: {
            start: `${year}-01-01`,
            end: `${year}-12-31`,
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

      try {
        const settingResponse = await api.get('/settings');
        const threshold = Number(settingResponse.data?.settings?.ambang_beban_bulanan);
        setMonthlyThreshold(Number.isFinite(threshold) && threshold > 0 ? threshold : 100);
      } catch {
        setMonthlyThreshold(100);
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
  }, [year, peran]);

  useEffect(() => {
    loadData();
  }, [loadData, reloadKey]);

  const activeTeams = useMemo(() => teams.filter((team) => team.aktif !== false), [teams]);

  const users = useMemo(() => {
    if (adminUsers.length > 0) {
      return adminUsers.filter((item) => item.aktif !== false).sort((a, b) => a.nama.localeCompare(b.nama));
    }
    return buildUsersFromTeams(activeTeams);
  }, [adminUsers, activeTeams]);

  // Pegawai yang muncul di filter: gabungan pegawai dari data tim dan penugasan.
  const filterUsers = useMemo(() => {
    const map = new Map<number, User>();
    users.forEach((item) => map.set(item.id, item));
    events.forEach((event) => {
      event.extendedProps.assigned_users.forEach((assignment) => {
        if (!map.has(assignment.user_id)) {
          map.set(assignment.user_id, {
            id: assignment.user_id,
            nama: assignment.nama,
            peran: 'pegawai',
            aktif: true,
          } as User);
        }
      });
    });
    return Array.from(map.values()).sort((a, b) => a.nama.localeCompare(b.nama));
  }, [users, events]);

  const filteredEvents = useMemo(() => applyCalendarFilters(events, filters), [events, filters]);
  const hasActiveFilters = Boolean(
    filters.teamId || filters.userId || filters.status || filters.jenis || filters.search.trim()
  );

  const teamStyles = useMemo(() => createTeamStyles(activeTeams), [activeTeams]);
  const dailyCounts = useMemo(() => buildDailyCounts(filteredEvents, year), [filteredEvents, year]);
  const analytics = useMemo(
    () => buildAnalytics(filteredEvents, activeTeams, year),
    [filteredEvents, activeTeams, year]
  );
  const employees = useMemo(
    () => buildEmployeeSummary(filteredEvents, filterUsers, year, monthlyThreshold),
    [filteredEvents, filterUsers, year, monthlyThreshold]
  );
  const eventsById = useMemo(
    () => new Map(filteredEvents.map((event) => [event.id, event])),
    [filteredEvents]
  );

  const updateFilter = <K extends keyof CalendarFilters>(key: K, value: CalendarFilters[K]) => {
    setFilters((current) => ({ ...current, [key]: value }));
  };

  const resetFilters = () => {
    setFilters((current) => ({
      year: current.year,
      teamId: '',
      userId: '',
      status: '',
      jenis: '',
      search: '',
    }));
  };

  const handleYearChange = (value: number) => {
    setFilters((current) => ({ ...current, year: value }));
    setMonth(value === now.getFullYear() ? now.getMonth() : 0);
  };

  const changeMonth = (offset: number) => {
    const total = year * 12 + month + offset;
    const nextYear = Math.floor(total / 12);
    const nextMonth = ((total % 12) + 12) % 12;
    setFilters((current) => ({ ...current, year: nextYear }));
    setMonth(nextMonth);
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
          <button
            type="button"
            onClick={() => setReloadKey((current) => current + 1)}
            disabled={loading}
            className="inline-flex min-h-10 items-center rounded-lg border border-[#E3EEFA] px-3 text-xs font-medium transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            style={{ color: PALETTE.slate }}
          >
            Muat ulang
          </button>
        </div>
      </div>

      {/* Filter terpusat: dipakai oleh seluruh tab kalender. */}
      <div className="rounded-xl border border-[#E3EEFA] bg-white p-3 shadow-sm">
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          <label className="block">
            <span className="mb-1 block text-[10px] font-semibold" style={{ color: PALETTE.slate }}>
              Tahun
            </span>
            <select
              aria-label="Pilih tahun"
              value={year}
              onChange={(event) => handleYearChange(Number(event.target.value))}
              className={`${selectClass} w-full`}
              style={{ color: PALETTE.ink }}
            >
              {getYearOptions().map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-1 block text-[10px] font-semibold" style={{ color: PALETTE.slate }}>
              Tim
            </span>
            <select
              aria-label="Filter tim"
              value={filters.teamId}
              onChange={(event) => updateFilter('teamId', event.target.value)}
              className={`${selectClass} w-full`}
              style={{ color: PALETTE.ink }}
            >
              <option value="">Semua Tim</option>
              {activeTeams.map((team) => (
                <option key={team.id} value={team.id}>
                  {team.nama}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-1 block text-[10px] font-semibold" style={{ color: PALETTE.slate }}>
              Pegawai
            </span>
            <select
              aria-label="Filter pegawai"
              value={filters.userId}
              onChange={(event) => updateFilter('userId', event.target.value)}
              className={`${selectClass} w-full`}
              style={{ color: PALETTE.ink }}
            >
              <option value="">Semua Pegawai</option>
              {filterUsers.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nama}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-1 block text-[10px] font-semibold" style={{ color: PALETTE.slate }}>
              Status
            </span>
            <select
              aria-label="Filter status kegiatan"
              value={filters.status}
              onChange={(event) => updateFilter('status', event.target.value)}
              className={`${selectClass} w-full`}
              style={{ color: PALETTE.ink }}
            >
              <option value="">Semua Status</option>
              {STATUS_OPTIONS.map((status) => (
                <option key={status} value={status}>
                  {status}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-1 block text-[10px] font-semibold" style={{ color: PALETTE.slate }}>
              Jenis
            </span>
            <select
              aria-label="Filter jenis kegiatan"
              value={filters.jenis}
              onChange={(event) => updateFilter('jenis', event.target.value)}
              className={`${selectClass} w-full`}
              style={{ color: PALETTE.ink }}
            >
              <option value="">Semua Jenis</option>
              {JENIS_KEGIATAN.map((jenis) => (
                <option key={jenis} value={jenis}>
                  {jenis}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-1 block text-[10px] font-semibold" style={{ color: PALETTE.slate }}>
              Kata kunci
            </span>
            <span className="relative block">
              <Search
                className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2"
                style={{ color: PALETTE.slate }}
              />
              <input
                type="search"
                aria-label="Cari nama kegiatan"
                value={filters.search}
                onChange={(event) => updateFilter('search', event.target.value)}
                placeholder="Cari kegiatan..."
                className={`${selectClass} w-full pl-8`}
                style={{ color: PALETTE.ink }}
              />
            </span>
          </label>
        </div>

        <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
          <p className="text-[10px]" style={{ color: PALETTE.slate }}>
            {loading
              ? 'Memuat data...'
              : `${filteredEvents.length} dari ${events.length} kegiatan ditampilkan.`}
          </p>
          <button
            type="button"
            onClick={resetFilters}
            disabled={!hasActiveFilters}
            className="rounded-lg border border-[#E3EEFA] px-3 py-1.5 text-[11px] font-medium transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            style={{ color: PALETTE.slate }}
          >
            Reset filter
          </button>
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
              style={isActive ? { backgroundColor: PALETTE.blue } : { color: PALETTE.slate }}
            >
              {tab.icon}
              {tab.label}
            </button>
          );
        })}
      </div>

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
          {filteredEvents.length === 0 && (
            <EmptyState
              hasActiveFilters={hasActiveFilters}
              onReset={resetFilters}
              year={year}
              hasEvents={events.length > 0}
            />
          )}

          {activeTab === 'heatmap' && (
            <HeatmapTab
              year={year}
              events={filteredEvents}
              dailyCounts={dailyCounts}
              teamStyles={teamStyles}
              onSelect={setSelectedEvent}
              onResetFilters={resetFilters}
              hasActiveFilters={hasActiveFilters}
            />
          )}

          {activeTab === 'timeline' && (
            <TimelineTab
              year={year}
              events={filteredEvents}
              teams={activeTeams}
              teamStyles={teamStyles}
              onSelect={setSelectedEvent}
              onResetFilters={resetFilters}
              hasActiveFilters={hasActiveFilters}
            />
          )}

          {activeTab === 'monthly' && (
            <MonthlyCalendarTab
              year={year}
              month={month}
              events={filteredEvents}
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
              onResetFilters={resetFilters}
              hasActiveFilters={hasActiveFilters}
            />
          )}

          {activeTab === 'employees' && (
            <EmployeeTab
              year={year}
              employees={employees}
              teamStyles={teamStyles}
              onSelect={setSelectedEvent}
              onResetFilters={resetFilters}
              hasActiveFilters={hasActiveFilters}
            />
          )}
        </>
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

const EmptyState: React.FC<{
  hasActiveFilters: boolean;
  onReset: () => void;
  year: number;
  hasEvents: boolean;
}> = ({ hasActiveFilters, onReset, year, hasEvents }) => (
  <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
    <div className="rounded-lg border border-dashed border-[#E3EEFA] px-4 py-12 text-center">
      <p className="text-sm font-medium" style={{ color: PALETTE.ink }}>
        {hasActiveFilters
          ? 'Tidak ada kegiatan yang cocok dengan kombinasi filter.'
          : `Belum ada kegiatan pada tahun ${year}.`}
      </p>
      <p className="mt-1 text-xs" style={{ color: PALETTE.slate }}>
        {hasActiveFilters
          ? 'Ubah atau reset filter untuk menampilkan kegiatan lain.'
          : hasEvents
            ? 'Coba pilih tahun lain.'
            : 'Kegiatan baru ditambahkan melalui Daftar Kegiatan.'}
      </p>
      {hasActiveFilters && (
        <button
          type="button"
          onClick={onReset}
          className="mt-3 rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition hover:brightness-95"
          style={{ backgroundColor: PALETTE.blue }}
        >
          Reset filter
        </button>
      )}
    </div>
  </section>
);

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
