import React, { useMemo, useState } from 'react';
import { ArrowUpDown, Search } from 'lucide-react';
import type { CalendarEvent, EmployeeSummary, TeamStyle, WorkloadIndicator } from './calendarTypes';
import { PALETTE, WORKLOAD_LABELS, formatDateId, getTeamStyle } from './calendarUtils';

interface EmployeeTabProps {
  year: number;
  employees: EmployeeSummary[];
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
  onResetFilters?: () => void;
  hasActiveFilters?: boolean;
}

const WORKLOAD_STYLES: Record<WorkloadIndicator, { bg: string; text: string; border: string }> = {
  aman: { bg: PALETTE.mint, text: PALETTE.teal, border: PALETTE.teal },
  hampir_penuh: { bg: PALETTE.peach, text: PALETTE.navy, border: PALETTE.amber },
  melebihi: { bg: PALETTE.blush, text: PALETTE.coral, border: PALETTE.coral },
};

export const EmployeeTab: React.FC<EmployeeTabProps> = ({
  year,
  employees,
  teamStyles,
  onSelect,
  onResetFilters,
  hasActiveFilters = false,
}) => {
  const [query, setQuery] = useState('');
  const [teamFilter, setTeamFilter] = useState('');
  const [sortDirection, setSortDirection] = useState<'desc' | 'asc'>('desc');
  const [expanded, setExpanded] = useState<Set<number>>(new Set());

  const teamOptions = useMemo(() => {
    const map = new Map<number, string>();
    employees.forEach((employee) => {
      employee.teams.forEach((team) => map.set(team.id, team.nama));
    });
    return Array.from(map.entries())
      .map(([id, nama]) => ({ id, nama }))
      .sort((a, b) => a.nama.localeCompare(b.nama));
  }, [employees]);

  const filtered = useMemo(() => {
    const keyword = query.trim().toLowerCase();
    const result = employees.filter((employee) => {
      if (teamFilter && !employee.teams.some((team) => String(team.id) === teamFilter)) {
        return false;
      }
      if (!keyword) return true;
      return (
        employee.nama.toLowerCase().includes(keyword) ||
        (employee.jabatan || '').toLowerCase().includes(keyword)
      );
    });

    return [...result].sort((a, b) => {
      const difference = a.events.length - b.events.length;
      return sortDirection === 'desc' ? -difference : difference;
    });
  }, [employees, query, teamFilter, sortDirection]);

  const toggleExpanded = (id: number) => {
    setExpanded((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const resetLocalFilters = () => {
    setQuery('');
    setTeamFilter('');
  };

  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
      <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h2 className="text-sm font-bold sm:text-base" style={{ color: PALETTE.navy }}>
            Rekap Karyawan {year} ({employees.length} pegawai)
          </h2>
          <p className="mt-0.5 text-xs" style={{ color: PALETTE.slate }}>
            Dihitung dari tanggal efektif penugasan, bukan periode keseluruhan kegiatan.
          </p>
        </div>

        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <label className="relative block w-full sm:w-56">
            <span className="sr-only">Cari nama pegawai</span>
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2"
              style={{ color: PALETTE.slate }}
            />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Cari nama pegawai..."
              className="h-10 w-full rounded-lg border border-[#E3EEFA] bg-white pl-9 pr-3 text-xs outline-none transition focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]"
              style={{ color: PALETTE.ink }}
            />
          </label>

          <label className="block w-full sm:w-44">
            <span className="sr-only">Filter tim</span>
            <select
              value={teamFilter}
              onChange={(event) => setTeamFilter(event.target.value)}
              className="h-10 w-full rounded-lg border border-[#E3EEFA] bg-white px-3 text-xs outline-none transition focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]"
              style={{ color: PALETTE.ink }}
            >
              <option value="">Semua tim</option>
              {teamOptions.map((team) => (
                <option key={team.id} value={team.id}>
                  {team.nama}
                </option>
              ))}
            </select>
          </label>

          {(query || teamFilter) && (
            <button
              type="button"
              onClick={resetLocalFilters}
              className="h-10 rounded-lg border border-[#E3EEFA] px-3 text-xs font-medium transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2"
              style={{ color: PALETTE.slate }}
            >
              Reset
            </button>
          )}
        </div>
      </div>

      <div className="mb-3 flex flex-wrap items-center gap-2 text-[10px]" style={{ color: PALETTE.slate }}>
        <span className="font-semibold">Beban:</span>
        {(Object.keys(WORKLOAD_LABELS) as WorkloadIndicator[]).map((key) => {
          const style = WORKLOAD_STYLES[key];
          return (
            <span
              key={key}
              className="rounded-full border px-2 py-0.5 font-semibold"
              style={{ backgroundColor: style.bg, color: style.text, borderColor: style.border }}
            >
              {WORKLOAD_LABELS[key]}
            </span>
          );
        })}
        <span className="ml-1">
          Berdasarkan puncak poin bulanan terhadap ambang beban bulanan. Tanda — berarti rincian tidak ditampilkan untuk peran yang tidak berwenang.
        </span>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[1080px] text-left text-xs">
          <thead>
            <tr className="border-b border-[#E3EEFA] text-[11px]" style={{ color: PALETTE.slate }}>
              <th className="w-10 py-2.5 pr-2 font-semibold">No</th>
              <th className="py-2.5 pr-3 font-semibold">Nama / Tim</th>
              <th className="w-24 py-2.5 pr-3 font-semibold">
                <button
                  type="button"
                  onClick={() => setSortDirection((current) => (current === 'desc' ? 'asc' : 'desc'))}
                  className="inline-flex items-center gap-1 font-semibold hover:text-bps"
                  aria-label={`Urutkan kegiatan ${sortDirection === 'desc' ? 'naik' : 'turun'}`}
                >
                  Kegiatan <ArrowUpDown className="h-3 w-3" />
                </button>
              </th>
              <th className="w-20 py-2.5 pr-3 font-semibold">Sbg PIC</th>
              <th className="w-40 py-2.5 pr-3 font-semibold">Periode</th>
              <th className="w-24 py-2.5 pr-3 font-semibold">Hari Tugas</th>
              <th className="w-24 py-2.5 pr-3 font-semibold">Bersamaan</th>
              <th className="w-36 py-2.5 pr-3 font-semibold">Beban Kerja</th>
              <th className="py-2.5 font-semibold">Daftar Kegiatan</th>
            </tr>
          </thead>

          <tbody>
            {filtered.map((employee, index) => {
              const isExpanded = expanded.has(employee.id);
              const events = [...employee.events].sort((a, b) => a.start.localeCompare(b.start));
              const visible = isExpanded ? events : events.slice(0, 3);
              const workload = WORKLOAD_STYLES[employee.indicator];

              return (
                <tr key={employee.id} className="border-b border-[#F1F5FB] align-top last:border-0">
                  <td className="py-3 pr-2" style={{ color: PALETTE.slate }}>
                    {index + 1}
                  </td>
                  <td className="py-3 pr-3">
                    <span className="block font-semibold" style={{ color: PALETTE.ink }}>
                      {employee.nama}
                    </span>
                    {employee.jabatan && (
                      <span className="mt-0.5 block text-[10px]" style={{ color: PALETTE.slate }}>
                        {employee.jabatan}
                      </span>
                    )}
                    <div className="mt-1 flex flex-wrap gap-1">
                      {employee.teams.map((team) => {
                        const style = getTeamStyle(teamStyles, team.id);
                        return (
                          <span
                            key={team.id}
                            className="rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                            style={{ backgroundColor: style.soft, color: style.text }}
                          >
                            {team.nama}
                          </span>
                        );
                      })}
                    </div>
                  </td>
                  <td className="py-3 pr-3 font-semibold" style={{ color: PALETTE.ink }}>
                    {employee.metricsHidden ? '—' : employee.events.length}
                  </td>
                  <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                    {employee.metricsHidden ? '—' : employee.picCount}
                  </td>
                  <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                    {employee.metricsHidden ? (
                      '—'
                    ) : employee.periodStart && employee.periodEnd ? (
                      <span>
                        {formatDateId(employee.periodStart)}
                        <span className="block text-[10px]" style={{ color: PALETTE.slate }}>
                          s.d. {formatDateId(employee.periodEnd)}
                        </span>
                      </span>
                    ) : (
                      <span style={{ color: PALETTE.slate }}>-</span>
                    )}
                  </td>
                  <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                    {employee.metricsHidden ? (
                      '—'
                    ) : (
                      <>
                        <span className="font-semibold">{employee.assignmentDays}</span>
                        <span className="block text-[10px]" style={{ color: PALETTE.slate }}>
                          {employee.busyDays} hari unik
                        </span>
                      </>
                    )}
                  </td>
                  <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                    {employee.metricsHidden ? (
                      '—'
                    ) : employee.maxConcurrent > 1 ? (
                      <span className="font-semibold" style={{ color: PALETTE.coral }}>
                        {employee.maxConcurrent} tugas
                      </span>
                    ) : (
                      <span style={{ color: PALETTE.slate }}>Tidak ada</span>
                    )}
                  </td>
                  <td className="py-3 pr-3">
                    {employee.metricsHidden ? (
                      <span style={{ color: PALETTE.slate }}>—</span>
                    ) : (
                      <>
                        <span
                          className="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                          style={{ backgroundColor: workload.bg, color: workload.text, borderColor: workload.border }}
                        >
                          {WORKLOAD_LABELS[employee.indicator]}
                        </span>
                        {employee.peakMonth && (
                          <span className="mt-1 block text-[10px]" style={{ color: PALETTE.slate }}>
                            {employee.peakMonth.label}: {employee.peakMonth.points} poin
                          </span>
                        )}
                      </>
                    )}
                  </td>
                  <td className="py-3">
                    {employee.metricsHidden ? (
                      <span style={{ color: PALETTE.slate }}>—</span>
                    ) : (
                      <div className="flex flex-wrap gap-1">
                        {visible.map((event) => {
                          const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
                          return (
                            <button
                              key={event.id}
                              type="button"
                              onClick={() => onSelect(event)}
                              className="max-w-[220px] truncate rounded-full border px-2 py-0.5 text-[10px] font-medium transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2"
                              style={{
                                backgroundColor: style.soft,
                                color: style.text,
                                borderColor: style.soft,
                              }}
                              title={`${event.title} (${event.extendedProps.team_nama || 'Tanpa tim'})`}
                            >
                              {event.title}
                            </button>
                          );
                        })}

                        {events.length > 3 && (
                          <button
                            type="button"
                            onClick={() => toggleExpanded(employee.id)}
                            className="rounded-full bg-[#F7F9FC] px-2 py-0.5 text-[10px] font-medium hover:bg-[#E3EEFA] focus-visible:outline-2 focus-visible:outline-offset-2"
                            style={{ color: PALETTE.slate }}
                            title={events.map((event) => event.title).join(', ')}
                          >
                            {isExpanded ? 'Sembunyikan' : `+${events.length - 3} lainnya`}
                          </button>
                        )}
                      </div>
                    )}
                  </td>
                </tr>
              );
            })}

            {filtered.length === 0 && (
              <tr>
                <td colSpan={9} className="py-10 text-center" style={{ color: PALETTE.slate }}>
                  {employees.length === 0 ? (
                    hasActiveFilters ? (
                      <span className="inline-flex flex-col items-center gap-2">
                        <span>Tidak ada pegawai yang cocok dengan filter kalender.</span>
                        {onResetFilters && (
                          <button
                            type="button"
                            onClick={onResetFilters}
                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition hover:brightness-95"
                            style={{ backgroundColor: PALETTE.blue }}
                          >
                            Reset filter
                          </button>
                        )}
                      </span>
                    ) : (
                      'Belum ada data penugasan pegawai pada tahun ini.'
                    )
                  ) : (
                    'Tidak ada pegawai yang cocok dengan pencarian atau filter tim.'
                  )}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
};
