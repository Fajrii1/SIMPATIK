import React, { useMemo, useState } from 'react';
import { ArrowUpDown, Search } from 'lucide-react';
import type { CalendarEvent, EmployeeSummary, TeamStyle } from './calendarTypes';
import { PALETTE, getTeamStyle } from './calendarUtils';

interface EmployeeTabProps {
  year: number;
  employees: EmployeeSummary[];
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
}

export const EmployeeTab: React.FC<EmployeeTabProps> = ({
  year,
  employees,
  teamStyles,
  onSelect,
}) => {
  const [query, setQuery] = useState('');
  const [sortDirection, setSortDirection] = useState<'desc' | 'asc'>('desc');
  const [expanded, setExpanded] = useState<Set<number>>(new Set());

  const filtered = useMemo(() => {
    const keyword = query.trim().toLowerCase();
    const result = employees.filter((employee) => {
      if (!keyword) return true;
      return (
        employee.nama.toLowerCase().includes(keyword) ||
        (employee.jabatan || '').toLowerCase().includes(keyword)
      );
    });

    return result.sort((a, b) => {
      const difference = a.events.length - b.events.length;
      return sortDirection === 'desc' ? -difference : difference;
    });
  }, [employees, query, sortDirection]);

  const toggleExpanded = (id: number) => {
    setExpanded((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
      <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-sm font-bold sm:text-base" style={{ color: PALETTE.navy }}>
            Rekap Karyawan {year} ({employees.length} pegawai)
          </h2>
          <p className="mt-0.5 text-xs" style={{ color: PALETTE.slate }}>
            Keterlibatan pegawai pada seluruh kegiatan tahun terpilih.
          </p>
        </div>

        <label className="relative block w-full md:w-64">
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
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[900px] text-left text-xs">
          <thead>
            <tr className="border-b border-[#E3EEFA] text-[11px]" style={{ color: PALETTE.slate }}>
              <th className="w-10 py-2.5 pr-2 font-semibold">No</th>
              <th className="py-2.5 pr-3 font-semibold">Nama</th>
              <th className="w-24 py-2.5 pr-3 font-semibold">
                <button
                  type="button"
                  onClick={() => setSortDirection((current) => (current === 'desc' ? 'asc' : 'desc'))}
                  className="inline-flex items-center gap-1 font-semibold hover:text-[#12305C]"
                  aria-label={`Urutkan kegiatan ${sortDirection === 'desc' ? 'naik' : 'turun'}`}
                >
                  Kegiatan <ArrowUpDown className="h-3 w-3" />
                </button>
              </th>
              <th className="w-20 py-2.5 pr-3 font-semibold">Sbg PIC</th>
              <th className="w-20 py-2.5 pr-3 font-semibold">Hari Sibuk</th>
              <th className="py-2.5 font-semibold">Daftar Kegiatan</th>
            </tr>
          </thead>

          <tbody>
            {filtered.map((employee, index) => {
              const isExpanded = expanded.has(employee.id);
              const events = [...employee.events].sort((a, b) => a.start.localeCompare(b.start));
              const visible = isExpanded ? events : events.slice(0, 3);

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
                  </td>
                  <td className="py-3 pr-3 font-semibold" style={{ color: PALETTE.ink }}>
                    {employee.events.length}
                  </td>
                  <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                    {employee.picCount}
                  </td>
                  <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                    {employee.busyDays}
                  </td>
                  <td className="py-3">
                    <div className="flex flex-wrap gap-1">
                      {visible.map((event) => {
                        const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
                        return (
                          <button
                            key={event.id}
                            type="button"
                            onClick={() => onSelect(event)}
                            className="max-w-[230px] truncate rounded-full border px-2 py-0.5 text-[10px] font-medium transition hover:brightness-95"
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
                          className="rounded-full bg-[#F7F9FC] px-2 py-0.5 text-[10px] font-medium hover:bg-[#E3EEFA]"
                          style={{ color: PALETTE.slate }}
                          title={events.map((event) => event.title).join(', ')}
                        >
                          {isExpanded ? 'Sembunyikan' : `+${events.length - 3} lainnya`}
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}

            {filtered.length === 0 && (
              <tr>
                <td colSpan={6} className="py-10 text-center" style={{ color: PALETTE.slate }}>
                  {employees.length === 0
                    ? 'Belum ada data penugasan pegawai pada tahun ini.'
                    : 'Tidak ada pegawai yang cocok dengan pencarian.'}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
};
