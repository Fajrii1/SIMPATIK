import React from 'react';
import { AlertTriangle, CalendarClock, CalendarDays, CheckCircle2, Clock, Users } from 'lucide-react';
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
import { PALETTE, formatDateId, getTeamStyle, teamKey } from './calendarUtils';

interface SummaryTabProps {
  year: number;
  analytics: CalendarAnalytics;
  teams: Team[];
  teamStyles: Map<number, TeamStyle>;
  onSelect: (event: CalendarEvent) => void;
  eventsById: Map<string, CalendarEvent>;
  onResetFilters?: () => void;
  hasActiveFilters?: boolean;
}

export const SummaryTab: React.FC<SummaryTabProps> = ({
  year,
  analytics,
  teams,
  teamStyles,
  onSelect,
  eventsById,
  onResetFilters,
  hasActiveFilters = false,
}) => {
  const { statusCounts } = analytics;

  if (analytics.total === 0) {
    return (
      <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
        <div className="rounded-lg border border-dashed border-[#E3EEFA] px-4 py-12 text-center">
          <p className="text-sm font-medium" style={{ color: PALETTE.ink }}>
            {hasActiveFilters
              ? 'Tidak ada kegiatan yang cocok dengan filter.'
              : 'Belum ada kegiatan pada tahun ini.'}
          </p>
          <p className="mt-1 text-xs" style={{ color: PALETTE.slate }}>
            {hasActiveFilters
              ? 'Ubah atau reset filter untuk melihat ringkasan kegiatan lain.'
              : 'Ringkasan akan muncul setelah ada kegiatan yang tercatat.'}
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
      </section>
    );
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <MetricCard label="Total kegiatan" value={String(analytics.total)} icon={CalendarDays} />
        <MetricCard
          label="Aktif (berjalan)"
          value={String(statusCounts.berjalan)}
          icon={Clock}
          tone="blue"
        />
        <MetricCard
          label="Selesai"
          value={String(statusCounts.selesai)}
          icon={CheckCircle2}
          tone="green"
        />
        <MetricCard
          label="Belum mulai"
          value={String(statusCounts.belumMulai)}
          icon={CalendarClock}
          tone="slate"
        />
        <MetricCard
          label="Terlambat"
          value={String(statusCounts.terlambat)}
          icon={AlertTriangle}
          tone="red"
        />
        <MetricCard
          label="Total hari kegiatan"
          value={`${analytics.activeDays} hari`}
          icon={CalendarDays}
          hint={`${analytics.activeMonths} bulan aktif`}
        />
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
        <div className="mb-1 flex items-center gap-2">
          <AlertTriangle className="h-4 w-4" style={{ color: PALETTE.coral }} />
          <h2 className="text-sm font-bold" style={{ color: PALETTE.navy }}>
            Overlap kegiatan per bulan
            {analytics.overlaps.length > 0 ? ` (${analytics.overlaps.length} bulan)` : ''}
          </h2>
        </div>
        <p className="mb-3 text-xs" style={{ color: PALETTE.slate }}>
          Beberapa kegiatan berlangsung pada tanggal yang sama. Tidak otomatis berarti konflik beban
          pegawai bila pegawainya berbeda ({analytics.overlapDayCount} hari overlap).
        </p>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-left text-xs">
            <thead>
              <tr className="border-b border-[#E3EEFA] text-[11px]" style={{ color: PALETTE.slate }}>
                <th className="w-[140px] py-2.5 pr-3 font-semibold">Bulan / Tanggal</th>
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
                    <p className="mt-1 text-[10px] leading-4" style={{ color: PALETTE.slate }}>
                      {month.dates.slice(0, 6).map(formatDateId).join(', ')}
                      {month.dates.length > 6 ? ` +${month.dates.length - 6} hari` : ''}
                    </p>
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
                            className="max-w-[280px] truncate rounded-full border px-2 py-0.5 text-[10px] font-medium transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2"
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

      <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm">
        <div className="mb-1 flex items-center gap-2">
          <Users className="h-4 w-4" style={{ color: PALETTE.navy }} />
          <h2 className="text-sm font-bold" style={{ color: PALETTE.navy }}>
            Konflik penugasan pegawai
            {analytics.employeeConflicts.length > 0
              ? ` (${analytics.employeeConflicts.length} pegawai)`
              : ''}
          </h2>
        </div>
        <p className="mb-3 text-xs" style={{ color: PALETTE.slate }}>
          Seorang pegawai memegang dua penugasan atau lebih pada tanggal yang beririsan. Dihitung
          dari tanggal efektif penugasan, bukan periode kegiatan.
        </p>

        {analytics.employeeConflicts.length === 0 ? (
          <p className="rounded-lg border border-dashed border-[#E3EEFA] py-8 text-center text-xs" style={{ color: PALETTE.slate }}>
            Tidak ada konflik penugasan pegawai pada tahun ini.
          </p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[680px] text-left text-xs">
              <thead>
                <tr className="border-b border-[#E3EEFA] text-[11px]" style={{ color: PALETTE.slate }}>
                  <th className="py-2.5 pr-3 font-semibold">Pegawai</th>
                  <th className="py-2.5 pr-3 font-semibold">Tanggal bentrok</th>
                  <th className="py-2.5 pr-3 font-semibold">Maks. bersamaan</th>
                  <th className="py-2.5 font-semibold">Kegiatan terkait</th>
                </tr>
              </thead>
              <tbody>
                {analytics.employeeConflicts.map((conflict) => (
                  <tr key={conflict.userId} className="border-b border-[#F1F5FB] last:border-0">
                    <td className="py-3 pr-3 font-semibold" style={{ color: PALETTE.ink }}>
                      {conflict.nama}
                    </td>
                    <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                      <span className="font-semibold">{conflict.dates.length} hari</span>
                      <p className="text-[10px] leading-4" style={{ color: PALETTE.slate }}>
                        {conflict.dates.slice(0, 4).map(formatDateId).join(', ')}
                        {conflict.dates.length > 4 ? ` +${conflict.dates.length - 4}` : ''}
                      </p>
                    </td>
                    <td className="py-3 pr-3" style={{ color: PALETTE.ink }}>
                      {conflict.maxConcurrent} penugasan
                    </td>
                    <td className="py-3">
                      <div className="flex flex-wrap gap-1">
                        {conflict.activities.slice(0, 4).map((activity) => {
                          const event = eventsById.get(activity.id);
                          return (
                            <button
                              key={activity.id}
                              type="button"
                              onClick={() => event && onSelect(event)}
                              className="max-w-[240px] truncate rounded-full bg-[#F7F9FC] px-2 py-0.5 text-[10px] font-medium transition hover:bg-[#EAF2FB] focus-visible:outline-2 focus-visible:outline-offset-2"
                              style={{ color: PALETTE.ink }}
                              title={activity.title}
                            >
                              {activity.title}
                            </button>
                          );
                        })}
                        {conflict.activities.length > 4 && (
                          <span className="text-[10px]" style={{ color: PALETTE.slate }}>
                            +{conflict.activities.length - 4} lainnya
                          </span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
};

type Tone = 'navy' | 'blue' | 'green' | 'slate' | 'red';

const TONES: Record<Tone, { bg: string; text: string }> = {
  navy: { bg: PALETTE.paleBlue, text: PALETTE.navy },
  blue: { bg: PALETTE.paleBlue, text: PALETTE.blue },
  green: { bg: PALETTE.mint, text: PALETTE.teal },
  slate: { bg: PALETTE.bg, text: PALETTE.slate },
  red: { bg: PALETTE.blush, text: PALETTE.coral },
};

const MetricCard: React.FC<{
  label: string;
  value: string;
  hint?: string;
  icon?: React.ComponentType<{ className?: string; style?: React.CSSProperties }>;
  tone?: Tone;
}> = ({ label, value, hint, icon: Icon, tone = 'navy' }) => {
  const palette = TONES[tone];

  return (
    <div className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm">
      <div className="flex items-center justify-between gap-2">
        <p className="text-[11px] font-medium" style={{ color: PALETTE.slate }}>
          {label}
        </p>
        {Icon && (
          <span className="flex h-7 w-7 items-center justify-center rounded-lg" style={{ backgroundColor: palette.bg }}>
            <Icon className="h-3.5 w-3.5" style={{ color: palette.text }} />
          </span>
        )}
      </div>
      <p className="mt-1.5 text-xl font-bold leading-tight" style={{ color: PALETTE.navy }}>
        {value}
      </p>
      {hint && (
        <p className="mt-0.5 text-[10px]" style={{ color: PALETTE.slate }}>
          {hint}
        </p>
      )}
    </div>
  );
};
