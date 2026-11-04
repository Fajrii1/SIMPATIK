import React, { useEffect, useMemo, useRef, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import {
  AlertTriangle,
  Search,
  X,
  Loader2,
  Copy,
  Info,
} from 'lucide-react';
import type {
  Activity,
  Team,
  User,
  PreviewBatchResponse,
  PreviewBatchPegawaiResult,
} from '../types';
import { getApiErrorMessage } from '../services/errors';

interface ActivityFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSaved: () => void;
  teams: Team[];
  editing: Activity | null;
}

interface AssignmentRow {
  key: string;
  user_id: number;
  peran: string;
  peranLainnya: string;
  volume: string;
  satuan: string;
  mulai: string;
  selesai: string;
}

const ROLE_OPTIONS = ['Anggota', 'Pengolah Data', 'Pengawas', 'Lainnya'] as const;
const SATUAN_OPTIONS = ['Dokumen', 'Blok Sensus', 'Responden', 'Hari', 'Laporan', 'Lainnya'] as const;
const PJ_ROLE = 'Penanggung Jawab';
const JENIS_OPTIONS = [
  'Survei',
  'Sensus',
  'Pengolahan Data',
  'Diseminasi',
  'Pembinaan',
  'Rapat dan Koordinasi',
  'Lainnya',
] as const;

const emptyForm = {
  nama: '',
  jenis: 'Survei',
  team_id: '',
  mulai: '',
  selesai: '',
  bobot: '1.0',
  kegiatan_besar: false,
  keterangan: '',
};

const buatKunci = () => `row-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

const indicatorLabels: Record<string, string> = {
  aman: 'Aman',
  hampir_penuh: 'Hampir Penuh',
  melebihi: 'Melebihi',
};

const indicatorClasses: Record<string, string> = {
  aman: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  hampir_penuh: 'bg-amber-50 text-amber-700 border-amber-200',
  melebihi: 'bg-rose-50 text-rose-700 border-rose-200',
};

const buatBaris = (userId: number): AssignmentRow => ({
  key: buatKunci(),
  user_id: userId,
  peran: 'Anggota',
  peranLainnya: '',
  volume: '',
  satuan: 'Dokumen',
  mulai: '',
  selesai: '',
});

export const ActivityFormModal: React.FC<ActivityFormModalProps> = ({
  isOpen,
  onClose,
  onSaved,
  teams,
  editing,
}) => {
  const { user } = useAuth();

  const isAdmin = user?.peran === 'admin';
  const isEditing = editing !== null;

  const [formData, setFormData] = useState(emptyForm);
  const [pjId, setPjId] = useState('');
  const [memberIds, setMemberIds] = useState<number[]>([]);
  const [memberSearch, setMemberSearch] = useState('');
  const [rows, setRows] = useState<AssignmentRow[]>([]);
  const [showPeriodAdvanced, setShowPeriodAdvanced] = useState(false);
  const [candidates, setCandidates] = useState<User[]>([]);
  const [formErrors, setFormErrors] = useState<string[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const modalRef = useRef<HTMLDivElement>(null);

  // Pratinjau batch
  const [preview, setPreview] = useState<PreviewBatchResponse | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState('');
  const [searchFocused, setSearchFocused] = useState(false);

  const selectableTeams = useMemo(() => {
    if (isAdmin) return teams;
    return teams.filter((team) => team.ketua_id === user?.id);
  }, [teams, isAdmin, user]);

  // Muat daftar kandidat penugasan saat modal dibuka.
  useEffect(() => {
    if (!isOpen) return;

    const loadCandidates = async () => {
      try {
        if (isAdmin) {
          const res = await api.get('/users', { params: { aktif: true, per_page: 100 } });
          setCandidates(res.data?.data ?? []);
          return;
        }

        const res = await api.get('/teams', { params: { per_page: 100 } });
        const ledTeams = (res.data?.data ?? []).filter((team: Team) => team.ketua_id === user?.id);
        const byId = new Map<number, User>();

        ledTeams.forEach((team: Team) => {
          (team.members ?? []).forEach((member) => {
            if (member.aktif !== false) {
              byId.set(member.id, member);
            }
          });
        });

        setCandidates(Array.from(byId.values()).sort((a, b) => a.nama.localeCompare(b.nama)));
      } catch {
        setCandidates([]);
      }
    };

    loadCandidates();
  }, [isOpen, isAdmin, user]);

  // Isi ulang form saat modal dibuka untuk edit/tambah.
  useEffect(() => {
    if (!isOpen) return;

    if (editing) {
      setFormData({
        nama: editing.nama,
        jenis: editing.jenis,
        team_id: String(editing.team_id),
        mulai: editing.mulai,
        selesai: editing.selesai,
        bobot: String(editing.bobot),
        kegiatan_besar: editing.kegiatan_besar,
        keterangan: editing.keterangan ?? '',
      });
      setPjId('');
      setMemberIds([]);
      setRows([]);
    } else {
      setFormData({
        ...emptyForm,
        team_id: selectableTeams[0]?.id ? String(selectableTeams[0].id) : '',
      });
      setPjId('');
      setMemberIds([]);
      setRows([]);
    }

    setMemberSearch('');
    setSearchFocused(false);
    setShowPeriodAdvanced(false);
    setFormErrors([]);
    setSubmitting(false);
    setPreview(null);
    setPreviewError('');
    setPreviewLoading(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, editing]);

  // Sinkronkan baris penugasan dengan PJ + pegawai terlibat.
  useEffect(() => {
    if (isEditing) return;

    setRows((current) => {
      const wanted = [
        ...(pjId ? [Number(pjId)] : []),
        ...memberIds,
      ];

      const byUser = new Map(current.map((row) => [row.user_id, row]));
      const next: AssignmentRow[] = [];

      wanted.forEach((id) => {
        const existing = byUser.get(id);
        next.push(existing ?? buatBaris(id));
      });

      return next;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pjId, memberIds, isEditing]);

  const visibleCandidates = useMemo(() => {
    const keyword = memberSearch.trim().toLowerCase();
    return candidates.filter(
      (candidate) =>
        String(candidate.id) !== pjId &&
        !memberIds.includes(candidate.id) &&
        (!keyword ||
          candidate.nama.toLowerCase().includes(keyword) ||
          (candidate.nip ?? '').toLowerCase().includes(keyword))
    );
  }, [candidates, memberSearch, pjId, memberIds]);

  const selectedMemberUsers = useMemo(
    () => candidates.filter((candidate) => memberIds.includes(candidate.id)),
    [candidates, memberIds]
  );

  const weightedPoin = (row: AssignmentRow): number => {
    const volume = Number(row.volume);
    const bobot = Number(formData.bobot);

    if (!Number.isFinite(volume) || !Number.isFinite(bobot)) {
      return 0;
    }

    return Math.round(volume * bobot * 100) / 100;
  };

  const addMember = (userId: number) => {
    setMemberIds((current) => (current.includes(userId) ? current : [...current, userId]));
    setMemberSearch('');
  };

  const removeMember = (userId: number) => {
    setMemberIds((current) => current.filter((id) => id !== userId));
  };

  const updateRow = (key: string, patch: Partial<AssignmentRow>) => {
    setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
  };

  const applyFirstRowToAll = () => {
    setRows((current) => {
      if (current.length < 2) return current;
      const source = current[0];

      return current.map((row, index) =>
        index === 0
          ? row
          : { ...row, volume: source.volume, satuan: source.satuan }
      );
    });
  };

  const basicValidForPreview =
    Boolean(formData.team_id) &&
    Boolean(formData.mulai) &&
    Number.isFinite(Number(formData.bobot)) &&
    Number(formData.bobot) >= 0.5 &&
    Number(formData.bobot) <= 3 &&
    rows.length > 0 &&
    rows.some((row) => Number(row.volume) > 0);

  // Baris PJ ditampilkan sebagai "Penanggung Jawab" tanpa mengubah peran default baris.
  // Peran efektif inilah yang harus dikirim ke backend, bukan row.peran mentah.
  const effectivePeran = (row: AssignmentRow) =>
    row.user_id === Number(pjId)
      ? PJ_ROLE
      : row.peran === 'Lainnya'
        ? row.peranLainnya.trim()
        : row.peran;

  const previewPayload = () => ({
    team_id: Number(formData.team_id),
    mulai: formData.mulai,
    selesai: formData.selesai || formData.mulai,
    bobot: Number(formData.bobot),
    assignments: rows
      .filter((row) => row.volume.trim() !== '' && Number(row.volume) > 0)
      .map((row) => ({
        user_id: row.user_id,
        peran: effectivePeran(row),
        volume: Number(row.volume),
        satuan: row.satuan,
        mulai: row.mulai || undefined,
        selesai: row.selesai || undefined,
      })),
  });

  // Debounce pratinjau batch saat isian yang relevan berubah.
  useEffect(() => {
    if (isEditing) return undefined;
    if (!basicValidForPreview) {
      setPreview(null);
      setPreviewError('');
      return undefined;
    }

    const timer = window.setTimeout(async () => {
      setPreviewLoading(true);
      setPreviewError('');

      try {
        const res = await api.post('/assignments/preview-batch', previewPayload());
        setPreview(res.data?.data ?? null);
      } catch (err: unknown) {
        setPreview(null);
        setPreviewError(getApiErrorMessage(err, 'Gagal menghitung pratinjau beban kerja.'));
      } finally {
        setPreviewLoading(false);
      }
    }, 500);

    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    isEditing,
    formData.team_id,
    formData.mulai,
    formData.selesai,
    formData.bobot,
    rows,
    basicValidForPreview,
  ]);

  // Pastikan pesan kesalahan terlihat, walau pengguna sedang berada di bagian bawah modal.
  useEffect(() => {
    if (formErrors.length > 0) {
      modalRef.current?.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }, [formErrors]);

  const previewByUser = useMemo(() => {
    const map = new Map<number, PreviewBatchPegawaiResult>();
    (preview?.rows ?? []).forEach((row) => map.set(row.user_id, row));
    return map;
  }, [preview]);

  const previewWarnings = useMemo(
    () => (preview?.rows ?? []).flatMap((row) => row.peringatan),
    [preview]
  );

  const indicatorFor = (row: AssignmentRow): string | null => {
    const result = previewByUser.get(row.user_id);
    if (!result || result.dampak_bulanan.length === 0) return null;

    const order: Record<string, number> = { aman: 0, hampir_penuh: 1, melebihi: 2 };
    const worst = result.dampak_bulanan.reduce(
      (acc, impact) =>
        (order[impact.indikator_sesudah] ?? 0) > (order[acc] ?? 0) ? impact.indikator_sesudah : acc,
      'aman' as string
    );

    return worst;
  };

  const validate = (): string[] => {
    const errors: string[] = [];

    if (!formData.nama.trim()) errors.push('Nama kegiatan wajib diisi.');
    if (!formData.team_id) errors.push('Tim penanggung jawab wajib dipilih.');
    if (!formData.mulai) errors.push('Tanggal mulai kegiatan wajib diisi.');

    const bobot = Number(formData.bobot);
    if (!Number.isFinite(bobot) || bobot < 0.5 || bobot > 3) {
      errors.push('Bobot harus berada antara 0,50 dan 3,00.');
    }

    if (formData.mulai && formData.selesai && formData.selesai < formData.mulai) {
      errors.push('Tanggal selesai tidak boleh sebelum tanggal mulai.');
    }

    if (isEditing) {
      return errors;
    }

    if (!pjId) {
      errors.push('Penanggung Jawab wajib dipilih.');
    }

    const activityStart = formData.mulai;
    const activityEnd = formData.selesai || formData.mulai;

    rows.forEach((row) => {
      const label = candidates.find((c) => c.id === row.user_id)?.nama ?? `Pegawai #${row.user_id}`;

      if (row.volume.trim() === '' || !(Number(row.volume) > 0)) {
        errors.push(`Volume untuk ${label} wajib diisi dan lebih dari 0.`);
      }

      if (!row.satuan) {
        errors.push(`Satuan untuk ${label} wajib dipilih.`);
      }

      if (row.peran === 'Lainnya' && !row.peranLainnya.trim()) {
        errors.push(`Peran "${label}" wajib diisi karena memilih Lainnya.`);
      }

      if (row.mulai && activityStart && row.mulai < activityStart) {
        errors.push(`Tanggal mulai penugasan ${label} berada sebelum periode kegiatan.`);
      }

      if (row.selesai && activityEnd && row.selesai > activityEnd) {
        errors.push(`Tanggal selesai penugasan ${label} berada setelah periode kegiatan.`);
      }

      if (row.mulai && row.selesai && row.selesai < row.mulai) {
        errors.push(`Tanggal selesai penugasan ${label} tidak boleh sebelum tanggal mulai.`);
      }
    });

    return errors;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormErrors([]);

    const errors = validate();

    if (errors.length > 0) {
      setFormErrors(errors);
      return;
    }

    setSubmitting(true);

    const basePayload = {
      nama: formData.nama.trim(),
      jenis: formData.jenis,
      team_id: Number(formData.team_id),
      mulai: formData.mulai,
      // Selesai boleh dikosongkan; backend menyamakannya dengan mulai.
      ...(formData.selesai ? { selesai: formData.selesai } : {}),
      bobot: Number(formData.bobot),
      kegiatan_besar: formData.kegiatan_besar,
      keterangan: formData.keterangan.trim() || null,
    };

    try {
      if (editing) {
        await api.put(`/activities/${editing.id}`, basePayload);
      } else {
        const assignments = rows.map((row) => ({
          user_id: row.user_id,
          peran: effectivePeran(row),
          volume: Number(row.volume),
          satuan: row.satuan,
          ...(row.mulai ? { mulai: row.mulai } : {}),
          ...(row.selesai ? { selesai: row.selesai } : {}),
        }));

        await api.post('/activities', { ...basePayload, assignments });
      }

      onSaved();
      onClose();
    } catch (err: unknown) {
      setFormErrors([getApiErrorMessage(err, 'Gagal menyimpan kegiatan.')]);
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  const title = isEditing ? 'Edit Kegiatan Statistik' : 'Tambah Kegiatan Statistik Baru';

  return (
    <div className="fixed inset-0 bg-bps/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
      <div
        ref={modalRef}
        className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-4xl w-full p-5 sm:p-6 max-h-[92vh] overflow-y-auto"
      >
        <h2 className="text-base sm:text-lg font-bold text-slate-900 mb-4">{title}</h2>

        <form onSubmit={handleSubmit} noValidate className="space-y-5">
          {formErrors.length > 0 && (
            <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700 space-y-1">
              {formErrors.map((message, index) => (
                <p key={index} className="flex items-start gap-2">
                  <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                  <span>{message}</span>
                </p>
              ))}
            </div>
          )}

          {/* Data kegiatan */}
          <fieldset className="space-y-4">
            <div>
              <label htmlFor="activity-name" className="block text-xs font-semibold text-slate-700 mb-1">
                Nama Kegiatan
              </label>
              <input
                id="activity-name"
                type="text"
                required
                placeholder="Misal: Survei Biaya Hidup (SBH) 2026"
                value={formData.nama}
                onChange={(e) => setFormData({ ...formData, nama: e.target.value })}
                className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label htmlFor="activity-team" className="block text-xs font-semibold text-slate-700 mb-1">
                  Tim Penanggung Jawab
                </label>
                <select
                  id="activity-team"
                  required
                  value={formData.team_id}
                  onChange={(e) => setFormData({ ...formData, team_id: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                >
                  <option value="">Pilih Tim</option>
                  {selectableTeams.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.nama}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label htmlFor="activity-jenis" className="block text-xs font-semibold text-slate-700 mb-1">
                  Jenis Kegiatan
                </label>
                <select
                  id="activity-jenis"
                  value={formData.jenis}
                  onChange={(e) => setFormData({ ...formData, jenis: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                >
                  {JENIS_OPTIONS.map((jenis) => (
                    <option key={jenis} value={jenis}>
                      {jenis}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label htmlFor="activity-start" className="block text-xs font-semibold text-slate-700 mb-1">
                  Tanggal Mulai
                </label>
                <input
                  id="activity-start"
                  type="date"
                  required
                  value={formData.mulai}
                  onChange={(e) => setFormData({ ...formData, mulai: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                />
              </div>

              <div>
                <label htmlFor="activity-end" className="block text-xs font-semibold text-slate-700 mb-1">
                  Tanggal Selesai (kosong = sama dengan mulai)
                </label>
                <input
                  id="activity-end"
                  type="date"
                  value={formData.selesai}
                  min={formData.mulai || undefined}
                  onChange={(e) => setFormData({ ...formData, selesai: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
              <div>
                <label htmlFor="activity-bobot" className="block text-xs font-semibold text-slate-700 mb-1">
                  Bobot Kesulitan (0,50 - 3,00)
                </label>
                <input
                  id="activity-bobot"
                  type="number"
                  step="0.05"
                  min="0.5"
                  max="3"
                  required
                  value={formData.bobot}
                  onChange={(e) => setFormData({ ...formData, bobot: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                />
                <p className="mt-1 text-[11px] text-slate-500">
                  1,00 = pekerjaan standar, di atas 1 lebih berat, di bawah 1 lebih ringan
                </p>
              </div>

              <div className="pt-2 sm:pt-4 flex items-center gap-2">
                <input
                  type="checkbox"
                  id="activity-big"
                  checked={formData.kegiatan_besar}
                  onChange={(e) => setFormData({ ...formData, kegiatan_besar: e.target.checked })}
                  className="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
                />
                <label htmlFor="activity-big" className="text-xs font-medium text-slate-700 cursor-pointer">
                  Termasuk Kegiatan Besar
                </label>
              </div>
            </div>

            <div>
              <label htmlFor="activity-note" className="block text-xs font-semibold text-slate-700 mb-1">
                Keterangan Tambahan
              </label>
              <textarea
                id="activity-note"
                rows={2}
                placeholder="Catatan ringkas kegiatan..."
                value={formData.keterangan}
                onChange={(e) => setFormData({ ...formData, keterangan: e.target.value })}
                className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
              />
            </div>
          </fieldset>

          {!isEditing && (
            <>
              {/* Penanggung Jawab */}
              <fieldset className="space-y-3 border-t border-slate-100 pt-4">
                <div>
                  <label htmlFor="activity-pj" className="block text-xs font-semibold text-slate-700 mb-1">
                    Penanggung Jawab <span className="text-rose-500">*</span>
                  </label>
                  <select
                    id="activity-pj"
                    required
                    value={pjId}
                    onChange={(e) => setPjId(e.target.value)}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  >
                    <option value="">Pilih Penanggung Jawab</option>
                    {candidates.map((candidate) => (
                      <option key={candidate.id} value={candidate.id}>
                        {candidate.nama}
                      </option>
                    ))}
                  </select>
                  <p className="mt-1 text-[11px] text-slate-500">
                    Penanggung Jawab menjadi penugasan pertama dengan peran "{PJ_ROLE}" dan wajib diisi.
                  </p>
                </div>
              </fieldset>

              {/* Pegawai Terlibat */}
              <fieldset className="space-y-3 border-t border-slate-100 pt-4">
                <div>
                  <label htmlFor="activity-member-search" className="block text-xs font-semibold text-slate-700 mb-1">
                    Pegawai Terlibat (opsional)
                  </label>
                  <div className="relative">
                    <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                    <input
                      id="activity-member-search"
                      type="text"
                      placeholder="Cari dan pilih pegawai..."
                      value={memberSearch}
                      onChange={(e) => setMemberSearch(e.target.value)}
                      onFocus={() => setSearchFocused(true)}
                      onBlur={() => setSearchFocused(false)}
                      className="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                    />
                  </div>

                  {(searchFocused || memberSearch.trim() !== '') && visibleCandidates.length > 0 && (
                    <div className="mt-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                      {visibleCandidates.map((candidate) => (
                        <button
                          key={candidate.id}
                          type="button"
                          // Cegah input kehilangan fokus sebelum onClick sempat berjalan.
                          onMouseDown={(event) => event.preventDefault()}
                          onClick={() => addMember(candidate.id)}
                          className="block w-full text-left px-3 py-2 text-xs text-slate-700 hover:bg-slate-50"
                        >
                          <span className="font-medium text-slate-900">{candidate.nama}</span>
                          {candidate.nip && (
                            <span className="ml-2 text-slate-400">({candidate.nip})</span>
                          )}
                        </button>
                      ))}
                    </div>
                  )}

                  {selectedMemberUsers.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {selectedMemberUsers.map((member) => (
                        <span
                          key={member.id}
                          className="inline-flex items-center gap-1 rounded-full bg-sky-50 text-sky-800 border border-sky-200 px-2.5 py-1 text-xs font-medium"
                        >
                          {member.nama}
                          <button
                            type="button"
                            onClick={() => removeMember(member.id)}
                            aria-label={`Hapus ${member.nama}`}
                            className="rounded-full hover:bg-sky-100 p-0.5"
                          >
                            <X className="w-3.5 h-3.5" />
                          </button>
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              </fieldset>

              {/* Tabel penugasan */}
              <fieldset className="space-y-3 border-t border-slate-100 pt-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div>
                    <h3 className="text-xs font-bold text-slate-800">Detail Penugasan per Pegawai</h3>
                    <p className="text-[11px] text-slate-500 mt-0.5">
                      Volume = jumlah pekerjaan, Satuan = jenis pekerjaan, Poin = volume x bobot
                    </p>
                    <p className="text-[11px] text-slate-500">1,00 = pekerjaan standar</p>
                  </div>
                  <button
                    type="button"
                    onClick={applyFirstRowToAll}
                    disabled={rows.length < 2}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 min-h-[36px]"
                  >
                    <Copy className="w-3.5 h-3.5" />
                    Terapkan volume dan satuan ke semua
                  </button>
                </div>

                <label className="flex items-center gap-2 text-[11px] text-slate-600">
                  <input
                    type="checkbox"
                    checked={showPeriodAdvanced}
                    onChange={(e) => setShowPeriodAdvanced(e.target.checked)}
                    className="w-3.5 h-3.5 text-sky-600 rounded border-slate-300"
                  />
                  Atur periode khusus (opsional)
                </label>

                {rows.length === 0 ? (
                  <p className="text-center text-xs text-slate-400 py-4">
                    Pilih Penanggung Jawab (dan pegawai terlibat) untuk mengisi detail penugasan.
                  </p>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-[11px] min-w-[720px]">
                      <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                        <tr>
                          <th className="py-2 px-2">Pegawai</th>
                          <th className="py-2 px-2">Peran</th>
                          <th className="py-2 px-2 w-24">Volume *</th>
                          <th className="py-2 px-2">Satuan *</th>
                          <th className="py-2 px-2 w-16">Poin</th>
                          <th className="py-2 px-2">Indikator</th>
                          {showPeriodAdvanced && <th className="py-2 px-2">Periode</th>}
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {rows.map((row) => {
                          const candidate = candidates.find((item) => item.id === row.user_id);
                          const isPj = row.user_id === Number(pjId);
                          const indicator = indicatorFor(row);

                          return (
                            <tr key={row.key}>
                              <td className="py-2 px-2">
                                <span className="font-medium text-slate-800">{candidate?.nama}</span>
                                {isPj && (
                                  <span className="ml-1.5 inline-block rounded bg-sky-100 text-sky-800 text-[9px] font-bold px-1.5 py-0.5 align-middle">
                                    PJ
                                  </span>
                                )}
                              </td>
                              <td className="py-2 px-2">
                                {isPj ? (
                                  <span className="text-[11px] font-semibold text-slate-700">
                                    {PJ_ROLE}
                                  </span>
                                ) : (
                                  <div className="space-y-1">
                                    <select
                                      aria-label={`Peran ${candidate?.nama}`}
                                      value={row.peran}
                                      onChange={(e) => updateRow(row.key, { peran: e.target.value })}
                                      className="w-full text-[11px] border border-slate-200 rounded px-2 py-1.5"
                                    >
                                      {ROLE_OPTIONS.map((role) => (
                                        <option key={role} value={role}>
                                          {role}
                                        </option>
                                      ))}
                                    </select>
                                    {row.peran === 'Lainnya' && (
                                      <input
                                        type="text"
                                        placeholder="Isi peran..."
                                        value={row.peranLainnya}
                                        onChange={(e) => updateRow(row.key, { peranLainnya: e.target.value })}
                                        className="w-full text-[11px] border border-slate-200 rounded px-2 py-1.5"
                                      />
                                    )}
                                  </div>
                                )}
                              </td>
                              <td className="py-2 px-2">
                                <input
                                  type="number"
                                  min="0.01"
                                  step="any"
                                  aria-label={`Volume ${candidate?.nama}`}
                                  value={row.volume}
                                  onChange={(e) => updateRow(row.key, { volume: e.target.value })}
                                  className="w-20 text-[11px] border border-slate-200 rounded px-2 py-1.5"
                                  placeholder="wajib"
                                />
                              </td>
                              <td className="py-2 px-2">
                                <select
                                  aria-label={`Satuan ${candidate?.nama}`}
                                  value={row.satuan}
                                  onChange={(e) => updateRow(row.key, { satuan: e.target.value })}
                                  className="text-[11px] border border-slate-200 rounded px-2 py-1.5"
                                >
                                  {SATUAN_OPTIONS.map((satuan) => (
                                    <option key={satuan} value={satuan}>
                                      {satuan}
                                    </option>
                                  ))}
                                </select>
                              </td>
                              <td className="py-2 px-2 font-semibold text-slate-700">
                                {weightedPoin(row).toFixed(2)}
                              </td>
                              <td className="py-2 px-2">
                                {indicator ? (
                                  <span
                                    className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                                      indicatorClasses[indicator] ?? ''
                                    }`}
                                  >
                                    {indicatorLabels[indicator] ?? indicator}
                                  </span>
                                ) : (
                                  <span className="text-slate-400">–</span>
                                )}
                              </td>
                              {showPeriodAdvanced && (
                                <td className="py-2 px-2">
                                  <div className="flex gap-1.5">
                                    <input
                                      type="date"
                                      aria-label={`Mulai ${candidate?.nama}`}
                                      value={row.mulai}
                                      onChange={(e) => updateRow(row.key, { mulai: e.target.value })}
                                      className="w-28 text-[11px] border border-slate-200 rounded px-1.5 py-1.5"
                                    />
                                    <input
                                      type="date"
                                      aria-label={`Selesai ${candidate?.nama}`}
                                      value={row.selesai}
                                      min={row.mulai || formData.mulai || undefined}
                                      onChange={(e) => updateRow(row.key, { selesai: e.target.value })}
                                      className="w-28 text-[11px] border border-slate-200 rounded px-1.5 py-1.5"
                                    />
                                  </div>
                                </td>
                              )}
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                )}
              </fieldset>

              {/* Pratinjau beban batch */}
              <fieldset className="space-y-2 border-t border-slate-100 pt-4">
                <div className="flex items-center justify-between gap-2">
                  <h3 className="text-xs font-bold text-slate-800">Pratinjau Beban Kerja</h3>
                  {previewLoading && <Loader2 className="w-4 h-4 animate-spin text-sky-600" />}
                </div>

                {previewError && (
                  <p role="alert" className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                    <span>{previewError}</span>
                  </p>
                )}

                {preview === null && !previewLoading && !previewError && (
                  <div className="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
                    <Info className="mt-0.5 w-3.5 h-3.5 shrink-0 text-slate-400" />
                    <span>
                      Isi minimal satu volume di atas; pratinjau dihitung otomatis sebelum menyimpan.
                    </span>
                  </div>
                )}

                {preview?.rows?.map((pegawai) => (
                  <div key={pegawai.user_id} className="rounded-lg border border-slate-200 p-2.5 text-[11px] space-y-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <span className="font-semibold text-slate-800">{pegawai.pegawai.nama}</span>
                      {pegawai.dampak_bulanan.map((impact) => (
                        <span key={impact.bulan} className="text-slate-600">
                          {impact.bulan}: {impact.beban_sebelum.toFixed(2)} →{' '}
                          <span className="font-semibold text-slate-900">
                            {impact.beban_sesudah.toFixed(2)} pt
                          </span>
                        </span>
                      ))}
                    </div>
                    {pegawai.peringatan.map((warning, index) => (
                      <p key={index} className="flex items-start gap-1.5 text-rose-700 font-medium">
                        <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                        {warning.pesan}
                      </p>
                    ))}
                  </div>
                ))}

                {previewWarnings.length === 0 && preview !== null && (
                  <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] text-emerald-800 font-medium">
                    Tidak ada peringatan beban atau penugasan bersamaan untuk kombinasi ini.
                  </div>
                )}
              </fieldset>
            </>
          )}

          {/* Aksi */}
          <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition min-h-[44px]"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition min-h-[44px]"
            >
              {submitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Tambah Kegiatan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
