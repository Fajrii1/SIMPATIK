import React from 'react';
import { AlertTriangle } from 'lucide-react';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import type { Team } from '../types';
import type { CalendarAnalytics, CalendarEvent, TeamStyle } from './calendarTypes';
import { PALETTE, getTeamStyle, teamKey } from './calendarUtils';

interface SummaryTabProps {
  year: number;
  analytics: CalendarAnalytics;
  teams: Team[];
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
  eventsById: Map<string, CalendarEvent>;
}

export const SummaryTab: React.FC<SummaryTabProps> = ({
  year,
  analytics,
  teams,
  teamStyles,
  onSelect,
  eventsById,
}) => {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total kegiatan" value={String(analytics.total)} />
        <MetricCard label="Bulan aktif" value={`${analytics.activeMonths} bulan`} />
        <MetricCard
          label="Bulan terpadat"
          value={
            analytics.densestMonth
              ? `${analytics.densestMonth.label} (${analytics.densestMonth.total} keg.)`
              : '-'
          }
        />
        <MetricCard label="Hari dengan overlap" value={String(analytics.overlapDayCount)} />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm xl:col-span-2">
          <h2 className="mb-4 text-sm font-bold" style={{ color: PALETTE.navy }}>
            Kegiatan per bulan {year}
          </h2>

          <div className="h-[300px]">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={analytics.monthlyCounts} margin={{ top: 4, right: 8, left: -18, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={PALETTE.paleBlue} vertical={false} />
                <XAxis
                  dataKey="month"
                  tick={{ fontSize: 10, fill: PALETTE.slate }}
                  axisLine={{ stroke: PALETTE.paleBlue }}
                  tickLine={false}
                />
                <YAxis
                  allowDecimals={false}
                  tick={{ fontSize: 10, fill: PALETTE.slate }}
                  axisLine={false}
                  tickLine={false}
                />
                <Tooltip
                  contentStyle={{
                    fontSize: 12,
                    borderRadius: 8,
                    border: `1px solid ${PALETTE.paleBlue}`,
                  }}
                />
                <Legend wrapperStyle={{ fontSize: 11 }} />
                {teams.map((team) => {
                  const style = getTeamStyle(teamStyles, team.id);
                  return (
                    <Bar
                      key={team.id}
                      dataKey={teamKey(team.id)}
                      name={team.nama}
                      stackId="a"
                      fill={style.solid}
                      radius={[2, 2, 0, 0]}
                    />
                  );
                })}
              </BarChart>
            </ResponsiveContainer>
          </div>
        </section>

        <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm">
          <h2 className="mb-3 text-sm font-bold" style={{ color: PALETTE.navy }}>
            5 bulan terpadat
          </h2>

          {analytics.topMonths.length === 0 ? (
            <p className="py-8 text-center text-xs" style={{ color: PALETTE.slate }}>
              Belum ada kegiatan pada tahun ini.
            </p>
          ) : (
            <div className="space-y-2">
              {analytics.topMonths.map((month) => (
                <div
                  key={month.index}
                  className="flex flex-col gap-1.5 rounded-lg border border-[#E3EEFA] px-3 py-2"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-semibold" style={{ color: PALETTE.ink }}>
                      {month.label}
                    </span>
                    <span className="text-[10px] font-medium" style={{ color: PALETTE.slate }}>
                      {month.total} kegiatan
                    </span>
                  </div>
                  <div className="flex flex-wrap gap-1">
                    {month.teams.map((team) => {
                      const style = getTeamStyle(teamStyles, team.id);
                      return (
                        <span
                          key={team.id}
                          className="rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                          style={{ backgroundColor: style.soft, color: style.text }}
                        >
                          {team.nama}: {team.jumlah}
                        </span>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm">
        <h2 className="mb-3 text-sm font-bold" style={{ color: PALETTE.navy }}>
          Beban per Fungsi
        </h2>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[700px] text-left text-xs">
            <thead>
              <tr className="border-b border-[#E3EEFA] text-[11px]" style={{ color: PALETTE.slate }}>
                <th className="py-2.5 pr-3 font-semibold">Fungsi</th>
                <th className="py-2.5 pr-3 font-semibold">Kegiatan</th>
                <th className="py-2.5 pr-3 font-semibold">Hari aktif</th>
                <th className="py-2.5 font-semibold">Nama Kegiatan</th>
              </tr>
            </thead>
            <tbody>
              {analytics.teamSummary.map((team) => {
                const style = getTeamStyle(teamStyles, team.id);
                return (
                  <tr key={team.id} className="border-b border-[#F1F5FB] last:border-0">
                    <td className="py-3 pr-3">
                      <span
                        className="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-semibold"
                        style={{ backgroundColor: style.soft, color: style.text, borderColor: style.soft }}
                      >
                        {team.nama}
                      </span>
                    </td>
                    <td className="py-3 pr-3 font-semibold" style={{ color: PALETTE.ink }}>
                      {team.jumlah}
                    </td>
                    <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                      {team.activeDays} hari
                    </td>
                    <td className="py-3" style={{ color: PALETTE.ink }}>
                      <ol className="list-inside list-decimal space-y-0.5">
                        {team.activities.map((activity) => (
                          <li key={activity} className="truncate" title={activity}>
                            {activity}
                          </li>
                        ))}
                      </ol>
                    </td>
                  </tr>
                );
              })}

              {analytics.teamSummary.length === 0 && (
                <tr>
                  <td colSpan={4} className="py-8 text-center" style={{ color: PALETTE.slate }}>
                    Belum ada data kegiatan.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm">
        <div className="mb-3 flex items-center gap-2">
          <AlertTriangle className="h-4 w-4" style={{ color: PALETTE.coral }} />
          <h2 className="text-sm font-bold" style={{ color: PALETTE.navy }}>
            Kelompok kegiatan overlap per bulan
            {analytics.overlaps.length > 0 ? ` (${analytics.overlaps.length} bulan)` : ''}
          </h2>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-left text-xs">
            <thead>
              <tr className="border-b border-[#E3EEFA] text-[11px]" style={{ color: PALETTE.slate }}>
                <th className="w-[140px] py-2.5 pr-3 font-semibold">Bulan</th>
                <th className="py-2.5 font-semibold">Kegiatan yang overlap serentak</th>
              </tr>
            </thead>
            <tbody>
              {analytics.overlaps.map((month) => (
                <tr key={month.index} className="border-b border-[#F1F5FB] last:border-0">
                  <td className="py-3 pr-3 align-top">
                    <span className="font-semibold" style={{ color: PALETTE.ink }}>
                      {month.label}
                    </span>
                    <span className="ml-1 text-[10px]" style={{ color: PALETTE.slate }}>
                      ({month.days} hari)
                    </span>
                  </td>
                  <td className="py-3">
                    <div className="flex flex-wrap gap-1">
                      {month.activities.slice(0, 5).map((activity) => {
                        const event = eventsById.get(activity.id);
                        const style = getTeamStyle(teamStyles, event?.extendedProps.team_id);
                        return (
                          <button
                            key={activity.id}
                            type="button"
                            onClick={() => event && onSelect(event)}
                            className="max-w-[280px] truncate rounded-full border px-2 py-0.5 text-[10px] font-medium transition hover:brightness-95"
                            style={{ backgroundColor: style.soft, color: style.text, borderColor: style.soft }}
                            title={activity.title}
                          >
                            {activity.title}
                          </button>
                        );
                      })}
                      {month.activities.length > 5 && (
                        <span
                          className="rounded-full bg-[#F7F9FC] px-2 py-0.5 text-[10px] font-medium"
                          style={{ color: PALETTE.slate }}
                          title={month.activities.slice(5).map((activity) => activity.title).join(', ')}
                        >
                          +{month.activities.length - 5} lainnya
                        </span>
                      )}
                    </div>
                  </td>
                </tr>
              ))}

              {analytics.overlaps.length === 0 && (
                <tr>
                  <td colSpan={2} className="py-8 text-center" style={{ color: PALETTE.slate }}>
                    Tidak ada kegiatan yang berjalan bersamaan pada tahun ini.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
};

const MetricCard: React.FC<{ label: string; value: string }> = ({ label, value }) => (
  <div className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm">
    <p className="text-[11px] font-medium" style={{ color: PALETTE.slate }}>
      {label}
    </p>
    <p className="mt-1.5 text-xl font-bold leading-tight" style={{ color: PALETTE.navy }}>
      {value}
    </p>
  </div>
);
