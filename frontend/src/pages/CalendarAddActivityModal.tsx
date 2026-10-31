import React, { useEffect, useMemo, useState } from 'react';
import { Search, X } from 'lucide-react';
import type { Team, User } from '../types';
import type { AddActivityForm } from './calendarTypes';
import { JENIS_KEGIATAN, PALETTE } from './calendarUtils';
import api from '../services/api';

interface AddActivityModalProps {
  open: boolean;
  teams: Team[];
  users: User[];
  defaultTeamId?: number;
  onClose: () => void;
  onSaved: (startYear?: number) => void | Promise<void>;
}

const EMPTY_FORM: AddActivityForm = {
  team_id: '',
  nama: '',
  jenis: 'Survei',
  mulai: '',
  selesai: '',
  pic_id: '',
  member_ids: [],
  keterangan: '',
  kegiatan_besar: false,
};

const fieldClass =
  'h-10 w-full rounded-lg border border-[#E3EEFA] bg-white px-3 text-sm outline-none transition focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]';

export const AddActivityModal: React.FC<AddActivityModalProps> = (props) => {
  if (!props.open) return null;
  return <AddActivityModalContent {...props} />;
};

const AddActivityModalContent: React.FC<AddActivityModalProps> = ({
  teams,
  users,
  defaultTeamId,
  onClose,
  onSaved,
}) => {
  const [form, setForm] = useState<AddActivityForm>(() => ({
    ...EMPTY_FORM,
    team_id: defaultTeamId ? String(defaultTeamId) : '',
  }));
  const [picSearch, setPicSearch] = useState('');
  const [memberSearch, setMemberSearch] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [createdActivityId, setCreatedActivityId] = useState<number | null>(null);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !saving) onClose();
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [saving, onClose]);

  const selectedTeam = useMemo(
    () => teams.find((team) => String(team.id) === form.team_id),
    [teams, form.team_id]
  );

  const picOptions = useMemo(() => {
    const keyword = picSearch.trim().toLowerCase();
    const options = users.filter((user) => {
      if (!keyword) return true;
      return (
        user.nama.toLowerCase().includes(keyword) ||
        (user.jabatan || '').toLowerCase().includes(keyword)
      );
    });

    if (form.pic_id && !options.some((user) => String(user.id) === form.pic_id)) {
      const selected = users.find((user) => String(user.id) === form.pic_id);
      if (selected) options.unshift(selected);
    }

    return options;
  }, [users, picSearch, form.pic_id]);

  const memberOptions = useMemo(() => {
    const keyword = memberSearch.trim().toLowerCase();
    return users.filter((user) => {
      if (String(user.id) === form.pic_id) return false;
      if (!keyword) return true;
      return (
        user.nama.toLowerCase().includes(keyword) ||
        (user.jabatan || '').toLowerCase().includes(keyword)
      );
    });
  }, [users, memberSearch, form.pic_id]);

  if (!open) return null;

  const toggleMember = (id: string) => {
    setForm((current) => ({
      ...current,
      member_ids: current.member_ids.includes(id)
        ? current.member_ids.filter((item) => item !== id)
        : [...current.member_ids, id],
    }));
  };

  const validate = (): string => {
    if (!form.team_id) return 'Tim penanggung jawab wajib dipilih.';
    if (!form.nama.trim()) return 'Nama kegiatan wajib diisi.';
    if (!form.mulai) return 'Tanggal mulai wajib diisi.';
    if (!form.selesai) return 'Tanggal selesai wajib diisi.';
    if (form.selesai < form.mulai) return 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
    if (!form.pic_id) return 'Penanggung jawab wajib dipilih.';
    return '';
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    const validationMessage = validate();

    if (validationMessage) {
      setError(validationMessage);
      return;
    }

    setSaving(true);
    setError('');

    try {
      let activityId = createdActivityId;

      if (!activityId) {
        const response = await api.post('/activities', {
          nama: form.nama.trim(),
          team_id: Number(form.team_id),
          jenis: form.jenis,
          mulai: form.mulai,
          selesai: form.selesai,
          bobot: 1,
          kegiatan_besar: form.kegiatan_besar,
          keterangan: form.keterangan.trim() || null,
        });

        activityId = Number(response.data?.data?.id || response.data?.id);

        if (!activityId) {
          throw new Error('Kegiatan tersimpan, tetapi ID kegiatan tidak ditemukan.');
        }

        setCreatedActivityId(activityId);
      }

      const assignments = [
        { userId: Number(form.pic_id), peran: 'PIC' },
        ...form.member_ids
          .filter((id) => id !== form.pic_id)
          .map((id) => ({ userId: Number(id), peran: 'Organik' })),
      ];

      const seen = new Set<number>();
      for (const assignment of assignments) {
        if (!assignment.userId || seen.has(assignment.userId)) continue;
        seen.add(assignment.userId);

        await api.post(`/activities/${activityId}/assignments`, {
          user_id: assignment.userId,
          peran: assignment.peran,
          volume: 1,
          satuan: 'Hari',
          mulai: form.mulai,
          selesai: form.selesai,
        });
      }

      const startYear = form.mulai ? Number(form.mulai.slice(0, 4)) : undefined;
      await onSaved(startYear);
    } catch (requestError: unknown) {
      const maybeError = requestError as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } };
        message?: string;
      };

      const validationErrors = maybeError.response?.data?.errors;
      const firstValidationError = validationErrors
        ? Object.values(validationErrors).flat()[0]
        : undefined;

      const message =
        firstValidationError ||
        maybeError.response?.data?.message ||
        maybeError.message ||
        'Gagal menyimpan kegiatan. Silakan coba lagi.';

      setError(
        createdActivityId
          ? `${message} Kegiatan sudah tersimpan; perbaiki data penugasan lalu klik Simpan lagi.`
          : message
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center bg-[#12305C]/55 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="add-activity-title"
    >
      <div className="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white shadow-xl">
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-[#E3EEFA] bg-white px-5 py-4">
          <div>
            <h2 id="add-activity-title" className="text-base font-bold" style={{ color: PALETTE.navy }}>
              Tambah Kegiatan
            </h2>
            <p className="text-xs" style={{ color: PALETTE.slate }}>
              Jadwalkan kegiatan dan tugaskan pegawai yang terlibat.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={saving}
            aria-label="Tutup form tambah kegiatan"
            className="flex h-9 w-9 items-center justify-center rounded text-[#5B6676] transition hover:bg-[#F7F9FC] disabled:opacity-40"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 px-5 py-4">
          <div>
            <label htmlFor="activity-team" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Fungsi / Tim
            </label>
            <select
              id="activity-team"
              required
              value={form.team_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  team_id: event.target.value,
                  pic_id: '',
                  member_ids: [],
                }))
              }
              className={fieldClass}
              style={{ color: PALETTE.ink }}
            >
              <option value="">Pilih tim</option>
              {teams.map((team) => (
                <option key={team.id} value={team.id}>
                  {team.nama}
                </option>
              ))}
            </select>
            {selectedTeam?.ketua && (
              <p className="mt-1 text-[10px]" style={{ color: PALETTE.slate }}>
                Ketua tim: {selectedTeam.ketua.nama}
              </p>
            )}
          </div>

          <div>
            <label htmlFor="activity-name" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Nama Kegiatan
            </label>
            <input
              id="activity-name"
              required
              maxLength={200}
              value={form.nama}
              onChange={(event) => setForm((current) => ({ ...current, nama: event.target.value }))}
              placeholder="Masukkan nama kegiatan"
              className={fieldClass}
              style={{ color: PALETTE.ink }}
            />
          </div>

          <div>
            <label htmlFor="activity-stage" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Tahapan
            </label>
            <select
              id="activity-stage"
              value={form.jenis}
              onChange={(event) => setForm((current) => ({ ...current, jenis: event.target.value }))}
              className={fieldClass}
              style={{ color: PALETTE.ink }}
            >
              {JENIS_KEGIATAN.map((jenis) => (
                <option key={jenis} value={jenis}>
                  {jenis}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label htmlFor="activity-start" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Tanggal Mulai
              </label>
              <input
                id="activity-start"
                required
                type="date"
                value={form.mulai}
                onChange={(event) => {
                  const value = event.target.value;
                  setForm((current) => ({
                    ...current,
                    mulai: value,
                    selesai: current.selesai || value,
                  }));
                }}
                className={fieldClass}
                style={{ color: PALETTE.ink }}
              />
            </div>

            <div>
              <label htmlFor="activity-end" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Tanggal Selesai
              </label>
              <input
                id="activity-end"
                required
                type="date"
                min={form.mulai || undefined}
                value={form.selesai}
                onChange={(event) => setForm((current) => ({ ...current, selesai: event.target.value }))}
                className={fieldClass}
                style={{ color: PALETTE.ink }}
              />
            </div>
          </div>

          <p className="-mt-2 text-[10px]" style={{ color: PALETTE.slate }}>
            Tanggal selesai otomatis sama dengan tanggal mulai untuk kegiatan satu hari.
          </p>

          <div>
            <label htmlFor="activity-pic-search" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Penanggung Jawab
            </label>
            <div className="relative mb-1.5">
              <Search
                className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2"
                style={{ color: PALETTE.slate }}
              />
              <input
                id="activity-pic-search"
                type="search"
                value={picSearch}
                onChange={(event) => setPicSearch(event.target.value)}
                placeholder="Cari nama organik..."
                className={`${fieldClass} pl-8 text-xs`}
                style={{ color: PALETTE.ink }}
              />
            </div>
            <select
              required
              aria-label="Pilih penanggung jawab"
              value={form.pic_id}
              onChange={(event) => setForm((current) => ({ ...current, pic_id: event.target.value }))}
              className={fieldClass}
              style={{ color: PALETTE.ink }}
            >
              <option value="">Pilih nama organik</option>
              {picOptions.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.nama}
                  {user.jabatan ? ` - ${user.jabatan}` : ''}
                </option>
              ))}
            </select>
          </div>

          <div>
            <span className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Organik Terlibat <span className="font-normal">(opsional)</span>
            </span>

            <div className="relative mb-1.5">
              <Search
                className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2"
                style={{ color: PALETTE.slate }}
              />
              <input
                type="search"
                value={memberSearch}
                onChange={(event) => setMemberSearch(event.target.value)}
                placeholder="Cari pegawai..."
                aria-label="Cari pegawai yang terlibat"
                className={`${fieldClass} pl-8 text-xs`}
                style={{ color: PALETTE.ink }}
              />
            </div>

            <div className="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-[#E3EEFA] p-2">
              {memberOptions.map((user) => (
                <label
                  key={user.id}
                  className="flex min-h-8 cursor-pointer items-center gap-2 rounded px-1 text-xs hover:bg-[#F7F9FC]"
                  style={{ color: PALETTE.ink }}
                >
                  <input
                    type="checkbox"
                    checked={form.member_ids.includes(String(user.id))}
                    onChange={() => toggleMember(String(user.id))}
                    className="h-3.5 w-3.5 rounded border-[#5B6676]"
                  />
                  <span className="truncate">
                    {user.nama}
                    {user.jabatan ? (
                      <span className="ml-1 text-[10px]" style={{ color: PALETTE.slate }}>
                        {user.jabatan}
                      </span>
                    ) : null}
                  </span>
                </label>
              ))}

              {memberOptions.length === 0 && (
                <p className="py-3 text-center text-[11px]" style={{ color: PALETTE.slate }}>
                  Tidak ada pegawai yang cocok.
                </p>
              )}
            </div>

            {form.member_ids.length > 0 && (
              <div className="mt-1.5 flex flex-wrap gap-1">
                {form.member_ids.map((id) => {
                  const selected = users.find((user) => String(user.id) === id);
                  if (!selected) return null;
                  return (
                    <button
                      key={id}
                      type="button"
                      onClick={() => toggleMember(id)}
                      className="rounded-full bg-[#E3EEFA] px-2 py-0.5 text-[10px] font-medium"
                      style={{ color: PALETTE.navy }}
                      title={`Hapus ${selected.nama}`}
                    >
                      {selected.nama} x
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          <div>
            <label htmlFor="activity-note" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Keterangan
            </label>
            <textarea
              id="activity-note"
              rows={3}
              value={form.keterangan}
              onChange={(event) => setForm((current) => ({ ...current, keterangan: event.target.value }))}
              placeholder="Tambahkan keterangan kegiatan..."
              className="w-full rounded-lg border border-[#E3EEFA] bg-white px-3 py-2 text-sm outline-none transition focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]"
              style={{ color: PALETTE.ink }}
            />
          </div>

          <label className="flex cursor-pointer items-center gap-2 text-xs" style={{ color: PALETTE.ink }}>
            <input
              type="checkbox"
              checked={form.kegiatan_besar}
              onChange={(event) =>
                setForm((current) => ({ ...current, kegiatan_besar: event.target.checked }))
              }
              className="h-3.5 w-3.5 rounded border-[#5B6676]"
            />
            Tandai sebagai kegiatan besar
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

          <div className="flex justify-end gap-2 border-t border-[#E3EEFA] pt-4">
            <button
              type="button"
              onClick={onClose}
              disabled={saving}
              className="min-h-10 rounded-lg border border-[#E3EEFA] px-4 text-sm font-medium transition hover:bg-[#F7F9FC] disabled:opacity-50"
              style={{ color: PALETTE.slate }}
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={saving || teams.length === 0}
              className="min-h-10 rounded-lg px-4 text-sm font-semibold text-white transition hover:brightness-95 disabled:opacity-50"
              style={{ backgroundColor: PALETTE.blue }}
            >
              {saving ? 'Menyimpan...' : 'Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
