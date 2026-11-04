import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import { useSearchParams } from 'react-router-dom';
import {
  Plus,
  Search,
  CheckCircle,
  RotateCcw,
  Loader2,
  Trash2,
  Edit,
  UserCheck,
  AlertTriangle,
  X,
} from 'lucide-react';
import { Activity, Team, User, Assignment, AssignmentPreviewResult } from '../types';
import { getApiErrorMessage } from '../services/errors';
import { ActivityFormModal } from './ActivityFormModal';

const INDICATOR_LABELS: Record<string, string> = {
  aman: 'Aman',
  hampir_penuh: 'Hampir Penuh',
  melebihi: 'Melebihi',
};

const ASSIGNMENT_ROLE_OPTIONS = ['Anggota', 'Pengolah Data', 'Pengawas', 'Lainnya'] as const;
const PJ_ROLE = 'Penanggung Jawab';

export const ActivitiesPage: React.FC = () => {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const idParam = searchParams.get('id');
  const [activities, setActivities] = useState<Activity[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [assignmentCandidates, setAssignmentCandidates] = useState<User[]>([]);  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedTeam, setSelectedTeam] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('');

  // Modal Tambah/Edit Kegiatan (komponen ActivityFormModal)
  const [showActivityForm, setShowActivityForm] = useState(false);
  const [editingActivity, setEditingActivity] = useState<Activity | null>(null);
  const [assignmentError, setAssignmentError] = useState('');
  const [previewError, setPreviewError] = useState('');
  const [pageError, setPageError] = useState('');

  // Modal Detail & Manajemen Penugasan
  const [selectedActivity, setSelectedActivity] = useState<Activity | null>(null);
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [assignmentLoading, setAssignmentLoading] = useState(false);
  const [showAssignmentModal, setShowAssignmentModal] = useState(false);
  const [completingActivity, setCompletingActivity] = useState<Activity | null>(null);
  const [editingAssignment, setEditingAssignment] = useState<Assignment | null>(null);

  // Form Penugasan + Preview State
  const [assignmentForm, setAssignmentForm] = useState({
    user_id: '',
    peran: 'Anggota',
    peranLainnya: '',
    volume: '1',
    satuan: 'Dokumen',
    mulai: '',
    selesai: '',
  });
  const [previewResult, setPreviewResult] = useState<AssignmentPreviewResult | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);

  const fetchActivities = async () => {
    setLoading(true);
    try {
      const res = await api.get('/activities', {
        params: {
          search: search || undefined,
          team_id: selectedTeam || undefined,
          status: selectedStatus || undefined,
        },
      });
      setActivities(res.data.data || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const fetchTeams = async () => {
    try {
      const res = await api.get('/teams');
      setTeams(res.data.data || []);
    } catch (err) {
      console.error(err);
    }
  };

  // Buka detail/penugasan kegiatan dari parameter ?id= (dipakai tautan dari kalender)
  const openDetailById = async (id: number) => {
    setPageError('');
    try {
      const res = await api.get(`/activities/${id}`);
      const data = res.data?.data ?? null;

      if (data) {
        await handleOpenAssignments(data);
      } else {
        setPageError('Kegiatan tidak ditemukan.');
      }
    } catch (err: unknown) {
      setPageError(getApiErrorMessage(err, 'Kegiatan tidak ditemukan atau Anda tidak berwenang melihatnya.'));
    }
  };

  const closeDetailModal = () => {
    setSelectedActivity(null);

    if (searchParams.get('id')) {
      const next = new URLSearchParams(searchParams);
      next.delete('id');
      setSearchParams(next, { replace: true });
    }
  };

  useEffect(() => {
    fetchTeams();
  }, []);

  useEffect(() => {
    fetchActivities();
  }, [search, selectedTeam, selectedStatus]);

  // Buka detail kegiatan otomatis bila ada parameter ?id= di URL.
  useEffect(() => {
    if (!idParam) return;

    const id = Number(idParam);

    if (Number.isInteger(id) && id > 0) {
      openDetailById(id);
    } else {
      setPageError('Parameter ID kegiatan tidak valid.');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idParam]);

  // Hak akses tambah kegiatan: admin atau ketua tim.
  const canCreate = user?.peran === 'admin' || Boolean(user?.is_ketua);

  // Peran PJ tidak diubah dari form ini agar kegiatan tetap memiliki tepat satu PJ.
  const editingIsPj = editingAssignment?.peran === PJ_ROLE;
  const assignmentRoleOptions: string[] = editingIsPj ? [PJ_ROLE] : [...ASSIGNMENT_ROLE_OPTIONS];

  // Sembunyikan pegawai yang sudah ditugaskan, kecuali pegawai pada penugasan yang sedang diedit.
  const selectableAssignmentCandidates = assignmentCandidates.filter(
    (candidate) =>
      candidate.id === Number(assignmentForm.user_id) ||
      !assignments.some((assignment) => assignment.user_id === candidate.id)
  );

  // Buka Form Tambah Kegiatan
  const handleOpenCreate = () => {
    setEditingActivity(null);
    setShowActivityForm(true);
  };

  // Buka Form Edit Kegiatan (form hanya mengubah data kegiatan; penugasan dikelola lewat modal penugasan)
  const handleOpenEdit = (act: Activity) => {
    setEditingActivity(act);
    setShowActivityForm(true);
  };

  const handleActivityFormSaved = () => {
    fetchActivities();
  };

  // Hapus Kegiatan
  const handleDeleteActivity = async (act: Activity) => {
    if (!confirm(`Yakin ingin menghapus kegiatan "${act.nama}"?`)) return;
    setPageError('');
    try {
      await api.delete(`/activities/${act.id}`);
      fetchActivities();
    } catch (err: unknown) {
      setPageError(getApiErrorMessage(err, 'Gagal menghapus kegiatan.'));
    }
  };

  // Toggle Selesaikan/Buka Kembali Kegiatan
  const handleToggleComplete = async (act: Activity) => {
    const isCompleted = act.status === 'Selesai';
    const endpoint = isCompleted ? `/activities/${act.id}/reopen` : `/activities/${act.id}/complete`;
    setPageError('');
    try {
      await api.patch(endpoint);
      fetchActivities();
    } catch (err: unknown) {
      setPageError(getApiErrorMessage(err, 'Gagal memperbarui status kegiatan.'));
    }
  };

  const handleConfirmComplete = async () => {
    if (!completingActivity) return;
    setPageError('');
    try {
      await api.patch(`/activities/${completingActivity.id}/complete`);
      setCompletingActivity(null);
      fetchActivities();
    } catch (err: unknown) {
      setPageError(getApiErrorMessage(err, 'Gagal memperbarui status kegiatan.'));
      setCompletingActivity(null);
    }
  };

  // Buka Detail Kegiatan & Daftar Penugasan
  const handleOpenAssignments = async (act: Activity) => {
    setSelectedActivity(act);
    setAssignmentLoading(true);
    try {
      const [asRes, memRes] = await Promise.all([
        api.get(`/activities/${act.id}/assignments`),
        api.get(`/activities/${act.id}/assignment-candidates`),
      ]);
      setAssignments(asRes.data.data || []);
      setAssignmentCandidates(memRes.data.data || []);
    } catch (err) {
      console.error(err);
    } finally {
      setAssignmentLoading(false);
    }
  };

  // Buka Form Penugasan Baru
  const handleOpenAddAssignment = () => {
    setEditingAssignment(null);
    setPreviewResult(null);
    setAssignmentError('');
    setPreviewError('');
    setAssignmentForm({
      user_id: '',
      peran: 'Anggota',
      peranLainnya: '',
      volume: '10',
      satuan: 'Dokumen',
      mulai: selectedActivity?.mulai || '',
      selesai: selectedActivity?.selesai || '',
    });
    setShowAssignmentModal(true);
  };

  // Buka form edit penugasan dengan nilai awal dari baris yang dipilih.
  const handleOpenEditAssignment = (assignment: Assignment) => {
    setEditingAssignment(assignment);
    setPreviewResult(null);
    setAssignmentError('');
    setPreviewError('');

    const peranTersedia = (ASSIGNMENT_ROLE_OPTIONS as readonly string[]).includes(assignment.peran);
    const peran = assignment.peran === PJ_ROLE
      ? PJ_ROLE
      : peranTersedia
        ? assignment.peran
        : 'Lainnya';

    setAssignmentForm({
      user_id: String(assignment.user_id),
      peran,
      peranLainnya: peran === 'Lainnya' ? assignment.peran : '',
      volume: String(assignment.volume ?? 0),
      satuan: assignment.satuan ?? 'Dokumen',
      mulai: assignment.mulai ?? '',
      selesai: assignment.selesai ?? '',
    });
    setShowAssignmentModal(true);
  };

  // Tutup form penugasan dan bersihkan hasil pratinjau.
  const closeAssignmentForm = () => {
    setShowAssignmentModal(false);
    setPreviewResult(null);
    setPreviewError('');
    setAssignmentError('');
  };

  // Pratinjau dampak beban kerja diperbarui otomatis saat pegawai, volume, atau periode berubah.
  // Endpoint ini hanya menghitung (tanpa menulis ke database).
  useEffect(() => {
    if (!showAssignmentModal || !selectedActivity) return undefined;

    const userId = Number(assignmentForm.user_id);
    const volume = Number(assignmentForm.volume);

    if (!userId || !Number.isFinite(volume) || volume <= 0) {
      setPreviewResult(null);
      setPreviewError('');
      setPreviewLoading(false);
      return undefined;
    }

    const timer = window.setTimeout(async () => {
      setPreviewLoading(true);
      setPreviewError('');

      try {
        const res = await api.post('/assignments/preview', {
          activity_id: selectedActivity.id,
          user_id: userId,
          peran: assignmentForm.peran === 'Lainnya'
            ? assignmentForm.peranLainnya.trim() || 'Lainnya'
            : assignmentForm.peran,
          volume,
          satuan: assignmentForm.satuan,
          mulai: assignmentForm.mulai || undefined,
          selesai: assignmentForm.selesai || undefined,
          assignment_id: editingAssignment?.id,
        });
        setPreviewResult(res.data?.data ?? null);
      } catch (err: unknown) {
        setPreviewResult(null);
        setPreviewError(getApiErrorMessage(err, 'Gagal menghitung pratinjau beban kerja.'));
      } finally {
        setPreviewLoading(false);
      }
    }, 500);

    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    showAssignmentModal,
    selectedActivity,
    editingAssignment,
    assignmentForm.user_id,
    assignmentForm.peran,
    assignmentForm.peranLainnya,
    assignmentForm.volume,
    assignmentForm.satuan,
    assignmentForm.mulai,
    assignmentForm.selesai,
  ]);

  // Simpan Penugasan Pegawai (tambah atau edit tanpa membuat baris duplikat)
  const handleSaveAssignment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedActivity) return;
    setAssignmentError('');

    const userId = Number(assignmentForm.user_id);
    const volume = Number(assignmentForm.volume);
    const peran = assignmentForm.peran === 'Lainnya'
      ? assignmentForm.peranLainnya.trim()
      : assignmentForm.peran;
    const errors: string[] = [];

    if (!userId) errors.push('Pegawai yang ditugaskan wajib dipilih.');
    if (!peran) errors.push('Peran penugasan wajib diisi.');
    if (!Number.isFinite(volume) || volume < 0.01) errors.push('Volume penugasan minimal 0.01.');
    if (!assignmentForm.satuan) errors.push('Satuan penugasan wajib dipilih.');

    if (assignmentForm.mulai && assignmentForm.mulai < selectedActivity.mulai) {
      errors.push('Tanggal mulai penugasan harus berada dalam periode kegiatan.');
    }
    if (assignmentForm.selesai && assignmentForm.selesai > selectedActivity.selesai) {
      errors.push('Tanggal selesai penugasan harus berada dalam periode kegiatan.');
    }
    if (assignmentForm.mulai && assignmentForm.selesai && assignmentForm.selesai < assignmentForm.mulai) {
      errors.push('Tanggal selesai penugasan tidak boleh sebelum tanggal mulai.');
    }

    const duplikat = assignments.some(
      (assignment) => assignment.user_id === userId && assignment.id !== editingAssignment?.id
    );
    if (duplikat) errors.push('Pegawai ini sudah ditugaskan pada kegiatan tersebut.');

    const pjLain = assignments.some(
      (assignment) => assignment.peran === PJ_ROLE && assignment.id !== editingAssignment?.id
    );
    if (peran === PJ_ROLE && pjLain) errors.push('Kegiatan hanya boleh memiliki satu Penanggung Jawab.');
    if (editingAssignment?.peran === PJ_ROLE && peran !== PJ_ROLE) {
      errors.push('Kegiatan wajib memiliki satu Penanggung Jawab.');
    }

    if (errors.length > 0) {
      setAssignmentError(errors.join(' '));
      return;
    }

    try {
      const payload = {
        activity_id: selectedActivity.id,
        user_id: userId,
        peran,
        volume,
        satuan: assignmentForm.satuan,
        mulai: assignmentForm.mulai || undefined,
        selesai: assignmentForm.selesai || undefined,
      };

      if (editingAssignment) {
        await api.put(`/assignments/${editingAssignment.id}`, payload);
      } else {
        await api.post(`/activities/${selectedActivity.id}/assignments`, payload);
      }

      setShowAssignmentModal(false);
      setPreviewResult(null);
      setPreviewError('');
      handleOpenAssignments(selectedActivity);
      fetchActivities();
    } catch (err: unknown) {
      setAssignmentError(getApiErrorMessage(err, 'Gagal menyimpan penugasan pegawai.'));
    }
  };

  // Hapus Penugasan
  const handleDeleteAssignment = async (as: Assignment) => {
    if (!confirm(`Hapus penugasan untuk ${as.user?.nama}?`)) return;
    setAssignmentError('');
    try {
      await api.delete(`/assignments/${as.id}`);
      if (selectedActivity) handleOpenAssignments(selectedActivity);
      fetchActivities();
    } catch (err: unknown) {
      setAssignmentError(getApiErrorMessage(err, 'Gagal menghapus penugasan.'));
    }
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Header & Aksi Tambah */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-lg sm:text-xl font-bold text-slate-900">Daftar Kegiatan Statistik</h1>
          <p className="text-xs sm:text-sm text-slate-500">
            Kelola kegiatan survei, sensus, penugasan petugas, dan monitoring jadwal BPS
          </p>
        </div>
        {canCreate && (
          <button
            onClick={handleOpenCreate}
            className="flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-xs transition min-h-[44px]"
          >
            <Plus className="w-4 h-4" />
            Tambah Kegiatan Baru
          </button>
        )}
      </div>

      {pageError && (
        <div className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <AlertTriangle className="mt-0.5 w-4 h-4 shrink-0" />
          <span>{pageError}</span>
        </div>
      )}

      {/* Filter and Search */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col md:flex-row gap-3">
        <div className="flex-1 relative">
          <Search className="w-4 h-4 absolute left-3 top-3.5 text-slate-400" />
          <input
            type="text"
            placeholder="Cari nama kegiatan..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-sky-600 min-h-[40px]"
          />
        </div>

        <select
          value={selectedTeam}
          onChange={(e) => setSelectedTeam(e.target.value)}
          className="text-xs sm:text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-hidden focus:ring-2 focus:ring-sky-600 min-h-[40px]"
        >
          <option value="">Semua Tim Kerja</option>
          {teams.map((t) => (
            <option key={t.id} value={t.id}>{t.nama}</option>
          ))}
        </select>

        <select
          value={selectedStatus}
          onChange={(e) => setSelectedStatus(e.target.value)}
          className="text-xs sm:text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-hidden focus:ring-2 focus:ring-sky-600 min-h-[40px]"
        >
          <option value="">Semua Status</option>
          <option value="Belum Mulai">Belum Mulai</option>
          <option value="Berjalan">Berjalan</option>
          <option value="Selesai">Selesai</option>
          <option value="Terlambat">Terlambat</option>
        </select>
      </div>

      {/* Tabel Desktop & Card Stack Mobile */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="py-16 flex justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
          </div>
        ) : activities.length === 0 ? (
          <div className="py-16 text-center text-slate-500 text-sm">
            Tidak ada kegiatan statistik yang ditemukan.
          </div>
        ) : (
          <>
            {/* Desktop & Tablet Table */}
            <div className="hidden sm:block overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                  <tr>
                    <th className="py-3 px-4">Nama Kegiatan</th>
                    <th className="py-3 px-4">Tim Penanggung Jawab</th>
                    <th className="py-3 px-4">Jenis</th>
                    <th className="py-3 px-4">Periode</th>
                    <th className="py-3 px-4">Bobot</th>
                    <th className="py-3 px-4">Petugas</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {activities.map((act) => (
                    <tr key={act.id} className="hover:bg-slate-50/70 transition">
                      <td className="py-3 px-4 font-medium text-slate-900">
                        <div>
                          {act.nama}
                          {act.kegiatan_besar && (
                            <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200 font-bold whitespace-nowrap">
                              BESAR
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="py-3 px-4 text-slate-600 text-xs">{act.team?.nama || '-'}</td>
                      <td className="py-3 px-4 text-slate-600 text-xs">{act.jenis}</td>
                      <td className="py-3 px-4 text-slate-500 text-xs whitespace-nowrap">
                        {act.mulai} s.d. {act.selesai}
                      </td>
                      <td className="py-3 px-4 font-semibold text-slate-700">{act.bobot}</td>
                      <td className="py-3 px-4 text-slate-600 whitespace-nowrap">
                        <button
                          onClick={() => handleOpenAssignments(act)}
                          className="text-xs font-semibold text-sky-600 hover:text-sky-800 underline underline-offset-2"
                        >
                          {act.assignments_count || 0} orang
                        </button>
                      </td>
                      <td className="py-3 px-4">
                        <span
                          className={`text-xs px-2.5 py-0.5 rounded-full font-medium whitespace-nowrap ${
                            act.status === 'Selesai'
                              ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                              : act.status === 'Berjalan'
                              ? 'bg-sky-50 text-sky-700 border border-sky-200'
                              : act.status === 'Terlambat'
                              ? 'bg-rose-50 text-rose-700 border border-rose-200'
                              : 'bg-slate-100 text-slate-600'
                          }`}
                        >
                          {act.status}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
                        <button
                          onClick={() => handleOpenAssignments(act)}
                          title="Kelola Penugasan"
                          className="p-1.5 text-slate-400 hover:text-sky-600 rounded transition"
                        >
                          <UserCheck className="w-4 h-4 inline" />
                        </button>
                        {canCreate && (
                          <>
                            <button
                              onClick={() => handleOpenEdit(act)}
                              title="Edit Kegiatan"
                              className="p-1.5 text-slate-400 hover:text-amber-600 rounded transition"
                            >
                              <Edit className="w-4 h-4 inline" />
                            </button>
                            <button
                              onClick={() => handleDeleteActivity(act)}
                              title="Hapus Kegiatan"
                              className="p-1.5 text-slate-400 hover:text-rose-600 rounded transition"
                            >
                              <Trash2 className="w-4 h-4 inline" />
                            </button>
                          </>
                        )}
                        {act.status === 'Selesai' ? (
                          <button
                            onClick={() => handleToggleComplete(act)}
                            className="p-1.5 text-amber-600 hover:text-amber-700 rounded transition"
                            title="Buka Kembali"
                          >
                            <RotateCcw className="w-4 h-4 inline" />
                          </button>
                        ) : (
                          <button
                            onClick={() => setCompletingActivity(act)}
                            className="p-1.5 text-emerald-600 hover:text-emerald-700 rounded transition"
                            title="Selesaikan Kegiatan"
                          >
                            <CheckCircle className="w-4 h-4 inline" />
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Mobile Card Stack (<640px) */}
            <div className="sm:hidden divide-y divide-slate-100">
              {activities.map((act) => (
                <div key={act.id} className="p-4 space-y-2.5">
                  <div className="flex justify-between items-start gap-2">
                    <div>
                      <h3 className="font-semibold text-slate-900 text-sm leading-snug">{act.nama}</h3>
                      <p className="text-xs text-slate-500 mt-0.5">{act.team?.nama} • {act.jenis}</p>
                    </div>
                    <span
                      className={`text-[10px] px-2 py-0.5 rounded-full font-bold shrink-0 ${
                        act.status === 'Selesai'
                          ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                          : act.status === 'Berjalan'
                          ? 'bg-sky-50 text-sky-700 border border-sky-200'
                          : 'bg-slate-100 text-slate-600'
                      }`}
                    >
                      {act.status}
                    </span>
                  </div>

                  <div className="flex items-center justify-between text-xs text-slate-600">
                    <span>Periode: {act.mulai} s.d. {act.selesai}</span>
                    <span>Petugas: <strong>{act.assignments_count || 0}</strong> org</span>
                  </div>

                  <div className="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button
                      onClick={() => handleOpenAssignments(act)}
                      className="text-xs font-semibold text-sky-600 hover:text-sky-700 flex items-center gap-1 min-h-[36px]"
                    >
                      <UserCheck className="w-3.5 h-3.5" /> Kelola Tugas
                    </button>
                    <div className="flex items-center gap-1">
                      {canCreate && (
                        <button
                          onClick={() => handleOpenEdit(act)}
                          className="p-2 text-slate-500 hover:text-amber-600 min-h-[36px] min-w-[36px]"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                      )}
                      <button
                        onClick={() => act.status === 'Selesai' ? handleToggleComplete(act) : setCompletingActivity(act)}
                        className={`p-2 min-h-[36px] min-w-[36px] ${act.status === 'Selesai' ? 'text-amber-600' : 'text-emerald-600'}`}
                      >
                        {act.status === 'Selesai' ? <RotateCcw className="w-4 h-4" /> : <CheckCircle className="w-4 h-4" />}
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
      </div>

      <ActivityFormModal
        isOpen={showActivityForm}
        onClose={() => setShowActivityForm(false)}
        onSaved={handleActivityFormSaved}
        teams={teams}
        editing={editingActivity}
      />

      {/* Modal Manajemen Penugasan Pegawai */}
      {selectedActivity && (
        <div className="fixed inset-0 bg-bps/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-3xl w-full p-5 sm:p-6 max-h-[88vh] flex flex-col">
            <div className="flex justify-between items-start mb-3 border-b border-slate-100 pb-3">
              <div>
                <h2 className="text-base sm:text-lg font-bold text-slate-900">
                  Penugasan Petugas: {selectedActivity.nama}
                </h2>
                <p className="text-xs text-slate-500">
                  Tim: {selectedActivity.team?.nama} • Periode: {selectedActivity.mulai} s.d. {selectedActivity.selesai} • Bobot: {selectedActivity.bobot}
                </p>
              </div>
              <button
                onClick={closeDetailModal}
                aria-label="Tutup modal penugasan"
                className="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Tombol Tambah Penugasan */}
            <div className="flex justify-between items-center py-2">
              <span className="text-xs font-bold text-slate-700">Daftar Petugas ({assignments.length})</span>
              <button
                onClick={handleOpenAddAssignment}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 rounded-lg text-xs font-semibold min-h-[36px]"
              >
                <Plus className="w-3.5 h-3.5" /> Tambah Petugas
              </button>
            </div>

            {assignmentError && (
              <p role="alert" className="mb-2 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                <span>{assignmentError}</span>
              </p>
            )}

            {/* List Penugasan */}
            <div className="flex-1 overflow-y-auto space-y-2 pr-1 my-2">
              {assignmentLoading ? (
                <div className="py-12 flex justify-center">
                  <Loader2 className="w-6 h-6 animate-spin text-sky-600" />
                </div>
              ) : assignments.length === 0 ? (
                <p className="text-center text-xs text-slate-400 py-8">Belum ada pegawai yang ditugaskan.</p>
              ) : (
                assignments.map((as) => (
                  <div key={as.id} className="p-3 border border-slate-100 rounded-lg bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <div>
                      <p className="font-bold text-slate-900">{as.user?.nama}</p>
                      {as.metrics_visible === false ? (
                        <p className="text-slate-500">
                          Peran: <strong className="text-slate-700">{as.peran}</strong>
                        </p>
                      ) : (
                        <p className="text-slate-500">
                          Peran: <strong className="text-slate-700">{as.peran}</strong> • Volume: {as.volume} {as.satuan} • Poin: <strong>{as.poin_total ?? 0} pt</strong>
                        </p>
                      )}
                      <p className="text-[11px] text-slate-400 font-mono mt-0.5">
                        {as.effective_mulai} s.d. {as.effective_selesai}
                      </p>
                    </div>
                    <div className="flex items-center gap-1 self-end sm:self-auto">
                      <button
                        onClick={() => handleOpenEditAssignment(as)}
                        className="p-1.5 text-slate-400 hover:text-sky-600 rounded transition min-h-[36px] min-w-[36px] flex items-center justify-center"
                        title="Edit Penugasan"
                        aria-label={`Edit penugasan ${as.user?.nama ?? ''}`}
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDeleteAssignment(as)}
                        className="p-1.5 text-slate-400 hover:text-rose-600 rounded transition min-h-[36px] min-w-[36px] flex items-center justify-center"
                        title="Hapus Penugasan"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>

            <div className="pt-3 border-t border-slate-100 text-right">
              <button
                onClick={closeDetailModal}
                className="px-4 py-2 text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg transition min-h-[44px]"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Form Tambah Penugasan + Preview Impact */}
      {showAssignmentModal && (
        <div className="fixed inset-0 bg-bps/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-lg w-full p-5 sm:p-6 max-h-[90vh] overflow-y-auto">
            <div className="flex items-start justify-between gap-3 mb-3">
              <h3 className="text-base font-bold text-slate-900">
                {editingAssignment ? 'Edit Penugasan Pegawai' : 'Tugaskan Pegawai ke Kegiatan'}
              </h3>
              <button
                type="button"
                onClick={closeAssignmentForm}
                aria-label="Tutup form penugasan"
                className="text-slate-400 hover:text-slate-600 p-1.5 -mr-1.5 -mt-1 flex items-center justify-center min-h-[36px] min-w-[36px]"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleSaveAssignment} noValidate className="space-y-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Pilih Pegawai</label>
                <select
                  required
                  value={assignmentForm.user_id}
                  onChange={(e) => setAssignmentForm({ ...assignmentForm, user_id: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                >
                  <option value="">Pilih Pegawai</option>
                  {selectableAssignmentCandidates.map((m) => (
                    <option key={m.id} value={m.id}>
                      {m.nama}{m.nip ? ` (${m.nip})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Peran Penugasan</label>
                  <select
                    required
                    value={assignmentForm.peran}
                    onChange={(e) => setAssignmentForm({ ...assignmentForm, peran: e.target.value })}
                    disabled={editingIsPj}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px] disabled:bg-slate-100 disabled:text-slate-500"
                  >
                    {assignmentRoleOptions.map((role) => (
                      <option key={role} value={role}>{role}</option>
                    ))}
                  </select>
                  {editingIsPj ? (
                    <p className="mt-1 text-[10px] text-slate-500">
                      Peran Penanggung Jawab tidak dapat diubah agar kegiatan tetap memiliki satu PJ.
                    </p>
                  ) : assignmentForm.peran === 'Lainnya' ? (
                    <input
                      type="text"
                      required
                      placeholder="Isi peran..."
                      value={assignmentForm.peranLainnya}
                      onChange={(e) => setAssignmentForm({ ...assignmentForm, peranLainnya: e.target.value })}
                      className="mt-1.5 w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                    />
                  ) : null}
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Satuan</label>
                  <select
                    value={assignmentForm.satuan}
                    onChange={(e) => setAssignmentForm({ ...assignmentForm, satuan: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  >
                    <option value="Dokumen">Dokumen</option>
                    <option value="Blok Sensus">Blok Sensus</option>
                    <option value="Responden">Responden</option>
                    <option value="Hari">Hari</option>
                    <option value="Laporan">Laporan</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Volume Pekerjaan</label>
                <input
                  type="number"
                  step="any"
                  min="0.01"
                  required
                  value={assignmentForm.volume}
                  onChange={(e) => setAssignmentForm({ ...assignmentForm, volume: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Mulai Tugas (Opsional)</label>
                  <input
                    type="date"
                    value={assignmentForm.mulai}
                    min={selectedActivity?.mulai || undefined}
                    max={selectedActivity?.selesai || undefined}
                    onChange={(e) => setAssignmentForm({ ...assignmentForm, mulai: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Selesai Tugas (Opsional)</label>
                  <input
                    type="date"
                    value={assignmentForm.selesai}
                    min={assignmentForm.mulai || selectedActivity?.mulai || undefined}
                    max={selectedActivity?.selesai || undefined}
                    onChange={(e) => setAssignmentForm({ ...assignmentForm, selesai: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  />
                </div>
              </div>

              {/* Pratinjau otomatis (tanpa menyimpan data) */}
              <div className="pt-2">
                <div className="flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
                  <span className="flex items-center gap-1.5 font-bold">
                    <AlertTriangle className="w-4 h-4 text-amber-600" />
                    Pratinjau Simulasi Dampak Beban Kerja
                  </span>
                  {previewLoading ? (
                    <span className="flex items-center gap-1 font-semibold">
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                      Menghitung...
                    </span>
                  ) : (
                    <span className="text-amber-700">Diperbarui otomatis</span>
                  )}
                </div>
                <p className="mt-1 text-[10px] text-slate-400">
                  Dihitung dari WorkloadService berdasarkan volume x bobot, prorata tanggal, dan penugasan lain. Tidak ada data yang disimpan.
                </p>
              </div>

              {previewError && (
                <p role="alert" className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                  <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                  <span>{previewError}</span>
                </p>
              )}

              {previewResult && (
                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-2">
                  <div className="flex flex-wrap justify-between gap-1 text-[11px] text-slate-600">
                    <span>
                      Rencana: <strong>{previewResult.rencana.volume} {previewResult.rencana.satuan}</strong>
                    </span>
                    <span>
                      Total <strong>{previewResult.rencana.total_poin} pt</strong> •{' '}
                      {previewResult.rencana.mulai} s.d. {previewResult.rencana.selesai}
                    </span>
                  </div>

                  {previewResult.dampak_bulanan.map((impact) => (
                    <div
                      key={impact.bulan}
                      className="rounded-lg border border-slate-200 bg-white p-2.5 space-y-1"
                    >
                      <div className="flex justify-between items-center">
                        <span className="font-bold text-slate-800">{impact.bulan}</span>
                        <span
                          className={`font-bold ${
                            impact.indikator_sesudah === 'melebihi'
                              ? 'text-rose-600'
                              : impact.indikator_sesudah === 'hampir_penuh'
                                ? 'text-amber-600'
                                : 'text-emerald-600'
                          }`}
                        >
                          {INDICATOR_LABELS[impact.indikator_sesudah] ?? impact.indikator_sesudah}
                        </span>
                      </div>
                      <div className="flex justify-between text-slate-600">
                        <span>Beban sebelum</span>
                        <span className="font-semibold">
                          {impact.beban_sebelum.toFixed(2)} pt ({impact.persentase_sebelum.toFixed(1)}%)
                        </span>
                      </div>
                      <div className="flex justify-between text-slate-600">
                        <span>{editingAssignment ? 'Perubahan beban' : 'Tambahan penugasan'}</span>
                        <span className="font-semibold text-sky-700">{impact.tambahan_poin >= 0 ? '+' : ''}{impact.tambahan_poin.toFixed(2)} pt</span>
                      </div>
                      <div className="flex justify-between text-slate-700">
                        <span>Beban sesudah</span>
                        <span className="font-bold text-sky-800">
                          {impact.beban_sesudah.toFixed(2)} pt ({impact.persentase_sesudah.toFixed(1)}%)
                        </span>
                      </div>
                      <div className="flex justify-between text-[10px] text-slate-500">
                        <span>Penugasan bersamaan</span>
                        <span>
                          {impact.penugasan_bersamaan_sebelum} → {impact.penugasan_bersamaan_sesudah} tugas
                        </span>
                      </div>
                    </div>
                  ))}

                  {previewResult.peringatan.map((warning, index) => (
                    <p
                      key={`${warning.jenis}-${warning.bulan}-${index}`}
                      className="flex items-start gap-1.5 font-semibold text-rose-600"
                    >
                      <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                      <span>{warning.pesan}</span>
                    </p>
                  ))}

                  {previewResult.peringatan.length === 0 && (
                    <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-medium text-emerald-800">
                      Tidak ada peringatan beban atau penugasan bersamaan untuk kombinasi ini.
                    </p>
                  )}
                </div>
              )}

              {assignmentError && (
                <p role="alert" className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                  <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                  <span>{assignmentError}</span>
                </p>
              )}

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={closeAssignmentForm}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition min-h-[44px]"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs transition min-h-[44px]"
                >
                  {editingAssignment ? 'Simpan Perubahan' : 'Simpan Penugasan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      {completingActivity && (
        <div className="fixed inset-0 bg-bps/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-md w-full p-5 sm:p-6">
            <div className="flex items-start justify-between gap-3 mb-3">
              <h3 className="text-base font-bold text-slate-900">Selesaikan Kegiatan</h3>
              <button
                type="button"
                onClick={() => setCompletingActivity(null)}
                aria-label="Tutup"
                className="text-slate-400 hover:text-slate-600 p-1.5 -mr-1.5 -mt-1 flex items-center justify-center min-h-[36px] min-w-[36px]"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <p className="text-sm text-slate-600">
              Yakin menandai kegiatan <strong>{completingActivity.nama}</strong> sebagai selesai?
            </p>
            <div className="flex justify-end gap-2 pt-4">
              <button
                type="button"
                onClick={() => setCompletingActivity(null)}
                className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition min-h-[44px]"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleConfirmComplete}
                className="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg shadow-xs transition min-h-[44px]"
              >
                Selesaikan
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
