import React from 'react';
import type { Team } from '../types';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  MONTHS_SHORT,
  PALETTE,
  formatDateRange,
  getEventInclusiveEnd,
  getTimelinePosition,
  getTeamStyle,
} from './calendarUtils';

interface TimelineTabProps {
  year: number;
  events: CalendarEvent[];
  teams: Team[];
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
}

export const TimelineTab: React.FC<TimelineTabProps> = ({
  year,
  events,
  teams,
  teamStyles,
  onSelect,
}) => {
  const teamsWithEvents = teams
    .map((team) => ({
      team,
      events: events
        .filter((event) => event.extendedProps.team_id === team.id)
        .sort((a, b) => a.start.localeCompare(b.start)),
    }))
    .filter((group) => group.events.length > 0);

  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
      <div className="mb-5">
        <h2 className="text-sm font-bold sm:text-base" style={{ color: PALETTE.navy }}>
          Timeline {year}
        </h2>
        <p className="mt-0.5 text-xs" style={{ color: PALETTE.slate }}>
          Rentang pelaksanaan kegiatan per tim kerja sepanjang tahun.
        </p>
      </div>

      {teamsWithEvents.length === 0 ? (
        <EmptyState />
      ) : (
        <div className="overflow-x-auto pb-1">
          <div className="min-w-[900px]">
            <div className="grid grid-cols-[250px_1fr] items-end gap-3 border-b border-[#E3EEFA] pb-2">
              <span className="text-[11px] font-semibold" style={{ color: PALETTE.slate }}>
                Tim / Kegiatan
              </span>
              <div className="grid grid-cols-12">
                {MONTHS_SHORT.map((month) => (
                  <span
                    key={month}
                    className="border-l border-[#E3EEFA] pl-2 text-[10px] font-medium"
                    style={{ color: PALETTE.slate }}
                  >
                    {month}
                  </span>
                ))}
              </div>
            </div>

            {teamsWithEvents.map(({ team, events: teamEvents }) => {
              const style = getTeamStyle(teamStyles, team.id);

              return (
                <div key={team.id} className="mb-5 last:mb-0">
                  <span
                    className="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                    style={{ backgroundColor: style.soft, color: style.text, borderColor: style.soft }}
                  >
                    {team.nama}
                  </span>

                  <div className="mt-1.5 divide-y divide-[#F1F5FB]">
                    {teamEvents.map((event) => {
                      const position = getTimelinePosition(event, year);
                      const end = getEventInclusiveEnd(event);

                      return (
                        <div
                          key={event.id}
                          className="grid grid-cols-[250px_1fr] items-center gap-3 py-2"
                        >
                          <button
                            type="button"
                            onClick={() => onSelect(event)}
                            className="truncate text-left text-[11px] font-medium hover:underline focus-visible:outline-2 focus-visible:outline-offset-2"
                            style={{ color: PALETTE.ink }}
                            title={event.title}
                          >
                            {event.title}
                          </button>

                          <div className="relative h-6 overflow-hidden rounded bg-[#F7F9FC]">
                            <div className="absolute inset-0 grid grid-cols-12">
                              {MONTHS_SHORT.map((month) => (
                                <span key={month} className="border-l border-[#E9F0F9]" />
                              ))}
                            </div>

                            <button
                              type="button"
                              onClick={() => onSelect(event)}
                              className="absolute top-1 h-4 min-w-1 rounded-[3px] transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-2"
                              style={{
                                left: `${position.left}%`,
                                width: `${position.width}%`,
                                backgroundColor: style.solid,
                              }}
                              title={`${event.title}: ${formatDateRange(event.start, end)}`}
                              aria-label={`${event.title}, ${formatDateRange(event.start, end)}`}
                            />
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </section>
  );
};

const EmptyState: React.FC = () => (
  <div className="rounded-lg border border-dashed border-[#E3EEFA] px-4 py-12 text-center">
    <p className="text-sm font-medium" style={{ color: PALETTE.ink }}>
      Belum ada kegiatan pada tahun ini.
    </p>
    <p className="mt-1 text-xs" style={{ color: PALETTE.slate }}>
      Tambahkan kegiatan atau ubah filter tim untuk melihat timeline.
    </p>
  </div>
);
