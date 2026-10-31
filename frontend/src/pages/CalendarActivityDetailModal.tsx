import React, { useEffect } from 'react';
import { CalendarDays, Star, Users, X } from 'lucide-react';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  PALETTE,
  STATUS_STYLES,
  formatDateRange,
  getEventInclusiveEnd,
  getTeamStyle,
} from './calendarUtils';

interface ActivityDetailModalProps {
  event: CalendarEvent;
  teamStyles: Map<number, TeamStyle>;
  onClose: () => void;
}

export const ActivityDetailModal: React.FC<ActivityDetailModalProps> = ({
  event,
  teamStyles,
  onClose,
}) => {
  useEffect(() => {
    const handleKeyDown = (keyboardEvent: KeyboardEvent) => {
      if (keyboardEvent.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
  const status = STATUS_STYLES[event.extendedProps.status] || STATUS_STYLES['Belum Mulai'];
  const assignments = event.extendedProps.assigned_users || [];

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center bg-[#12305C]/55 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="activity-detail-title"
    >
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
        <div className="flex items-start justify-between gap-3">
          <div>
            <span
              className="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
              style={{ backgroundColor: style.soft, color: style.text }}
            >
              {event.extendedProps.team_nama || 'Tanpa tim'}
            </span>
            <h2
              id="activity-detail-title"
              className="mt-2 text-base font-bold leading-snug"
              style={{ color: PALETTE.navy }}
            >
              {event.title}
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Tutup detail kegiatan"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded text-[#5B6676] transition hover:bg-[#F7F9FC]"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="mt-3 flex flex-wrap gap-1.5">
          <span
            className="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
            style={{ backgroundColor: status.bg, color: status.text, borderColor: status.border }}
          >
            {event.extendedProps.status}
          </span>
          <span
            className="rounded-full border px-2 py-0.5 text-[10px] font-medium"
            style={{ backgroundColor: PALETTE.bg, color: PALETTE.slate, borderColor: PALETTE.paleBlue }}
          >
            {event.extendedProps.jenis}
          </span>
          {event.extendedProps.kegiatan_besar && (
            <span
              className="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
              style={{ backgroundColor: PALETTE.peach, color: PALETTE.navy, borderColor: PALETTE.peach }}
            >
              <Star className="h-2.5 w-2.5" /> Kegiatan besar
            </span>
          )}
        </div>

        <div className="mt-4 space-y-2.5 text-xs">
          <DetailRow
            icon={<CalendarDays className="h-3.5 w-3.5" />}
            label="Periode"
            value={formatDateRange(event.start, getEventInclusiveEnd(event))}
          />
          <DetailRow
            icon={<Users className="h-3.5 w-3.5" />}
            label="Jumlah penugasan"
            value={`${event.extendedProps.assignments_count || assignments.length} pegawai`}
          />
          <DetailRow
            icon={<Star className="h-3.5 w-3.5" />}
            label="Bobot kegiatan"
            value={String(event.extendedProps.bobot)}
          />
        </div>

        <div className="mt-4 border-t border-[#E3EEFA] pt-3">
          <h3 className="text-xs font-semibold" style={{ color: PALETTE.ink }}>
            Pegawai terlibat
          </h3>

          {assignments.length === 0 ? (
            <p className="mt-2 text-xs" style={{ color: PALETTE.slate }}>
              Belum ada penugasan pegawai pada kegiatan ini.
            </p>
          ) : (
            <div className="mt-2 max-h-52 divide-y divide-[#F1F5FB] overflow-y-auto rounded-lg border border-[#E3EEFA]">
              {assignments.map((assignment, index) => (
                <div
                  key={`${assignment.user_id}-${index}`}
                  className="flex items-center justify-between gap-3 px-3 py-2 text-xs"
                >
                  <span className="truncate font-medium" style={{ color: PALETTE.ink }}>
                    {assignment.nama || 'Pegawai'}
                  </span>
                  <span
                    className="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    style={{ backgroundColor: style.soft, color: style.text }}
                  >
                    {assignment.peran}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>

        <button
          type="button"
          onClick={onClose}
          className="mt-5 min-h-10 w-full rounded-lg text-sm font-semibold text-white transition hover:brightness-95"
          style={{ backgroundColor: PALETTE.blue }}
        >
          Tutup
        </button>
      </div>
    </div>
  );
};

const DetailRow: React.FC<{ icon: React.ReactNode; label: string; value: string }> = ({
  icon,
  label,
  value,
}) => (
  <div className="flex items-center justify-between gap-3">
    <span className="flex items-center gap-1.5" style={{ color: PALETTE.slate }}>
      {icon}
      {label}
    </span>
    <span className="text-right font-semibold" style={{ color: PALETTE.ink }}>
      {value}
    </span>
  </div>
);
