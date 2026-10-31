import React, { useState } from 'react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  MONTHS,
  PALETTE,
  STATUS_STYLES,
  WEEKDAYS,
  eventCoversDate,
  formatDateId,
  formatDateRange,
  getDaysInMonth,
  getEventInclusiveEnd,
  getTeamStyle,
  toIsoDate,
} from './calendarUtils';

const TODAY_ISO = toIsoDate(new Date());

interface MonthlyCalendarTabProps {
  year: number;
  month: number;
  events: CalendarEvent[];
  teamStyles: Map<number, TeamStyle>;
  onPrevMonth: () => void;
  onNextMonth: () => void;
  onSelect: (event: CalendarEvent) => void;
}

export const MonthlyCalendarTab: React.FC<MonthlyCalendarTabProps> = ({
  year,
  month,
  events,
  teamStyles,
  onPrevMonth,
  onNextMonth,
  onSelect,
}) => {
  const [dayDetail, setDayDetail] = useState<{ date: string; events: CalendarEvent[] } | null>(null);

  const firstDayOffset = (new Date(year, month, 1).getDay() + 6) % 7;
  const totalDays = getDaysInMonth(year, month);
  const totalCells = Math.ceil((firstDayOffset + totalDays) / 7) * 7;
  const today = TODAY_ISO;

  const eventsForDay = (date: string) =>
    events
      .filter((event) => eventCoversDate(event, date))
      .sort((a, b) => a.title.localeCompare(b.title));

  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <button
          type="button"
          onClick={onPrevMonth}
          aria-label="Bulan sebelumnya"
          className="flex h-9 w-9 items-center justify-center rounded border border-[#E3EEFA] text-[#5B6676] transition hover:bg-[#E3EEFA] hover:text-[#12305C] focus-visible:outline-2 focus-visible:outline-offset-2"
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
          className="flex h-9 w-9 items-center justify-center rounded border border-[#E3EEFA] text-[#5B6676] transition hover:bg-[#E3EEFA] hover:text-[#12305C] focus-visible:outline-2 focus-visible:outline-offset-2"
        >
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>

      <div className="overflow-x-auto pb-1">
        <div className="min-w-[860px]">
          <div className="grid grid-cols-7 border-b border-[#E3EEFA] pb-2">
            {WEEKDAYS.map((day) => (
              <span
                key={day}
                className="px-2 text-center text-[11px] font-semibold"
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
                    className="min-h-[112px] border-b border-r border-[#E3EEFA] bg-[#FBFCFE]"
                    aria-hidden="true"
                  />
                );
              }

              const date = toIsoDate(new Date(year, month, dayNumber));
              const dayEvents = eventsForDay(date);
              const visibleEvents = dayEvents.slice(0, 3);
              const isToday = date === today;

              return (
                <div
                  key={date}
                  className={`min-h-[112px] border-b border-r border-[#E3EEFA] p-1.5 ${
                    isToday ? 'ring-1 ring-inset ring-[#3B82C4]' : ''
                  }`}
                >
                  <div className="mb-1 flex items-center justify-between px-0.5">
                    <span
                      className="text-[11px] font-semibold"
                      style={{ color: isToday ? PALETTE.blue : PALETTE.slate }}
                    >
                      {dayNumber}
                    </span>
                    {isToday && (
                      <span className="text-[9px] font-medium" style={{ color: PALETTE.blue }}>
                        Hari ini
                      </span>
                    )}
                  </div>

                  <div className="space-y-1">
                    {visibleEvents.map((event) => {
                      const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
                      return (
                        <button
                          key={event.id}
                          type="button"
                          onClick={() => onSelect(event)}
                          className="block w-full truncate rounded-sm border-l-[3px] px-1.5 py-0.5 text-left text-[9px] font-medium leading-4 transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-1"
                          style={{
                            backgroundColor: style.soft,
                            color: style.text,
                            borderLeftColor: style.solid,
                          }}
                          title={`${event.title} (${event.extendedProps.team_nama || 'Tanpa tim'})`}
                        >
                          {event.title}
                        </button>
                      );
                    })}

                    {dayEvents.length > 3 && (
                      <button
                        type="button"
                        onClick={() => setDayDetail({ date, events: dayEvents })}
                        className="px-1 text-[9px] font-medium hover:underline"
                        style={{ color: PALETTE.blue }}
                      >
                        +{dayEvents.length - 3} lainnya
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {dayDetail && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-[#12305C]/50 p-4"
          role="dialog"
          aria-modal="true"
          aria-label={`Kegiatan ${formatDateId(dayDetail.date)}`}
        >
          <div className="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
            <div className="mb-4 flex items-start justify-between gap-3">
              <div>
                <h3 className="text-base font-bold" style={{ color: PALETTE.navy }}>
                  Kegiatan {formatDateId(dayDetail.date)}
                </h3>
                <p className="text-xs" style={{ color: PALETTE.slate }}>
                  {dayDetail.events.length} kegiatan berjalan pada tanggal ini.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setDayDetail(null)}
                aria-label="Tutup"
                className="flex h-9 w-9 items-center justify-center rounded text-[#5B6676] hover:bg-[#F7F9FC]"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="max-h-[60vh] space-y-2 overflow-y-auto">
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
                    className="w-full rounded-lg border p-3 text-left transition hover:bg-[#F7F9FC]"
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
