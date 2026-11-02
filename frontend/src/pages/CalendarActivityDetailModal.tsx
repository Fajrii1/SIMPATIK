import React, { useEffect, useState } from 'react';
import { CalendarDays, Pencil, Star, Trash2, Users, X } from 'lucide-react';
import api from '../services/api';
import type { CalendarEvent, TeamStyle } from './calendarTypes';
import {
  JENIS_KEGIATAN,
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
  canManage?: boolean;
  onChanged?: () => void | Promise<void>;
}

interface EditForm {
  nama: string;
  jenis: string;
  mulai: string;
  selesai: string;
  bobot: string;
  keterangan: string;
  kegiatan_besar: boolean;
}

export const ActivityDetailModal: React.FC<ActivityDetailModalProps> = ({
  event,
  teamStyles,
  onClose,
  canManage = false,
  onChanged,
}) => {
  const [editing, setEditing] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [form, setForm] = useState<EditForm>({
    nama: event.title,
    jenis: event.extendedProps.jenis,
    mulai: event.start,
    selesai: getEventInclusiveEnd(event),
    bobot: String(event.extendedProps.bobot),
    keterangan: event.extendedProps.keterangan || '',
    kegiatan_besar: event.extendedProps.kegiatan_besar,
  });

  useEffect(() => {
    const handleKeyDown = (keyboardEvent: KeyboardEvent) => {
      if (keyboardEvent.key === 'Escape' && !saving && !deleting) {
        if (confirmDelete) setConfirmDelete(false);
        else onClose();
      }
    };

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [onClose, saving, deleting, confirmDelete]);

  const style = getTeamStyle(teamStyles, event.extendedProps.team_id);
  const status = STATUS_STYLES[event.extendedProps.status] || STATUS_STYLES['Belum Mulai'];
  const assignments = event.extendedProps.assigned_users || [];

  const totalPoints = assignments.reduce(
    (sum, assignment) =>
      sum + (Number(assignment.volume) || 0) * (Number(event.extendedProps.bobot) || 0),
    0
  );

  const handleSave = async (submitEvent: React.FormEvent) => {
    submitEvent.preventDefault();
    if (saving) return;

    if (!form.nama.trim()) {
      setError('Nama kegiatan wajib diisi.');
      return;
    }
    if (!form.mulai || !form.selesai) {
      setError('Tanggal mulai dan selesai wajib diisi.');
      return;
    }
    if (form.selesai < form.mulai) {
      setError('Tanggal selesai tidak boleh sebelum tanggal mulai.');
      return;
    }
    const bobot = Number(form.bobot);
    if (!Number.isFinite(bobot) || bobot <= 0 || bobot > 99.99) {
      setError('Bobot kegiatan harus berada pada rentang 0.01 sampai 99.99.');
      return;
    }

    setSaving(true);
    setError('');
    setSuccess('');

    try {
      await api.put(`/activities/${event.extendedProps.activity_id}`, {
        nama: form.nama.trim(),
        jenis: form.jenis,
        mulai: form.mulai,
        selesai: form.selesai,
        bobot,
        keterangan: form.keterangan.trim() || null,
        kegiatan_besar: form.kegiatan_besar,
      });

      setSuccess('Kegiatan berhasil diperbarui.');
      setEditing(false);
      await onChanged?.();
    } catch (requestError: unknown) {
      const maybeError = requestError as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } };
        message?: string;
      };
      const validationErrors = maybeError.response?.data?.errors;
      const firstValidationError = validationErrors
        ? Object.values(validationErrors).flat()[0]
        : undefined;

      setError(
        firstValidationError ||
          maybeError.response?.data?.message ||
          maybeError.message ||
          'Gagal memperbarui kegiatan.'
      );
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (deleting) return;
    setDeleting(true);
    setError('');

    try {
      await api.delete(`/activities/${event.extendedProps.activity_id}`);
      await onChanged?.();
      onClose();
    } catch (requestError: unknown) {
      const maybeError = requestError as { response?: { data?: { message?: string } }; message?: string };
      setError(
        maybeError.response?.data?.message ||
          maybeError.message ||
          'Gagal menghapus kegiatan.'
      );
      setDeleting(false);
      setConfirmDelete(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center bg-[#12305C]/55 p-3 sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="activity-detail-title"
      onClick={() => {
        if (!saving && !deleting) onClose();
      }}
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
            disabled={saving || deleting}
            aria-label="Tutup detail kegiatan"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded text-[#5B6676] transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-40"
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

        {editing && canManage ? (
          <form onSubmit={handleSave} className="mt-4 space-y-3">
            <div>
              <label htmlFor="detail-name" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Nama Kegiatan
              </label>
              <input
                id="detail-name"
                required
                maxLength={200}
                value={form.nama}
                onChange={(e) => setForm((current) => ({ ...current, nama: e.target.value }))}
                className="h-10 w-full rounded-lg border border-[#E3EEFA] px-3 text-sm outline-none focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]"
                style={{ color: PALETTE.ink }}
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label htmlFor="detail-jenis" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                  Jenis
                </label>
                <select
                  id="detail-jenis"
                  value={form.jenis}
                  onChange={(e) => setForm((current) => ({ ...current, jenis: e.target.value }))}
                  className="h-10 w-full rounded-lg border border-[#E3EEFA] px-3 text-sm outline-none focus:border-[#3B82C4]"
                  style={{ color: PALETTE.ink }}
                >
                  {JENIS_KEGIATAN.map((jenis) => (
                    <option key={jenis} value={jenis}>
                      {jenis}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label htmlFor="detail-weight" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                  Bobot
                </label>
                <input
                  id="detail-weight"
                  type="number"
                  min={0.01}
                  max={99.99}
                  step={0.01}
                  value={form.bobot}
                  onChange={(e) => setForm((current) => ({ ...current, bobot: e.target.value }))}
                  className="h-10 w-full rounded-lg border border-[#E3EEFA] px-3 text-sm outline-none focus:border-[#3B82C4]"
                  style={{ color: PALETTE.ink }}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label htmlFor="detail-start" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                  Mulai
                </label>
                <input
                  id="detail-start"
                  type="date"
                  value={form.mulai}
                  onChange={(e) => setForm((current) => ({ ...current, mulai: e.target.value }))}
                  className="h-10 w-full rounded-lg border border-[#E3EEFA] px-3 text-sm outline-none focus:border-[#3B82C4]"
                  style={{ color: PALETTE.ink }}
                />
              </div>
              <div>
                <label htmlFor="detail-end" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                  Selesai
                </label>
                <input
                  id="detail-end"
                  type="date"
                  min={form.mulai || undefined}
                  value={form.selesai}
                  onChange={(e) => setForm((current) => ({ ...current, selesai: e.target.value }))}
                  className="h-10 w-full rounded-lg border border-[#E3EEFA] px-3 text-sm outline-none focus:border-[#3B82C4]"
                  style={{ color: PALETTE.ink }}
                />
              </div>
            </div>

            <div>
              <label htmlFor="detail-note" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Keterangan
              </label>
              <textarea
                id="detail-note"
                rows={2}
                value={form.keterangan}
                onChange={(e) => setForm((current) => ({ ...current, keterangan: e.target.value }))}
                className="w-full rounded-lg border border-[#E3EEFA] px-3 py-2 text-sm outline-none focus:border-[#3B82C4]"
                style={{ color: PALETTE.ink }}
              />
            </div>

            <label className="flex items-center gap-2 text-xs" style={{ color: PALETTE.ink }}>
              <input
                type="checkbox"
                checked={form.kegiatan_besar}
                onChange={(e) => setForm((current) => ({ ...current, kegiatan_besar: e.target.checked }))}
                className="h-3.5 w-3.5 rounded border-[#5B6676]"
              />
              Kegiatan besar
            </label>

            {error && (
              <p
                role="alert"
                className="rounded-lg border px-3 py-2 text-xs"
                style={{ backgroundColor: PALETTE.blush, borderColor: PALETTE.coral, color: PALETTE.coral }}
              >
                {error}
              </p>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => {
                  setEditing(false);
                  setError('');
                }}
                disabled={saving}
                className="min-h-9 rounded-lg border border-[#E3EEFA] px-3 text-xs font-medium disabled:opacity-50"
                style={{ color: PALETTE.slate }}
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={saving}
                className="min-h-9 rounded-lg px-3 text-xs font-semibold text-white disabled:opacity-50"
                style={{ backgroundColor: PALETTE.blue }}
              >
                {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
              </button>
            </div>
          </form>
        ) : (
          <>
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
              <DetailRow
                icon={<Star className="h-3.5 w-3.5" />}
                label="Bobot × total volume"
                value={`${event.extendedProps.bobot} × ${Math.round(
                  assignments.reduce((sum, assignment) => sum + (Number(assignment.volume) || 0), 0) * 100
                ) / 100} = ${Math.round(totalPoints * 100) / 100} poin`}
              />

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
                    const points =
                      (Number(assignment.volume) || 0) * (Number(event.extendedProps.bobot) || 0);

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
                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px]" style={{ color: PALETTE.slate }}>
                          <span>
                            Vol. {assignment.volume ?? '-'} {assignment.satuan || ''}
                          </span>
                          <span>{Math.round(points * 100) / 100} poin</span>
                          <span>
                            {assignment.mulai && assignment.selesai
                              ? `${formatDateId(assignment.mulai)} - ${formatDateId(assignment.selesai)}`
                              : 'Mengikuti periode kegiatan'}
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {success && (
              <p
                role="status"
                className="mt-3 rounded-lg border px-3 py-2 text-xs"
                style={{ backgroundColor: PALETTE.mint, borderColor: PALETTE.teal, color: PALETTE.teal }}
              >
                {success}
              </p>
            )}

            {error && (
              <p
                role="alert"
                className="mt-3 rounded-lg border px-3 py-2 text-xs"
                style={{ backgroundColor: PALETTE.blush, borderColor: PALETTE.coral, color: PALETTE.coral }}
              >
                {error}
              </p>
            )}

            {confirmDelete ? (
              <div
                className="mt-4 rounded-lg border px-3 py-3 text-xs"
                style={{ backgroundColor: PALETTE.blush, borderColor: PALETTE.coral }}
              >
                <p className="font-semibold" style={{ color: PALETTE.coral }}>
                  Hapus kegiatan ini?
                </p>
                <p className="mt-0.5" style={{ color: PALETTE.ink }}>
                  Kegiatan akan dihapus dari kalender. Tindakan ini memerlukan hak akses tim terkait.
                </p>
                <div className="mt-2 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setConfirmDelete(false)}
                    disabled={deleting}
                    className="min-h-8 rounded-lg border border-[#E3EEFA] bg-white px-3 text-xs font-medium disabled:opacity-50"
                    style={{ color: PALETTE.slate }}
                  >
                    Batal
                  </button>
                  <button
                    type="button"
                    onClick={handleDelete}
                    disabled={deleting}
                    className="min-h-8 rounded-lg px-3 text-xs font-semibold text-white disabled:opacity-50"
                    style={{ backgroundColor: PALETTE.coral }}
                  >
                    {deleting ? 'Menghapus...' : 'Ya, hapus'}
                  </button>
                </div>
              </div>
            ) : (
              <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                {canManage && (
                  <>
                    <button
                      type="button"
                      onClick={() => setConfirmDelete(true)}
                      className="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border px-3 text-xs font-semibold transition hover:bg-[#FEF3F3] focus-visible:outline-2 focus-visible:outline-offset-2"
                      style={{ borderColor: PALETTE.coral, color: PALETTE.coral }}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                      Hapus
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setEditing(true);
                        setError('');
                        setSuccess('');
                      }}
                      className="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border px-3 text-xs font-semibold transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2"
                      style={{ borderColor: PALETTE.paleBlue, color: PALETTE.navy }}
                    >
                      <Pencil className="h-3.5 w-3.5" />
                      Edit
                    </button>
                  </>
                )}
                <button
                  type="button"
                  onClick={onClose}
                  className="min-h-10 rounded-lg px-4 text-sm font-semibold text-white transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2"
                  style={{ backgroundColor: PALETTE.blue }}
                >
                  Tutup
                </button>
              </div>
            )}
          </>
        )}
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
