import React, { useEffect, useMemo, useState } from 'react';
import { Plus, Search, Trash2, Users, X } from 'lucide-react';
import type { Team, User } from '../types';
import type { AddActivityForm, AddAssignmentForm } from './calendarTypes';
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

const SATUAN_OPTIONS = ['Dokumen', 'Blok Sensus', 'Responden', 'Hari', 'Laporan', 'Lainnya'];

let assignmentSeq = 0;
const nextAssignmentKey = () => {
  assignmentSeq += 1;
  return `assignment-${assignmentSeq}`;
};

const createAssignment = (mulai = '', selesai = ''): AddAssignmentForm => ({
  key: nextAssignmentKey(),
  user_id: '',
  peran: 'PIC',
  volume: '1',
  satuan: 'Hari',
  mulai,
  selesai,
});

const EMPTY_FORM: AddActivityForm = {
  team_id: '',
  nama: '',
  jenis: 'Survei',
  mulai: '',
  selesai: '',
  bobot: '1',
  keterangan: '',
  kegiatan_besar: false,
  assignments: [createAssignment()],
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
    assignments: [createAssignment()],
  }));
  const [employeeSearch, setEmployeeSearch] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !saving) onClose();
    };

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [saving, onClose]);

  const selectedTeam = useMemo(
    () => teams.find((team) => String(team.id) === form.team_id),
    [teams, form.team_id]
  );

  const filteredUsers = useMemo(() => {
    const keyword = employeeSearch.trim().toLowerCase();
    if (!keyword) return users;
    return users.filter(
      (user) =>
        user.nama.toLowerCase().includes(keyword) ||
        (user.jabatan || '').toLowerCase().includes(keyword)
    );
  }, [users, employeeSearch]);

  const updateForm = <K extends keyof AddActivityForm>(key: K, value: AddActivityForm[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  const updateAssignment = (key: string, patch: Partial<AddAssignmentForm>) => {
    setForm((current) => ({
      ...current,
      assignments: current.assignments.map((assignment) =>
        assignment.key === key ? { ...assignment, ...patch } : assignment
      ),
    }));
  };

  const addAssignment = () => {
    setForm((current) => ({
      ...current,
      assignments: [
        ...current.assignments,
        createAssignment(current.mulai, current.selesai || current.mulai),
      ],
    }));
  };

  const removeAssignment = (key: string) => {
    setForm((current) => ({
      ...current,
      assignments:
        current.assignments.length <= 1
          ? current.assignments
          : current.assignments.filter((assignment) => assignment.key !== key),
    }));
  };

  const totalPoints = useMemo(() => {
    const bobot = Number(form.bobot) || 0;
    return form.assignments.reduce((sum, assignment) => {
      const volume = Number(assignment.volume) || 0;
      return sum + volume * bobot;
    }, 0);
  }, [form.bobot, form.assignments]);

  const validate = (): string => {
    if (!form.team_id) return 'Tim penanggung jawab wajib dipilih.';
    if (!form.nama.trim()) return 'Nama kegiatan wajib diisi.';
    if (!form.mulai) return 'Tanggal mulai wajib diisi.';
    if (!form.selesai) return 'Tanggal selesai wajib diisi.';
    if (form.selesai < form.mulai) return 'Tanggal selesai tidak boleh sebelum tanggal mulai.';

    const bobot = Number(form.bobot);
    if (!Number.isFinite(bobot) || bobot < 0.5 || bobot > 3) {
      return 'Bobot kesulitan harus berada di antara 0,50 dan 3,00.';
    }

    if (form.assignments.length === 0) {
      return 'Tambahkan minimal satu pegawai yang ditugaskan.';
    }

    const usedUsers = new Set<number>();

    for (let index = 0; index < form.assignments.length; index += 1) {
      const assignment = form.assignments[index];
      const label = `Penugasan #${index + 1}`;

      if (!assignment.user_id) return `${label}: pilih pegawai terlebih dahulu.`;
      const userId = Number(assignment.user_id);
      if (usedUsers.has(userId)) {
        return `${label}: pegawai yang sama tidak boleh dipilih lebih dari sekali dalam satu pengiriman.`;
      }
      usedUsers.add(userId);

      if (!assignment.peran.trim()) return `${label}: peran penugasan wajib diisi.`;
      const volume = Number(assignment.volume);
      if (!Number.isFinite(volume) || volume <= 0) return `${label}: volume harus lebih besar dari 0.`;
      if (!assignment.satuan) return `${label}: satuan penugasan wajib dipilih.`;

      if (assignment.mulai && assignment.mulai < form.mulai) {
        return `${label}: tanggal mulai penugasan tidak boleh sebelum tanggal mulai kegiatan.`;
      }
      if (assignment.selesai && assignment.selesai > form.selesai) {
        return `${label}: tanggal selesai penugasan tidak boleh melewati tanggal selesai kegiatan.`;
      }
      if (assignment.mulai && assignment.selesai && assignment.selesai < assignment.mulai) {
        return `${label}: tanggal selesai penugasan tidak boleh sebelum tanggal mulai.`;
      }
    }

    return '';
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (saving) return;

    const validationMessage = validate();
    if (validationMessage) {
      setError(validationMessage);
      return;
    }

    setSaving(true);
    setError('');

    try {
      await api.post('/activities', {
        nama: form.nama.trim(),
        team_id: Number(form.team_id),
        jenis: form.jenis,
        mulai: form.mulai,
        selesai: form.selesai,
        bobot: Number(form.bobot),
        kegiatan_besar: form.kegiatan_besar,
        keterangan: form.keterangan.trim() || null,
        assignments: form.assignments.map((assignment) => ({
          user_id: Number(assignment.user_id),
          peran: assignment.peran.trim(),
          volume: Number(assignment.volume),
          satuan: assignment.satuan,
          mulai: assignment.mulai || null,
          selesai: assignment.selesai || null,
        })),
      });

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

      setError(
        firstValidationError ||
          maybeError.response?.data?.message ||
          maybeError.message ||
          'Gagal menyimpan kegiatan. Silakan coba lagi.'
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center bg-[#12305C]/55 p-3 sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="add-activity-title"
    >
      <div className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-xl">
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-[#E3EEFA] bg-white px-4 py-4 sm:px-5">
          <div>
            <h2 id="add-activity-title" className="text-base font-bold" style={{ color: PALETTE.navy }}>
              Tambah Kegiatan
            </h2>
            <p className="text-xs" style={{ color: PALETTE.slate }}>
              Kegiatan dan penugasan awal disimpan sekaligus dalam satu transaksi.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={saving}
            aria-label="Tutup form tambah kegiatan"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded text-[#5B6676] transition hover:bg-[#F7F9FC] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-40"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 px-4 py-4 sm:px-5">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label htmlFor="activity-team" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Fungsi / Tim <span style={{ color: PALETTE.coral }}>*</span>
              </label>
              <select
                id="activity-team"
                required
                value={form.team_id}
                onChange={(event) => updateForm('team_id', event.target.value)}
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
              <label htmlFor="activity-stage" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Jenis Kegiatan <span style={{ color: PALETTE.coral }}>*</span>
              </label>
              <select
                id="activity-stage"
                value={form.jenis}
                onChange={(event) => updateForm('jenis', event.target.value)}
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
          </div>

          <div>
            <label htmlFor="activity-name" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Nama Kegiatan <span style={{ color: PALETTE.coral }}>*</span>
            </label>
            <input
              id="activity-name"
              required
              maxLength={200}
              value={form.nama}
              onChange={(event) => updateForm('nama', event.target.value)}
              placeholder="Masukkan nama kegiatan"
              className={fieldClass}
              style={{ color: PALETTE.ink }}
            />
          </div>

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div>
              <label htmlFor="activity-start" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Mulai <span style={{ color: PALETTE.coral }}>*</span>
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
                    selesai: current.selesai && current.selesai >= value ? current.selesai : value,
                    assignments: current.assignments.map((assignment) => ({
                      ...assignment,
                      mulai:
                        !assignment.mulai || assignment.mulai < value ? value : assignment.mulai,
                    })),
                  }));
                }}
                className={fieldClass}
                style={{ color: PALETTE.ink }}
              />
            </div>

            <div>
              <label htmlFor="activity-end" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Selesai <span style={{ color: PALETTE.coral }}>*</span>
              </label>
              <input
                id="activity-end"
                required
                type="date"
                min={form.mulai || undefined}
                value={form.selesai}
                onChange={(event) => updateForm('selesai', event.target.value)}
                className={fieldClass}
                style={{ color: PALETTE.ink }}
              />
            </div>

            <div>
              <label htmlFor="activity-weight" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Bobot Kesulitan (0,50 - 3,00) <span style={{ color: PALETTE.coral }}>*</span>
              </label>
              <input
                id="activity-weight"
                required
                type="number"
                min={0.5}
                max={3}
                step={0.05}
                value={form.bobot}
                onChange={(event) => updateForm('bobot', event.target.value)}
                className={fieldClass}
                style={{ color: PALETTE.ink }}
              />
              <p className="mt-1 text-[10px]" style={{ color: PALETTE.slate }}>
                1,00 = pekerjaan standar, di atas 1 lebih berat, di bawah 1 lebih ringan
              </p>
            </div>
          </div>

          <p className="text-[10px]" style={{ color: PALETTE.slate }}>
            Rumus poin: volume × bobot. Tanggal selesai boleh sama dengan tanggal mulai untuk kegiatan
            satu hari.
          </p>

          <div className="rounded-lg border border-[#E3EEFA] p-3">
            <div className="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <span className="flex items-center gap-1.5 text-xs font-semibold" style={{ color: PALETTE.ink }}>
                <Users className="h-3.5 w-3.5" />
                Pegawai &amp; Penugasan
              </span>
              <span className="text-[10px]" style={{ color: PALETTE.slate }}>
                Total poin: <strong style={{ color: PALETTE.navy }}>{Math.round(totalPoints * 100) / 100}</strong>
              </span>
            </div>

            <div className="relative mb-2">
              <Search
                className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2"
                style={{ color: PALETTE.slate }}
              />
              <input
                type="search"
                value={employeeSearch}
                onChange={(event) => setEmployeeSearch(event.target.value)}
                placeholder="Cari pegawai untuk semua baris..."
                aria-label="Cari pegawai"
                className={`${fieldClass} pl-8 text-xs`}
                style={{ color: PALETTE.ink }}
              />
            </div>

            <div className="space-y-2">
              {form.assignments.map((assignment, index) => {
                const selectedInOtherRows = new Set(
                  form.assignments
                    .filter((item) => item.key !== assignment.key)
                    .map((item) => Number(item.user_id))
                    .filter(Boolean)
                );
                const options = filteredUsers.filter(
                  (user) =>
                    !selectedInOtherRows.has(user.id) ||
                    String(user.id) === assignment.user_id
                );
                const rowPoints =
                  (Number(assignment.volume) || 0) * (Number(form.bobot) || 0);

                return (
                  <div key={assignment.key} className="rounded-lg border border-[#F1F5FB] bg-[#FBFDFF] p-2.5">
                    <div className="mb-2 flex items-center justify-between gap-2">
                      <span className="text-[10px] font-semibold" style={{ color: PALETTE.slate }}>
                        Penugasan #{index + 1}
                      </span>
                      <button
                        type="button"
                        onClick={() => removeAssignment(assignment.key)}
                        disabled={form.assignments.length <= 1}
                        aria-label={`Hapus penugasan #${index + 1}`}
                        className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium transition hover:bg-[#FEEBEB] disabled:cursor-not-allowed disabled:opacity-40"
                        style={{ color: PALETTE.coral }}
                      >
                        <Trash2 className="h-3 w-3" />
                        Hapus
                      </button>
                    </div>

                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-12">
                      <label className="sm:col-span-5">
                        <span className="mb-0.5 block text-[10px]" style={{ color: PALETTE.slate }}>
                          Pegawai
                        </span>
                        <select
                          value={assignment.user_id}
                          onChange={(event) =>
                            updateAssignment(assignment.key, { user_id: event.target.value })
                          }
                          className={`${fieldClass} text-xs`}
                          style={{ color: PALETTE.ink }}
                          aria-label={`Pegawai penugasan #${index + 1}`}
                        >
                          <option value="">Pilih pegawai</option>
                          {options.map((user) => (
                            <option key={user.id} value={user.id}>
                              {user.nama}
                              {user.jabatan ? ` - ${user.jabatan}` : ''}
                            </option>
                          ))}
                        </select>
                      </label>

                      <label className="sm:col-span-3">
                        <span className="mb-0.5 block text-[10px]" style={{ color: PALETTE.slate }}>
                          Peran
                        </span>
                        <input
                          type="text"
                          maxLength={60}
                          value={assignment.peran}
                          onChange={(event) =>
                            updateAssignment(assignment.key, { peran: event.target.value })
                          }
                          placeholder="PIC / Organik"
                          className={`${fieldClass} text-xs`}
                          style={{ color: PALETTE.ink }}
                          aria-label={`Peran penugasan #${index + 1}`}
                        />
                      </label>

                      <label className="sm:col-span-2">
                        <span className="mb-0.5 block text-[10px]" style={{ color: PALETTE.slate }}>
                          Volume
                        </span>
                        <input
                          type="number"
                          min={0.01}
                          step={0.01}
                          value={assignment.volume}
                          onChange={(event) =>
                            updateAssignment(assignment.key, { volume: event.target.value })
                          }
                          className={`${fieldClass} text-xs`}
                          style={{ color: PALETTE.ink }}
                          aria-label={`Volume penugasan #${index + 1}`}
                        />
                      </label>

                      <label className="sm:col-span-2">
                        <span className="mb-0.5 block text-[10px]" style={{ color: PALETTE.slate }}>
                          Satuan
                        </span>
                        <select
                          value={assignment.satuan}
                          onChange={(event) =>
                            updateAssignment(assignment.key, { satuan: event.target.value })
                          }
                          className={`${fieldClass} text-xs`}
                          style={{ color: PALETTE.ink }}
                          aria-label={`Satuan penugasan #${index + 1}`}
                        >
                          {SATUAN_OPTIONS.map((satuan) => (
                            <option key={satuan} value={satuan}>
                              {satuan}
                            </option>
                          ))}
                        </select>
                      </label>

                      <label className="sm:col-span-3">
                        <span className="mb-0.5 block text-[10px]" style={{ color: PALETTE.slate }}>
                          Mulai efektif
                        </span>
                        <input
                          type="date"
                          min={form.mulai || undefined}
                          max={form.selesai || undefined}
                          value={assignment.mulai}
                          onChange={(event) =>
                            updateAssignment(assignment.key, { mulai: event.target.value })
                          }
                          className={`${fieldClass} text-xs`}
                          style={{ color: PALETTE.ink }}
                          aria-label={`Tanggal mulai penugasan #${index + 1}`}
                        />
                      </label>

                      <label className="sm:col-span-3">
                        <span className="mb-0.5 block text-[10px]" style={{ color: PALETTE.slate }}>
                          Selesai efektif
                        </span>
                        <input
                          type="date"
                          min={assignment.mulai || form.mulai || undefined}
                          max={form.selesai || undefined}
                          value={assignment.selesai}
                          onChange={(event) =>
                            updateAssignment(assignment.key, { selesai: event.target.value })
                          }
                          className={`${fieldClass} text-xs`}
                          style={{ color: PALETTE.ink }}
                          aria-label={`Tanggal selesai penugasan #${index + 1}`}
                        />
                      </label>

                      <div className="flex items-end sm:col-span-6">
                        <span className="text-[10px]" style={{ color: PALETTE.slate }}>
                          Poin baris ini:{' '}
                          <strong style={{ color: PALETTE.navy }}>
                            {Math.round(rowPoints * 100) / 100}
                          </strong>
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            <button
              type="button"
              onClick={addAssignment}
              className="mt-2 inline-flex min-h-8 items-center gap-1 rounded-lg border border-dashed border-[#9CC3E5] px-2.5 text-[11px] font-semibold transition hover:bg-[#F7FBFF] focus-visible:outline-2 focus-visible:outline-offset-2"
              style={{ color: PALETTE.blue }}
            >
              <Plus className="h-3.5 w-3.5" />
              Tambah pegawai
            </button>
          </div>

          <div>
            <label htmlFor="activity-note" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
              Keterangan
            </label>
            <textarea
              id="activity-note"
              rows={3}
              value={form.keterangan}
              onChange={(event) => updateForm('keterangan', event.target.value)}
              placeholder="Tambahkan keterangan kegiatan..."
              className="w-full rounded-lg border border-[#E3EEFA] bg-white px-3 py-2 text-sm outline-none transition focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]"
              style={{ color: PALETTE.ink }}
            />
          </div>

          <label className="flex cursor-pointer items-center gap-2 text-xs" style={{ color: PALETTE.ink }}>
            <input
              type="checkbox"
              checked={form.kegiatan_besar}
              onChange={(event) => updateForm('kegiatan_besar', event.target.checked)}
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
              {saving ? 'Menyimpan...' : 'Simpan Kegiatan & Penugasan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
