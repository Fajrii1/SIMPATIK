import React, { useEffect, useMemo, useState } from 'react';
import { X } from 'lucide-react';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  MONTHS,
  MONTHS_SHORT,
  PALETTE,
  STATUS_STYLES,
  buildIsoDate,
  eventCoversDate,
  formatDateId,
  formatDateRange,
  getEventInclusiveEnd,
  getMonthLength,
  getTeamStyle,
  getWeekdayOffset,
  heatCellStyle,
  toIsoDate,
} from './calendarUtils';

interface HeatmapTabProps {
  year: number;
  events: CalendarEvent[];
  dailyCounts: Record<string, number>;
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
  onResetFilters?: () => void;
  hasActiveFilters?: boolean;
}

const LEGEND_STEPS = [0, 1, 2, 3, 4, 5, 7];
const TODAY_ISO = toIsoDate(new Date());

interface DayDetail {
  date: string;
  events: CalendarEvent[];
}

export const HeatmapTab: React.FC<HeatmapTabProps> = ({
  year,
  events,
  dailyCounts,
  teamStyles,
  onSelect,
  onResetFilters,
  hasActiveFilters = false,
}) => {
  const [dayDetail, setDayDetail] = useState<DayDetail | null>(null);

  const { activeDays, maxCount } = useMemo(() => {
    const counts = Object.values(dailyCounts);
    return {
      activeDays: counts.filter((value) => value > 0).length,
      maxCount: counts.reduce((max, value) => Math.max(max, value), 0),
    };
  }, [dailyCounts]);

  useEffect(() => {
    if (!dayDetail) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setDayDetail(null);
    };

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [dayDetail]);

  const openDayDetail = (date: string) => {
    const dayEvents = events
      .filter((event) => eventCoversDate(event, date))
      .sort((a, b) => a.start.localeCompare(b.start) || a.title.localeCompare(b.title));

    if (dayEvents.length > 0) setDayDetail({ date, events: dayEvents });
  };

  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-3 shadow-sm sm:p-5">
      <div className="mb-3 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-sm font-bold sm:text-base" style={{ color: PALETTE.navy }}>
            Peta Kepadatan {year}
          </h2>
          <p className="mt-0.5 text-xs" style={{ color: PALETTE.slate }}>
            Jumlah kegiatan yang berjalan pada setiap tanggal. Klik tanggal untuk melihat daftarnya.
          </p>
          <p className="mt-1 text-[10px] font-medium" style={{ color: PALETTE.slate }}>
            {activeDays} hari aktif | kepadatan tertinggi {maxCount} kegiatan/hari
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-1.5 text-[10px] sm:text-[11px]" style={{ color: PALETTE.slate }}>
          <span>Sepi</span>
          {LEGEND_STEPS.map((step) => (
            <span
              key={step}
              className="flex h-4 min-w-4 items-center justify-center rounded-[3px] px-1 font-semibold"
              style={heatCellStyle(step)}
              title={`${step === 7 ? '7 atau lebih' : step} kegiatan`}
            >
              {step === 7 ? '7+' : step}
            </span>
          ))}
          <span>Padat</span>
        </div>
      </div>

      <>
        {events.length === 0 && (
          <div
            className="rounded-lg border border-dashed border-[#E3EEFA] px-4 py-3 text-center text-xs"
            style={{ color: PALETTE.slate }}
          >
            {hasActiveFilters
              ? 'Tidak ada kegiatan yang cocok dengan filter. Heatmap tetap ditampilkan dengan intensitas 0.'
              : 'Belum ada kegiatan pada tahun ini. Heatmap ditampilkan dengan intensitas 0.'}
          </div>
        )}

        {/* Tampilan mobile: tiap bulan berupa grid 7 kolom agar tanggal mudah disentuh. */}
          <div className="space-y-3 sm:hidden">
            <div className="grid grid-cols-7 gap-1 text-center text-[9px] font-semibold" style={{ color: PALETTE.slate }}>
              {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((label, index) => (
                <span key={`${label}-${index}`}>{label}</span>
              ))}
            </div>

            {MONTHS_SHORT.map((monthShort, monthIndex) => {
              const daysInMonth = getMonthLength(year, monthIndex);
              const offset = getWeekdayOffset(year, monthIndex);

              return (
                <div key={`mobile-${monthShort}`}>
                  <div className="mb-1 flex items-center justify-between">
                    <span className="text-[11px] font-semibold" style={{ color: PALETTE.ink }}>
                      {MONTHS[monthIndex]}
                    </span>
                    <span className="text-[9px]" style={{ color: PALETTE.slate }}>
                      {daysInMonth} hari
                    </span>
                  </div>

                  <div className="grid grid-cols-7 gap-1">
                    {Array.from({ length: offset }, (_, index) => (
                      <span key={`pad-${monthIndex}-${index}`} aria-hidden="true" />
                    ))}

                    {Array.from({ length: daysInMonth }, (_, dayIndex) => {
                      const day = dayIndex + 1;
                      const date = buildIsoDate(year, monthIndex, day);
                      const count = dailyCounts[date] || 0;
                      const style = heatCellStyle(count);
                      const isToday = date === TODAY_ISO;

                      return (
                        <button
                          key={`mobile-${date}`}
                          type="button"
                          onClick={() => openDayDetail(date)}
                          disabled={count === 0}
                          className={`flex h-8 items-center justify-center rounded text-[10px] font-semibold tabular-nums transition ${
                            count === 0
                              ? 'cursor-default'
                              : 'cursor-pointer active:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-1'
                          } ${isToday ? 'ring-2 ring-[#3B82C4] ring-offset-1' : ''}`}
                          style={style}
                          title={`${day} ${MONTHS[monthIndex]} ${year}: ${count} kegiatan`}
                          aria-label={`${day} ${MONTHS[monthIndex]} ${year}: ${count} kegiatan`}
                        >
                          {day}
                        </button>
                      );
                    })}
                  </div>
                </div>
              );
            })}
          </div>

          <div
            className="hidden gap-1 sm:grid"
            style={{ gridTemplateColumns: 'minmax(34px, 44px) repeat(31, minmax(0, 1fr))' }}
            role="grid"
            aria-label={`Heatmap kegiatan tahun ${year}`}
          >
          <span aria-hidden="true" />
          {Array.from({ length: 31 }, (_, index) => (
            <span
              key={`head-${index}`}
              className="hidden text-center text-[9px] font-medium sm:block"
              style={{ color: PALETTE.slate }}
              aria-hidden="true"
            >
              {index + 1}
            </span>
          ))}

          {MONTHS_SHORT.map((monthShort, monthIndex) => {
            const daysInMonth = getMonthLength(year, monthIndex);

            return (
              <React.Fragment key={monthShort}>
                <span
                  className="flex items-center text-[10px] font-semibold sm:text-[11px]"
                  style={{ color: PALETTE.ink }}
                  title={MONTHS[monthIndex]}
                >
                  {monthShort}
                </span>

                {Array.from({ length: 31 }, (_, dayIndex) => {
                  const day = dayIndex + 1;

                  if (day > daysInMonth) {
                    return <span key={`${monthIndex}-${day}`} aria-hidden="true" />;
                  }

                  const date = buildIsoDate(year, monthIndex, day);
                  const count = dailyCounts[date] || 0;
                  const style = heatCellStyle(count);
                  const isToday = date === TODAY_ISO;

                  return (
                    <button
                      key={date}
                      type="button"
                      onClick={() => openDayDetail(date)}
                      disabled={count === 0}
                      className={`flex h-5 items-center justify-center rounded-[3px] text-[8px] font-semibold tabular-nums transition sm:h-7 sm:text-[10px] ${
                        count === 0
                          ? 'cursor-default'
                          : 'cursor-pointer hover:ring-2 hover:ring-[#1F4E8C]/40 focus-visible:outline-2 focus-visible:outline-offset-1'
                      } ${isToday ? 'ring-2 ring-[#3B82C4] ring-offset-1' : ''}`}
                      style={style}
                      title={`${day} ${MONTHS[monthIndex]} ${year}: ${count} kegiatan`}
                      aria-label={`${day} ${MONTHS[monthIndex]} ${year}: ${count} kegiatan`}
                    >
                      <span className="hidden sm:inline">{count > 0 ? count : ''}</span>
                    </button>
                  );
                })}
              </React.Fragment>
            );
          })}
          </div>
        </>

      {dayDetail && (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-[#12305C]/50 p-3 sm:p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="heatmap-day-title"
          onClick={() => setDayDetail(null)}
        >
          <div
            className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-4 shadow-xl sm:p-5"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="mb-4 flex items-start justify-between gap-3">
              <div>
                <h3 id="heatmap-day-title" className="text-base font-bold" style={{ color: PALETTE.navy }}>
                  Kegiatan {formatDateId(dayDetail.date)}
                </h3>
                <p className="text-xs" style={{ color: PALETTE.slate }}>
                  {dayDetail.events.length} kegiatan berjalan pada tanggal ini.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setDayDetail(null)}
                aria-label="Tutup daftar kegiatan"
                autoFocus
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded text-[#5B6676] transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="max-h-[60vh] space-y-2 overflow-y-auto pr-1">
              {dayDetail.events.map((event) => {
                const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
                const status = STATUS_STYLES[event.extendedProps.status] || STATUS_STYLES['Belum Mulai'];

                return (
                  <button
                    key={event.id}
                    type="button"
                    onClick={() => {
                      setDayDetail(null);
                      onSelect(event);
                    }}
                    className="w-full rounded-lg border p-3 text-left transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2"
                    style={{ borderColor: PALETTE.paleBlue }}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <span className="text-sm font-semibold" style={{ color: PALETTE.ink }}>
                        {event.title}
                      </span>
                      <span
                        className="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                        style={{ backgroundColor: status.bg, color: status.text, borderColor: status.border }}
                      >
                        {event.extendedProps.status}
                      </span>
                    </div>
                    <p className="mt-1 text-xs" style={{ color: PALETTE.slate }}>
                      {event.extendedProps.team_nama || 'Tanpa tim'} -{' '}
                      {formatDateRange(event.start, getEventInclusiveEnd(event))}
                    </p>
                    <span
                      className="mt-1.5 inline-block rounded-full px-2 py-0.5 text-[10px] font-medium"
                      style={{ backgroundColor: style.soft, color: style.text }}
                    >
                      {event.extendedProps.jenis}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>
        </div>
      )}
    </section>
  );
};

const EmptyState: React.FC<{ hasActiveFilters: boolean; onResetFilters?: () => void }> = ({
  hasActiveFilters,
  onResetFilters,
}) => (
  <div className="rounded-lg border border-dashed border-[#E3EEFA] px-4 py-12 text-center">
    <p className="text-sm font-medium" style={{ color: PALETTE.ink }}>
      {hasActiveFilters ? 'Tidak ada kegiatan yang cocok dengan filter.' : 'Belum ada kegiatan pada tahun ini.'}
    </p>
    <p className="mt-1 text-xs" style={{ color: PALETTE.slate }}>
      {hasActiveFilters
        ? 'Ubah atau reset filter untuk menampilkan peta kepadatan.'
        : 'Peta kepadatan akan muncul setelah ada kegiatan.'}
    </p>
    {hasActiveFilters && onResetFilters && (
      <button
        type="button"
        onClick={onResetFilters}
        className="mt-3 rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition hover:brightness-95"
        style={{ backgroundColor: PALETTE.blue }}
      >
        Reset filter
      </button>
    )}
  </div>
);
