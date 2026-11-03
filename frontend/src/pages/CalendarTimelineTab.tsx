import React, { useMemo } from 'react';
import { CalendarRange } from 'lucide-react';
import type { Team } from '../types';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  PALETTE,
  buildMonthSegments,
  formatDateRange,
  getDayOfYearPercent,
  getEventInclusiveEnd,
  getTeamStyle,
  getTimelinePosition,
  toIsoDate,
} from './calendarUtils';

interface TimelineTabProps {
  year: number;
  events: CalendarEvent[];
  teams: Team[];
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
  onResetFilters?: () => void;
  hasActiveFilters?: boolean;
}

const TODAY_ISO = toIsoDate(new Date());
const LABEL_WIDTH = 168;

export const TimelineTab: React.FC<TimelineTabProps> = ({
  year,
  events,
  teams,
  teamStyles,
  onSelect,
  onResetFilters,
  hasActiveFilters = false,
}) => {
  const segments = useMemo(() => buildMonthSegments(year), [year]);
  const isCurrentYear = Number(TODAY_ISO.slice(0, 4)) === year;
  const todayPercent = useMemo(() => getDayOfYearPercent(TODAY_ISO, year), [year]);

  const groups = useMemo(() => {
    const teamOrder = new Map(teams.map((team, index) => [team.id, index]));

    return [...teams]
      .sort((a, b) => (teamOrder.get(a.id) ?? 0) - (teamOrder.get(b.id) ?? 0))
      .map((team) => ({
        team,
        events: events
          .filter((event) => event.extendedProps.team_id === team.id)
          .sort((a, b) => a.start.localeCompare(b.start) || a.title.localeCompare(b.title)),
      }))
      .filter((group) => group.events.length > 0);
  }, [teams, events]);

  const unknownTeamEvents = useMemo(
    () =>
      events
        .filter((event) => !teams.some((team) => team.id === event.extendedProps.team_id))
        .sort((a, b) => a.start.localeCompare(b.start) || a.title.localeCompare(b.title)),
    [events, teams]
  );

  const totalGroups = groups.length + (unknownTeamEvents.length > 0 ? 1 : 0);

  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
      <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-sm font-bold sm:text-base" style={{ color: PALETTE.navy }}>
            Timeline {year}
          </h2>
          <p className="mt-0.5 text-xs" style={{ color: PALETTE.slate }}>
            Posisi dan panjang batang mengikuti jumlah hari sebenarnya. Geser area timeline bila layar sempit.
          </p>
        </div>
        <span
          className="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-medium"
          style={{ backgroundColor: PALETTE.paleBlue, color: PALETTE.navy }}
        >
          <CalendarRange className="h-3 w-3" />
          {events.length} kegiatan | {totalGroups} tim
        </span>
      </div>

      {totalGroups === 0 ? (
        <EmptyState hasActiveFilters={hasActiveFilters} onResetFilters={onResetFilters} />
      ) : (
        <div className="overflow-x-auto pb-1">
          <div className="min-w-[720px]">
            <div
              className="grid items-end gap-x-3 border-b border-[#E3EEFA] pb-1.5"
              style={{ gridTemplateColumns: `${LABEL_WIDTH}px 1fr` }}
            >
              <span className="text-[11px] font-semibold" style={{ color: PALETTE.slate }}>
                Tim / Kegiatan
              </span>
              <div className="relative flex h-5">
                {segments.map((segment) => (
                  <span
                    key={segment.index}
                    className="flex items-center border-l border-[#E3EEFA] pl-1 text-[9px] font-semibold sm:text-[10px]"
                    style={{ width: `${segment.widthPct}%`, color: PALETTE.slate }}
                    title={`${segment.label}: ${segment.days} hari`}
                  >
                    {segment.label}
                  </span>
                ))}

                {isCurrentYear && (
                  <span
                    className="absolute -top-0.5 bottom-0 z-10 w-0.5 -translate-x-1/2 rounded-full"
                    style={{ left: `${todayPercent}%`, backgroundColor: PALETTE.coral }}
                    title={`Hari ini: ${formatDateRange(TODAY_ISO, TODAY_ISO)}`}
                  />
                )}
              </div>
            </div>

            {groups.map(({ team, events: teamEvents }) => (
              <TimelineGroup
                key={team.id}
                teamName={team.nama}
                teamId={team.id}
                teamEvents={teamEvents}
                year={year}
                segments={segments}
                teamStyles={teamStyles}
                onSelect={onSelect}
                isCurrentYear={isCurrentYear}
                todayPercent={todayPercent}
              />
            ))}

            {unknownTeamEvents.length > 0 && (
              <TimelineGroup
                teamName="Tanpa tim"
                teamId={0}
                teamEvents={unknownTeamEvents}
                year={year}
                segments={segments}
                teamStyles={teamStyles}
                onSelect={onSelect}
                isCurrentYear={isCurrentYear}
                todayPercent={todayPercent}
              />
            )}
          </div>
        </div>
      )}
    </section>
  );
};

interface TimelineGroupProps {
  teamName: string;
  teamId: number;
  teamEvents: CalendarEvent[];
  year: number;
  segments: { index: number; label: string; widthPct: number }[];
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
  isCurrentYear: boolean;
  todayPercent: number;
}

const TimelineGroup: React.FC<TimelineGroupProps> = ({
  teamName,
  teamId,
  teamEvents,
  year,
  segments,
  teamStyles,
  onSelect,
  isCurrentYear,
  todayPercent,
}) => {
  const style = getTeamStyle(teamStyles, teamId);

  return (
    <div className="mt-3">
      <div className="mb-1 flex items-center gap-2">
        <span
          className="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-semibold"
          style={{ backgroundColor: style.soft, color: style.text, borderColor: style.soft }}
        >
          {teamName}
        </span>
        <span className="text-[10px]" style={{ color: PALETTE.slate }}>
          {teamEvents.length} kegiatan
        </span>
      </div>

      <div className="divide-y divide-[#F1F5FB]">
        {teamEvents.map((event) => {
          const position = getTimelinePosition(event, year);
          const period = formatDateRange(event.start, getEventInclusiveEnd(event));
          const tooltip = `${event.title}\n${teamName} | ${event.extendedProps.status}\n${period}`;

          return (
            <div
              key={event.id}
              className="grid items-center gap-x-3 py-1.5"
              style={{ gridTemplateColumns: `${LABEL_WIDTH}px 1fr` }}
            >
              <button
                type="button"
                onClick={() => onSelect(event)}
                className="truncate text-left text-[11px] font-medium transition hover:underline focus-visible:outline-2 focus-visible:outline-offset-2"
                style={{ color: PALETTE.ink }}
                title={`${event.title} (${period})`}
              >
                {event.title}
              </button>

              <div className="relative h-6">
                <div className="absolute inset-0 overflow-hidden rounded bg-[#F7F9FC]">
                  {segments.map((segment) => (
                    <span
                      key={segment.index}
                      className="absolute top-0 bottom-0 border-l border-[#E9F0F9]"
                      style={{ left: `${segments.slice(0, segment.index).reduce((sum, item) => sum + item.widthPct, 0)}%` }}
                    />
                  ))}
                </div>

                <button
                  type="button"
                  onClick={() => onSelect(event)}
                  className="absolute top-1/2 h-3.5 -translate-y-1/2 rounded-[3px] shadow-sm transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2"
                  style={{
                    left: `${position.left}%`,
                    width: `max(${position.width}%, 3px)`,
                    backgroundColor: style.solid,
                    minWidth: 3,
                  }}
                  title={tooltip}
                  aria-label={`${event.title}, ${period}, ${event.extendedProps.status}`}
                />

                {isCurrentYear && (
                  <span
                    className="pointer-events-none absolute top-0 bottom-0 z-10 w-px -translate-x-1/2"
                    style={{ left: `${todayPercent}%`, backgroundColor: PALETTE.coral, opacity: 0.75 }}
                    aria-hidden="true"
                  />
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
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
        ? 'Ubah atau reset filter untuk menampilkan kegiatan lain.'
        : 'Tambahkan kegiatan atau ubah tahun untuk melihat timeline.'}
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
