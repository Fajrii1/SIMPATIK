import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import {
  Plus,
  Search,
  CheckCircle,
  RotateCcw,
  Loader2,
  Trash2,
  Edit,
  Eye,
  UserCheck,
  AlertTriangle,
} from 'lucide-react';
import { Activity, Team, User, Assignment, AssignmentPreviewResult } from '../types';

export const ActivitiesPage: React.FC = () => {
  const { user } = useAuth();
  const [activities, setActivities] = useState<Activity[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [teamMembers, setTeamMembers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedTeam, setSelectedTeam] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('');

  // Modal Tambah/Edit Kegiatan
  const [showModal, setShowModal] = useState(false);
  const [modalLoading, setModalLoading] = useState(false);
  const [editingActivity, setEditingActivity] = useState<Activity | null>(null);
  const [formData, setFormData] = useState({
    nama: '',
    jenis: 'Survei',
    team_id: '',
    mulai: '',
    selesai: '',
    bobot: 1.0,
    kegiatan_besar: false,
    keterangan: '',
  });

  // Modal Detail & Manajemen Penugasan
  const [selectedActivity, setSelectedActivity] = useState<Activity | null>(null);
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [assignmentLoading, setAssignmentLoading] = useState(false);
  const [showAssignmentModal, setShowAssignmentModal] = useState(false);
  const [editingAssignment, setEditingAssignment] = useState<Assignment | null>(null);

  // Form Penugasan + Preview State
  const [assignmentForm, setAssignmentForm] = useState({
    user_id: '',
    peran: 'Pencacah',
    volume: 1,
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

  useEffect(() => {
    fetchTeams();
  }, []);

  useEffect(() => {
    fetchActivities();
  }, [search, selectedTeam, selectedStatus]);

  // Hak Cipta Akses Tambah Kegiatan: Admin atau Ketua Tim
  const canCreate =
    user?.peran === 'admin' ||
    (user?.teams && user.teams.some((t) => teams.some((tm) => tm.id === t.id && tm.ketua_id === user.id)));

  // Buka Form Tambah Kegiatan
  const handleOpenCreate = () => {
    setEditingActivity(null);
    setFormData({
      nama: '',
      jenis: 'Survei',
      team_id: teams[0]?.id ? String(teams[0].id) : '',
      mulai: '',
      selesai: '',
      bobot: 1.0,
      kegiatan_besar: false,
      keterangan: '',
    });
    setShowModal(true);
  };

  // Buka Form Edit Kegiatan
  const handleOpenEdit = (act: Activity) => {
    setEditingActivity(act);
    setFormData({
      nama: act.nama,
      jenis: act.jenis,
      team_id: String(act.team_id),
      mulai: act.mulai,
      selesai: act.selesai,
      bobot: act.bobot,
      kegiatan_besar: act.kegiatan_besar,
      keterangan: act.keterangan || '',
    });
    setShowModal(true);
  };

  // Simpan Kegiatan (Create/Update)
  const handleSaveActivity = async (e: React.FormEvent) => {
    e.preventDefault();
    setModalLoading(true);
    try {
      const payload = {
        ...formData,
        bobot: parseFloat(formData.bobot.toString()),
        team_id: parseInt(formData.team_id),
      };

      if (editingActivity) {
        await api.put(`/activities/${editingActivity.id}`, payload);
      } else {
        await api.post('/activities', payload);
      }

      setShowModal(false);
      fetchActivities();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menyimpan kegiatan statistik.');
    } finally {
      setModalLoading(false);
    }
  };

  // Hapus Kegiatan
  const handleDeleteActivity = async (act: Activity) => {
    if (!confirm(`Yakin ingin menghapus kegiatan "${act.nama}"?`)) return;
    try {
      await api.delete(`/activities/${act.id}`);
      fetchActivities();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menghapus kegiatan.');
    }
  };

  // Toggle Selesaikan/Buka Kembali Kegiatan
  const handleToggleComplete = async (act: Activity) => {
    const isCompleted = act.status === 'Selesai';
    const endpoint = isCompleted ? `/activities/${act.id}/reopen` : `/activities/${act.id}/complete`;
    try {
      await api.patch(endpoint);
      fetchActivities();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal memperbarui status kegiatan.');
    }
  };

  // Buka Detail Kegiatan & Daftar Penugasan
  const handleOpenAssignments = async (act: Activity) => {
    setSelectedActivity(act);
    setAssignmentLoading(true);
    try {
      const [asRes, memRes] = await Promise.all([
        api.get(`/activities/${act.id}/assignments`),
        api.get(`/teams/${act.team_id}/members`),
      ]);
      setAssignments(asRes.data.data || []);
      setTeamMembers(memRes.data.data || []);
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
    setAssignmentForm({
      user_id: teamMembers[0]?.id ? String(teamMembers[0].id) : '',
      peran: 'Pencacah',
      volume: 10,
      satuan: 'Dokumen',
      mulai: selectedActivity?.mulai || '',
      selesai: selectedActivity?.selesai || '',
    });
    setShowAssignmentModal(true);
  };

  // Preview Beban Kerja Sebelum Menyimpan Penugasan
  const handlePreviewAssignment = async () => {
    if (!selectedActivity || !assignmentForm.user_id) return;
    setPreviewLoading(true);
    try {
      const res = await api.post('/assignments/preview', {
        activity_id: selectedActivity.id,
        user_id: parseInt(assignmentForm.user_id),
        peran: assignmentForm.peran,
        volume: parseFloat(assignmentForm.volume.toString()),
        satuan: assignmentForm.satuan,
        mulai: assignmentForm.mulai || undefined,
        selesai: assignmentForm.selesai || undefined,
        assignment_id: editingAssignment?.id,
      });
      setPreviewResult(res.data.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menghitung pratinjau beban kerja.');
    } finally {
      setPreviewLoading(false);
    }
  };

  // Simpan Penugasan Pegawai
  const handleSaveAssignment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedActivity) return;
    try {
      const payload = {
        activity_id: selectedActivity.id,
        user_id: parseInt(assignmentForm.user_id),
        peran: assignmentForm.peran,
        volume: parseFloat(assignmentForm.volume.toString()),
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
      handleOpenAssignments(selectedActivity);
      fetchActivities();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menyimpan penugasan pegawai.');
    }
  };

  // Hapus Penugasan
  const handleDeleteAssignment = async (as: Assignment) => {
    if (!confirm(`Hapus penugasan untuk ${as.user?.nama}?`)) return;
    try {
      await api.delete(`/assignments/${as.id}`);
      if (selectedActivity) handleOpenAssignments(selectedActivity);
      fetchActivities();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menghapus penugasan.');
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
                            onClick={() => handleToggleComplete(act)}
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
                        onClick={() => handleToggleComplete(act)}
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

      {/* Modal Tambah/Edit Kegiatan */}
      {showModal && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-5 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-base sm:text-lg font-bold text-slate-900 mb-4">
              {editingActivity ? 'Edit Kegiatan Statistik' : 'Tambah Kegiatan Statistik Baru'}
            </h2>
            <form onSubmit={handleSaveActivity} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Kegiatan</label>
                <input
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
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tim Penanggung Jawab</label>
                  <select
                    required
                    value={formData.team_id}
                    onChange={(e) => setFormData({ ...formData, team_id: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  >
                    <option value="">Pilih Tim</option>
                    {teams.map((t) => (
                      <option key={t.id} value={t.id}>{t.nama}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Jenis Kegiatan</label>
                  <select
                    value={formData.jenis}
                    onChange={(e) => setFormData({ ...formData, jenis: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  >
                    <option value="Survei">Survei</option>
                    <option value="Sensus">Sensus</option>
                    <option value="Pengolahan Data">Pengolahan Data</option>
                    <option value="Diseminasi">Diseminasi</option>
                    <option value="Pembinaan">Pembinaan</option>
                    <option value="Rapat dan Koordinasi">Rapat dan Koordinasi</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai</label>
                  <input
                    type="date"
                    required
                    value={formData.mulai}
                    onChange={(e) => setFormData({ ...formData, mulai: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai</label>
                  <input
                    type="date"
                    required
                    value={formData.selesai}
                    onChange={(e) => setFormData({ ...formData, selesai: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Bobot Kesulitan (0.50 - 3.00)</label>
                  <input
                    type="number"
                    step="0.05"
                    min="0.5"
                    max="3"
                    required
                    value={formData.bobot}
                    onChange={(e) => setFormData({ ...formData, bobot: parseFloat(e.target.value) || 1.0 })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  />
                </div>

                <div className="pt-2 sm:pt-4 flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="kegiatan_besar"
                    checked={formData.kegiatan_besar}
                    onChange={(e) => setFormData({ ...formData, kegiatan_besar: e.target.checked })}
                    className="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
                  />
                  <label htmlFor="kegiatan_besar" className="text-xs font-medium text-slate-700 cursor-pointer">
                    Termasuk Kegiatan Besar
                  </label>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Keterangan Tambahan</label>
                <textarea
                  rows={2}
                  placeholder="Catatan ringkas kegiatan..."
                  value={formData.keterangan}
                  onChange={(e) => setFormData({ ...formData, keterangan: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition min-h-[44px]"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={modalLoading}
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition min-h-[44px]"
                >
                  {modalLoading ? 'Menyimpan...' : 'Simpan Kegiatan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Manajemen Penugasan Pegawai */}
      {selectedActivity && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
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
                onClick={() => setSelectedActivity(null)}
                aria-label="Tutup modal penugasan"
                className="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center"
              >
                ✕
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
                      <p className="text-slate-500">
                        Peran: <strong className="text-slate-700">{as.peran}</strong> • Volume: {as.volume} {as.satuan} • Poin Beban: <strong>{as.poin_beban} pt</strong>
                      </p>
                      <p className="text-[11px] text-slate-400 font-mono mt-0.5">
                        {as.effective_mulai} s.d. {as.effective_selesai}
                      </p>
                    </div>
                    <div className="flex items-center gap-1 self-end sm:self-auto">
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
                onClick={() => setSelectedActivity(null)}
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
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-lg w-full p-5 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h3 className="text-base font-bold text-slate-900 mb-3">Tugaskan Pegawai ke Kegiatan</h3>
            <form onSubmit={handleSaveAssignment} className="space-y-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Pilih Pegawai</label>
                <select
                  required
                  value={assignmentForm.user_id}
                  onChange={(e) => setAssignmentForm({ ...assignmentForm, user_id: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                >
                  <option value="">Pilih Anggota Tim</option>
                  {teamMembers.map((m) => (
                    <option key={m.id} value={m.id}>{m.nama} ({m.nip})</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Peran Penugasan</label>
                  <input
                    type="text"
                    required
                    value={assignmentForm.peran}
                    onChange={(e) => setAssignmentForm({ ...assignmentForm, peran: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  />
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
                  step="0.1"
                  min="0.1"
                  required
                  value={assignmentForm.volume}
                  onChange={(e) => setAssignmentForm({ ...assignmentForm, volume: parseFloat(e.target.value) || 0 })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Mulai Tugas (Opsional)</label>
                  <input
                    type="date"
                    value={assignmentForm.mulai}
                    onChange={(e) => setAssignmentForm({ ...assignmentForm, mulai: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Selesai Tugas (Opsional)</label>
                  <input
                    type="date"
                    value={assignmentForm.selesai}
                    onChange={(e) => setAssignmentForm({ ...assignmentForm, selesai: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  />
                </div>
              </div>

              {/* Tombol Preview Workload Impact */}
              <div className="pt-2">
                <button
                  type="button"
                  onClick={handlePreviewAssignment}
                  disabled={previewLoading || !assignmentForm.user_id}
                  className="w-full py-2 px-3 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 min-h-[40px]"
                >
                  {previewLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : <AlertTriangle className="w-4 h-4 text-amber-600" />}
                  Pratinjau Simulasi Dampak Beban Kerja
                </button>
              </div>

              {/* Box Hasil Preview */}
              {previewResult && (
                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1.5">
                  <div className="flex justify-between">
                    <span className="text-slate-500">Beban Sebelum:</span>
                    <span className="font-semibold">{previewResult.current_workload} pt ({previewResult.indicator_before})</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Beban Sesudah:</span>
                    <span className="font-bold text-sky-700">{previewResult.simulated_workload} pt ({previewResult.indicator_after})</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Puncak Bersamaan:</span>
                    <span className="font-bold text-slate-800">{previewResult.concurrent_assignments_peak} tugas</span>
                  </div>
                  {previewResult.is_over_threshold && (
                    <p className="text-rose-600 font-bold mt-1">⚠️ Melebihi ambang batas bulanan!</p>
                  )}
                  {previewResult.has_concurrency_warning && (
                    <p className="text-amber-600 font-bold">⚠️ Melebihi batas aman penugasan bersamaan!</p>
                  )}
                </div>
              )}

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowAssignmentModal(false)}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition min-h-[44px]"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs transition min-h-[44px]"
                >
                  Simpan Penugasan
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
