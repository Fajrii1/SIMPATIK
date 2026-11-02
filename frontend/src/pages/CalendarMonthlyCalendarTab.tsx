import React, { useEffect, useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import type { Team } from '../types';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  MONTHS,
  PALETTE,
  STATUS_STYLES,
  WEEKDAYS,
  buildIsoDate,
  eventCoversDate,
  formatDateId,
  formatDateRange,
  getDaysInMonth,
  getEventInclusiveEnd,
  getTeamStyle,
  getWeekdayOffset,
  toIsoDate,
} from './calendarUtils';

interface MonthlyCalendarTabProps {
  year: number;
  month: number;
  events: CalendarEvent[];
  teams: Team[];
  teamStyles: Map<number, TeamStyle>;
  onPrevMonth: () => void;
  onNextMonth: () => void;
  onSelect: (event: CalendarEvent) => void;
}

interface DayDetail {
  date: string;
  events: CalendarEvent[];
}

const TODAY_ISO = toIsoDate(new Date());

const EVENT_TOOLTIP = (event: CalendarEvent) => {
  const team = event.extendedProps.team_nama || 'Tanpa tim';
  const status = event.extendedProps.status || 'Tanpa status';
  const range = formatDateRange(event.start, getEventInclusiveEnd(event));
  return `${event.title}\n${team} • ${status}\n${range}`;
};

export const MonthlyCalendarTab: React.FC<MonthlyCalendarTabProps> = ({
  year,
  month,
  events,
  teams,
  teamStyles,
  onPrevMonth,
  onNextMonth,
  onSelect,
}) => {
  const [dayDetail, setDayDetail] = useState<DayDetail | null>(null);

  const firstDayOffset = getWeekdayOffset(year, month);
  const totalDays = getDaysInMonth(year, month);
  const totalCells = Math.ceil((firstDayOffset + totalDays) / 7) * 7;

  const eventsByDay = useMemo(() => {
    const map = new Map<string, CalendarEvent[]>();

    for (let day = 1; day <= totalDays; day += 1) {
      const date = buildIsoDate(year, month, day);
      const dayEvents = events
        .filter((event) => eventCoversDate(event, date))
        .sort(
          (a, b) =>
            a.start.localeCompare(b.start) ||
            a.title.localeCompare(b.title)
        );

      if (dayEvents.length > 0) map.set(date, dayEvents);
    }

    return map;
  }, [events, year, month, totalDays]);

  const legendTeams = useMemo(
    () =>
      teams.filter((team) =>
        events.some((event) => event.extendedProps.team_id === team.id)
      ),
    [teams, events]
  );

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

  const openDayDetail = (date: string, dayEvents: CalendarEvent[]) => {
    setDayDetail({ date, events: dayEvents });
  };

  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-3 shadow-sm sm:p-5">
      <div className="mb-3 flex flex-col gap-3 sm:mb-4">
        <div className="flex items-center justify-between gap-3">
          <button
            type="button"
            onClick={onPrevMonth}
            aria-label="Bulan sebelumnya"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded border border-[#E3EEFA] text-[#5B6676] transition hover:bg-[#E3EEFA] hover:text-[#12305C] focus-visible:outline-2 focus-visible:outline-offset-2"
          >
            <ChevronLeft className="h-4 w-4" />
          </button>

          <h2 className="text-sm font-bold sm:text-base" style={{ color: PALETTE.navy }}>
            {MONTHS[month]} {year}
          </h2>

          <button
            type="button"
            onClick={onNextMonth}
            aria-label="Bulan berikutnya"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded border border-[#E3EEFA] text-[#5B6676] transition hover:bg-[#E3EEFA] hover:text-[#12305C] focus-visible:outline-2 focus-visible:outline-offset-2"
          >
            <ChevronRight className="h-4 w-4" />
          </button>
        </div>

        {legendTeams.length > 0 && (
          <div
            className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[10px] sm:text-[11px]"
            style={{ color: PALETTE.slate }}
            aria-label="Legenda warna tim"
          >
            <span className="font-semibold" style={{ color: PALETTE.ink }}>
              Tim:
            </span>
            {legendTeams.map((team) => {
              const style = getTeamStyle(teamStyles, team.id);
              return (
                <span key={team.id} className="inline-flex items-center gap-1">
                  <span
                    className="inline-block h-2 w-2 rounded-full"
                    style={{ backgroundColor: style.solid }}
                    aria-hidden="true"
                  />
                  <span className="max-w-[120px] truncate" title={team.nama}>
                    {team.nama}
                  </span>
                </span>
              );
            })}
            <span className="inline-flex items-center gap-1">
              <span
                className="inline-block h-2 w-2 rounded-full ring-2 ring-offset-1"
                style={{ boxShadow: `0 0 0 2px ${PALETTE.sky}` }}
                aria-hidden="true"
              />
              Hari ini
            </span>
          </div>
        )}
      </div>

      <div className="grid grid-cols-7 border-b border-[#E3EEFA] pb-1.5">
        {WEEKDAYS.map((day) => (
          <span
            key={day}
            className="px-0.5 text-center text-[10px] font-semibold sm:px-2 sm:text-[11px]"
            style={{ color: PALETTE.slate }}
          >
            {day}
          </span>
        ))}
      </div>

      <div className="grid grid-cols-7">
        {Array.from({ length: totalCells }, (_, index) => {
          const dayNumber = index - firstDayOffset + 1;
          const valid = dayNumber >= 1 && dayNumber <= totalDays;

          if (!valid) {
            return (
              <div
                key={`blank-${index}`}
                className="min-h-[64px] border-b border-r border-[#E3EEFA] bg-[#FBFCFE] last:border-r-0 sm:min-h-[96px] lg:min-h-[112px]"
                aria-hidden="true"
              />
            );
          }

          const date = buildIsoDate(year, month, dayNumber);
          const dayEvents = eventsByDay.get(date) || [];
          const visibleEvents = dayEvents.slice(0, 3);
          const isToday = date === TODAY_ISO;
          const desktopExtra = Math.max(dayEvents.length - 3, 0);

          return (
            <div
              key={date}
              className={`min-h-[64px] border-b border-r border-[#E3EEFA] p-1 sm:min-h-[96px] sm:p-1.5 lg:min-h-[112px] ${
                isToday ? 'bg-[#F7FBFF] ring-1 ring-inset ring-[#3B82C4]' : ''
              }`}
              aria-label={`${dayNumber} ${MONTHS[month]} ${year}, ${dayEvents.length} kegiatan`}
            >
              <div className="mb-1 flex items-center justify-between px-0.5">
                <span
                  className={`flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold sm:text-[11px] ${
                    isToday ? 'text-white' : ''
                  }`}
                  style={{
                    backgroundColor: isToday ? PALETTE.sky : 'transparent',
                    color: isToday ? PALETTE.white : PALETTE.slate,
                  }}
                  title={isToday ? 'Hari ini' : undefined}
                >
                  {dayNumber}
                </span>
                {isToday && (
                  <span
                    className="hidden text-[9px] font-medium sm:inline"
                    style={{ color: PALETTE.blue }}
                  >
                    Hari ini
                  </span>
                )}
              </div>

              <div className="space-y-0.5 sm:space-y-1">
                {dayEvents.length > 0 && (
                  <button
                    type="button"
                    onClick={() =>
                      dayEvents.length === 1
                        ? onSelect(dayEvents[0])
                        : openDayDetail(date, dayEvents)
                    }
                    className="flex w-full items-center justify-center gap-0.5 rounded px-0.5 py-0.5 transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-1 sm:hidden"
                    aria-label={`${dayEvents.length} kegiatan pada ${dayNumber} ${MONTHS[month]} ${year}, ${dayEvents.length === 1 ? 'buka detail kegiatan' : 'buka daftar kegiatan'}`}
                  >
                    {Array.from(
                      new Set(dayEvents.map((event) => event.extendedProps.team_id))
                    )
                      .slice(0, 3)
                      .map((teamId) => (
                        <span
                          key={`dot-${teamId}`}
                          className="h-1.5 w-1.5 shrink-0 rounded-full"
                          style={{ backgroundColor: getTeamStyle(teamStyles, teamId).solid }}
                        />
                      ))}
                    {dayEvents.length > 1 && (
                      <span
                        className="text-[9px] font-semibold leading-none"
                        style={{ color: PALETTE.blue }}
                      >
                        {dayEvents.length}
                      </span>
                    )}
                  </button>
                )}

                {visibleEvents.map((event) => {
                  const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
                  return (
                    <button
                      key={event.id}
                      type="button"
                      onClick={() => onSelect(event)}
                      className="hidden w-full truncate rounded-sm border-l-[3px] px-1 py-0.5 text-left text-[9px] font-medium leading-4 transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-1 sm:block sm:px-1.5 sm:text-[10px]"
                      style={{
                        backgroundColor: style.soft,
                        color: style.text,
                        borderLeftColor: style.solid,
                      }}
                      title={EVENT_TOOLTIP(event)}
                      aria-label={`${event.title}, ${event.extendedProps.team_nama || 'Tanpa tim'}`}
                    >
                      {event.title}
                    </button>
                  );
                })}

                {desktopExtra > 0 && (
                  <button
                    type="button"
                    onClick={() => openDayDetail(date, dayEvents)}
                    className="hidden px-1 text-left text-[9px] font-semibold hover:underline sm:block"
                    style={{ color: PALETTE.blue }}
                  >
                    +{desktopExtra} kegiatan
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {dayDetail && (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-[#12305C]/50 p-3 sm:p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="day-detail-title"
          onClick={() => setDayDetail(null)}
        >
          <div
            className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-4 shadow-xl sm:p-5"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="mb-4 flex items-start justify-between gap-3">
              <div>
                <h3
                  id="day-detail-title"
                  className="text-base font-bold"
                  style={{ color: PALETTE.navy }}
                >
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
