import React from 'react';
import { MONTHS_SHORT, PALETTE, getDaysInMonth, heatCellStyle } from './calendarUtils';

interface HeatmapTabProps {
  year: number;
  dailyCounts: Record<string, number>;
}

const LEGEND_STEPS = [0, 1, 2, 3, 4, 5, 7];

export const HeatmapTab: React.FC<HeatmapTabProps> = ({ year, dailyCounts }) => {
  return (
    <section className="rounded-xl border border-[#E3EEFA] bg-white p-4 shadow-sm sm:p-5">
      <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-sm font-bold sm:text-base" style={{ color: PALETTE.navy }}>
            Peta Kepadatan {year}
          </h2>
          <p className="mt-0.5 text-xs" style={{ color: PALETTE.slate }}>
            Jumlah kegiatan yang berjalan pada setiap tanggal.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2 text-[11px]" style={{ color: PALETTE.slate }}>
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
          <span>Padat (7+)</span>
        </div>
      </div>

      <div className="overflow-x-auto pb-1">
        <div className="min-w-[860px]">
          <div className="mb-1 grid grid-cols-[54px_repeat(31,minmax(0,1fr))] gap-1">
            <span />
            {Array.from({ length: 31 }, (_, index) => (
              <span
                key={index}
                className="text-center text-[10px] font-medium"
                style={{ color: PALETTE.slate }}
              >
                {index + 1}
              </span>
            ))}
          </div>

          {MONTHS_SHORT.map((month, monthIndex) => (
            <div
              key={month}
              className="mb-1 grid grid-cols-[54px_repeat(31,minmax(0,1fr))] items-center gap-1"
            >
              <span className="text-[11px] font-semibold" style={{ color: PALETTE.ink }}>
                {month}
              </span>

              {Array.from({ length: 31 }, (_, dayIndex) => {
                const day = dayIndex + 1;
                const valid = day <= getDaysInMonth(year, monthIndex);
                const date = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const count = valid ? dailyCounts[date] || 0 : 0;

                if (!valid) {
                  return <span key={day} className="h-7 rounded-[3px]" aria-hidden="true" />;
                }

                return (
                  <span
                    key={day}
                    className="flex h-7 items-center justify-center rounded-[3px] text-[10px] font-semibold tabular-nums"
                    style={heatCellStyle(count)}
                    title={`${day} ${month} ${year}: ${count} kegiatan`}
                    aria-label={`${day} ${month} ${year}: ${count} kegiatan`}
                  >
                    {count > 0 ? count : ''}
                  </span>
                );
              })}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};
