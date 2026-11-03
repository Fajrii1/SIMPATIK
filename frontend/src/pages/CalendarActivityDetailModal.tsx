import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, ExternalLink, Star, Users, X } from 'lucide-react';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  PALETTE,
  STATUS_STYLES,
  formatDateId,
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
      if (keyboardEvent.key === 'Escape') {
        onClose();
      }
    };

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [onClose]);

  const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
  const status = STATUS_STYLES[event.extendedProps.status] || STATUS_STYLES['Belum Mulai'];
  const assignments = event.extendedProps.assigned_users || [];

  // Metrik penugasan hanya tampil bila semua viewer berhak melihatnya.
  const allMetricsVisible = assignments.every(
    (assignment) => assignment.metrics_visible !== false
  );

  const totalPoints = allMetricsVisible
    ? assignments.reduce(
        (sum, assignment) =>
          sum + (Number(assignment.volume) || 0) * (Number(event.extendedProps.bobot) || 0),
        0
      )
    : null;

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center bg-bps/55 p-3 sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="activity-detail-title"
      onClick={onClose}
    >
      <div
        className="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl sm:p-5"
        onClick={(clickEvent) => clickEvent.stopPropagation()}
      >
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
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded text-[#5B6676] transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2"
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
            value={`${event.extendedProps.assignments_count || assignments.length} penugasan`}
          />
          {totalPoints !== null && (
            <DetailRow
              icon={<Star className="h-3.5 w-3.5" />}
              label="Bobot × total volume"
              value={`${event.extendedProps.bobot} × ${
                Math.round(
                  assignments.reduce((sum, assignment) => sum + (Number(assignment.volume) || 0), 0) * 100
                ) / 100
              } = ${Math.round(totalPoints * 100) / 100} poin`}
            />
          )}

          <div className="flex items-start justify-between gap-3">
            <span className="shrink-0" style={{ color: PALETTE.slate }}>
              Keterangan
            </span>
            <span
              className="max-w-[70%] whitespace-pre-wrap text-right font-medium"
              style={{ color: PALETTE.ink }}
            >
              {event.extendedProps.keterangan || '-'}
            </span>
          </div>
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
            <div className="mt-2 max-h-64 divide-y divide-[#F1F5FB] overflow-y-auto rounded-lg border border-[#E3EEFA]">
              {assignments.map((assignment, index) => {
                const metricsVisible = assignment.metrics_visible !== false;
                const points = metricsVisible
                  ? (Number(assignment.volume) || 0) * (Number(event.extendedProps.bobot) || 0)
                  : null;

                return (
                  <div key={`${assignment.user_id}-${index}`} className="px-3 py-2 text-xs">
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate font-semibold" style={{ color: PALETTE.ink }}>
                        {assignment.nama || 'Pegawai'}
                      </span>
                      <span
                        className="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        style={{ backgroundColor: style.soft, color: style.text }}
                      >
                        {assignment.peran}
                      </span>
                    </div>
                    {metricsVisible ? (
                      <div
                        className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px]"
                        style={{ color: PALETTE.slate }}
                      >
                        <span>
                          Vol. {assignment.volume ?? '-'} {assignment.satuan || ''}
                        </span>
                        <span>{Math.round((points ?? 0) * 100) / 100} poin</span>
                        <span>
                          {assignment.mulai && assignment.selesai
                            ? `${formatDateId(assignment.mulai)} - ${formatDateId(assignment.selesai)}`
                            : 'Mengikuti periode kegiatan'}
                        </span>
                      </div>
                    ) : null}
                  </div>
                );
              })}
            </div>
          )}
        </div>

        <div className="mt-4 flex flex-col gap-2 border-t border-[#E3EEFA] pt-3 sm:flex-row sm:items-center sm:justify-between">
          <Link
            to={`/activities?id=${event.extendedProps.activity_id}`}
            className="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border px-3 text-xs font-semibold transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2"
            style={{ borderColor: PALETTE.paleBlue, color: PALETTE.navy }}
          >
            <ExternalLink className="h-3.5 w-3.5" />
            Buka di Daftar Kegiatan
          </Link>
          <button
            type="button"
            onClick={onClose}
            className="min-h-10 rounded-lg px-4 text-sm font-semibold text-white transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2"
            style={{ backgroundColor: PALETTE.blue }}
          >
            Tutup
          </button>
        </div>
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
