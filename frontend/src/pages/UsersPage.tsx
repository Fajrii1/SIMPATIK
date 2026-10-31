import React, { useState, useEffect } from 'react';
import api from '../services/api';
import {
  Users,
  Plus,
  CheckCircle,
  XCircle,
  KeyRound,
  Loader2,
  Search,
  Edit,
  Trash2,
  Eye,
  UserPlus,
  UserMinus,
} from 'lucide-react';
import { User, Team } from '../types';

export const UsersPage: React.FC = () => {
  const [users, setUsers] = useState<User[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [activeTab, setActiveTab] = useState<'users' | 'teams'>('users');

  // User Modal State (Create & Edit)
  const [showUserModal, setShowUserModal] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [viewingUser, setViewingUser] = useState<User | null>(null);
  const [userFormData, setUserFormData] = useState({
    nama: '',
    email: '',
    nip: '',
    jabatan: 'Statistisi Pertama',
    peran: 'pegawai',
    password: 'password',
  });

  // Team Modal State (Create & Edit)
  const [showTeamModal, setShowTeamModal] = useState(false);
  const [editingTeam, setEditingTeam] = useState<Team | null>(null);
  const [viewingTeam, setViewingTeam] = useState<Team | null>(null);
  const [teamMembers, setTeamMembers] = useState<User[]>([]);
  const [teamFormData, setTeamFormData] = useState({
    nama: '',
    keterangan: '',
    ketua_id: '',
  });

  // Add Member Modal
  const [selectedAddMemberId, setSelectedAddMemberId] = useState('');

  const fetchData = async () => {
    setLoading(true);
    try {
      const [uRes, tRes] = await Promise.all([
        api.get('/users', { params: { search: search || undefined, per_page: 50 } }),
        api.get('/teams'),
      ]);
      setUsers(uRes.data.data || []);
      setTeams(tRes.data.data || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [search]);

  // Open Create User Modal
  const handleOpenCreateUser = () => {
    setEditingUser(null);
    setUserFormData({
      nama: '',
      email: '',
      nip: '',
      jabatan: 'Statistisi Pertama',
      peran: 'pegawai',
      password: 'password',
    });
    setShowUserModal(true);
  };

  // Open Edit User Modal
  const handleOpenEditUser = (u: User) => {
    setEditingUser(u);
    setUserFormData({
      nama: u.nama,
      email: u.email,
      nip: u.nip,
      jabatan: u.jabatan,
      peran: u.peran,
      password: '',
    });
    setShowUserModal(true);
  };

  // Simpan User (Create/Update)
  const handleSaveUser = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (editingUser) {
        await api.put(`/users/${editingUser.id}`, userFormData);
      } else {
        await api.post('/users', userFormData);
      }
      setShowUserModal(false);
      fetchData();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menyimpan data pegawai.');
    }
  };

  // Hapus User (Soft Delete)
  const handleDeleteUser = async (u: User) => {
    if (!confirm(`Hapus (soft delete) akun pegawai ${u.nama}?`)) return;
    try {
      await api.delete(`/users/${u.id}`);
      fetchData();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menghapus data pegawai.');
    }
  };

  // Buka Modal Tim (Create)
  const handleOpenCreateTeam = () => {
    setEditingTeam(null);
    setTeamFormData({ nama: '', keterangan: '', ketua_id: '' });
    setShowTeamModal(true);
  };

  // Buka Modal Tim (Edit)
  const handleOpenEditTeam = (t: Team) => {
    setEditingTeam(t);
    setTeamFormData({
      nama: t.nama,
      keterangan: t.keterangan || '',
      ketua_id: t.ketua_id ? String(t.ketua_id) : '',
    });
    setShowTeamModal(true);
  };

  // Simpan Tim (Create/Update)
  const handleSaveTeam = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const payload = {
        ...teamFormData,
        ketua_id: teamFormData.ketua_id ? parseInt(teamFormData.ketua_id) : null,
      };

      if (editingTeam) {
        await api.put(`/teams/${editingTeam.id}`, payload);
      } else {
        await api.post('/teams', payload);
      }

      setShowTeamModal(false);
      fetchData();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menyimpan tim kerja.');
    }
  };

  // Hapus Tim (Soft Delete)
  const handleDeleteTeam = async (t: Team) => {
    if (!confirm(`Hapus tim kerja "${t.nama}"?`)) return;
    try {
      await api.delete(`/teams/${t.id}`);
      fetchData();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menghapus tim.');
    }
  };

  // Lihat Anggota Tim
  const handleOpenTeamMembers = async (t: Team) => {
    setViewingTeam(t);
    try {
      const res = await api.get(`/teams/${t.id}/members`);
      setTeamMembers(res.data.data || []);
    } catch (err) {
      console.error(err);
    }
  };

  // Tambah Anggota ke Tim
  const handleAddMemberToTeam = async () => {
    if (!viewingTeam || !selectedAddMemberId) return;
    try {
      await api.post(`/teams/${viewingTeam.id}/members`, {
        user_id: parseInt(selectedAddMemberId),
      });
      setSelectedAddMemberId('');
      handleOpenTeamMembers(viewingTeam);
      fetchData();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menambahkan anggota.');
    }
  };

  // Hapus Anggota dari Tim
  const handleRemoveMemberFromTeam = async (userId: number) => {
    if (!viewingTeam) return;
    if (!confirm('Hapus anggota ini dari tim kerja?')) return;
    try {
      await api.delete(`/teams/${viewingTeam.id}/members/${userId}`);
      handleOpenTeamMembers(viewingTeam);
      fetchData();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal menghapus anggota.');
    }
  };

  const handleToggleUserStatus = async (user: User) => {
    try {
      await api.patch(`/users/${user.id}/status`, { aktif: !user.aktif });
      fetchData();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal mengubah status pegawai.');
    }
  };

  const handleResetPassword = async (user: User) => {
    if (!confirm(`Reset kata sandi untuk ${user.nama} ke default 'password'?`)) return;
    try {
      await api.patch(`/users/${user.id}/reset-password`);
      alert('Kata sandi berhasil direset ke "password". Pegawai diwajibkan mengganti kata sandi saat login.');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Gagal mereset kata sandi.');
    }
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Header & Tombol Tambah */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-lg sm:text-xl font-bold text-slate-900">Manajemen Pengguna & Tim Kerja</h1>
          <p className="text-xs sm:text-sm text-slate-500">Kelola akun aparatur sipil BPS, hak akses peran, dan struktur tim</p>
        </div>

        <div className="flex items-center gap-2 self-start sm:self-auto">
          {activeTab === 'users' ? (
            <button
              onClick={handleOpenCreateUser}
              className="flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-xs transition min-h-[44px]"
            >
              <Plus className="w-4 h-4" /> Tambah Pegawai
            </button>
          ) : (
            <button
              onClick={handleOpenCreateTeam}
              className="flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-xs transition min-h-[44px]"
            >
              <Plus className="w-4 h-4" /> Tambah Tim Kerja
            </button>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-slate-200">
        <button
          onClick={() => setActiveTab('users')}
          className={`py-3 px-4 text-sm font-semibold border-b-2 transition min-h-[44px] ${
            activeTab === 'users'
              ? 'border-sky-600 text-sky-600'
              : 'border-transparent text-slate-500 hover:text-slate-700'
          }`}
        >
          Daftar Pegawai ({users.length})
        </button>
        <button
          onClick={() => setActiveTab('teams')}
          className={`py-3 px-4 text-sm font-semibold border-b-2 transition min-h-[44px] ${
            activeTab === 'teams'
              ? 'border-sky-600 text-sky-600'
              : 'border-transparent text-slate-500 hover:text-slate-700'
          }`}
        >
          Tim Kerja ({teams.length})
        </button>
      </div>

      {/* Content for Users Tab */}
      {activeTab === 'users' && (
        <div className="space-y-4">
          <div className="bg-white p-3 rounded-xl border border-slate-200 shadow-xs">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-3.5 text-slate-400" />
              <input
                type="text"
                placeholder="Cari berdasarkan nama atau NIP..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-sky-600 min-h-[40px]"
              />
            </div>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            {loading ? (
              <div className="py-16 flex justify-center">
                <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
              </div>
            ) : (
              <>
                {/* Desktop & Tablet Table */}
                <div className="hidden sm:block overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                      <tr>
                        <th className="py-3 px-4">Nama Pegawai / NIP</th>
                        <th className="py-3 px-4">Email</th>
                        <th className="py-3 px-4">Jabatan</th>
                        <th className="py-3 px-4">Peran</th>
                        <th className="py-3 px-4">Status</th>
                        <th className="py-3 px-4 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {users.map((u) => (
                        <tr key={u.id} className="hover:bg-slate-50/70 transition">
                          <td className="py-3 px-4">
                            <p className="font-semibold text-slate-900">{u.nama}</p>
                            <p className="text-xs text-slate-400 font-mono">{u.nip}</p>
                          </td>
                          <td className="py-3 px-4 text-slate-600 text-xs">{u.email}</td>
                          <td className="py-3 px-4 text-slate-600">{u.jabatan}</td>
                          <td className="py-3 px-4">
                            <span className="text-xs px-2.5 py-0.5 rounded-full font-medium bg-slate-100 text-slate-700 capitalize whitespace-nowrap">
                              {u.peran}
                            </span>
                          </td>
                          <td className="py-3 px-4">
                            <span
                              className={`text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap ${
                                u.aktif
                                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                  : 'bg-slate-100 text-slate-400'
                              }`}
                            >
                              {u.aktif ? 'Aktif' : 'Nonaktif'}
                            </span>
                          </td>
                          <td className="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
                            <button
                              onClick={() => setViewingUser(u)}
                              title="Lihat Detail"
                              className="p-1.5 text-slate-400 hover:text-sky-600 rounded transition"
                            >
                              <Eye className="w-4 h-4 inline" />
                            </button>
                            <button
                              onClick={() => handleOpenEditUser(u)}
                              title="Edit Pegawai"
                              className="p-1.5 text-slate-400 hover:text-amber-600 rounded transition"
                            >
                              <Edit className="w-4 h-4 inline" />
                            </button>
                            <button
                              onClick={() => handleResetPassword(u)}
                              title="Reset Kata Sandi"
                              className="p-1.5 text-slate-400 hover:text-amber-600 transition"
                            >
                              <KeyRound className="w-4 h-4 inline" />
                            </button>
                            <button
                              onClick={() => handleToggleUserStatus(u)}
                              title={u.aktif ? 'Nonaktifkan Akun' : 'Aktifkan Akun'}
                              className={`p-1.5 transition ${
                                u.aktif ? 'text-slate-400 hover:text-rose-600' : 'text-slate-400 hover:text-emerald-600'
                              }`}
                            >
                              {u.aktif ? <XCircle className="w-4 h-4 inline" /> : <CheckCircle className="w-4 h-4 inline" />}
                            </button>
                            <button
                              onClick={() => handleDeleteUser(u)}
                              title="Hapus Pegawai"
                              className="p-1.5 text-slate-400 hover:text-rose-600 rounded transition"
                            >
                              <Trash2 className="w-4 h-4 inline" />
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Mobile Card Stack (<640px) */}
                <div className="sm:hidden divide-y divide-slate-100">
                  {users.map((u) => (
                    <div key={u.id} className="p-4 space-y-2">
                      <div className="flex justify-between items-start">
                        <div>
                          <p className="font-semibold text-slate-900 text-sm">{u.nama}</p>
                          <p className="text-xs text-slate-400 font-mono">{u.nip}</p>
                        </div>
                        <span
                          className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${
                            u.aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'
                          }`}
                        >
                          {u.aktif ? 'Aktif' : 'Nonaktif'}
                        </span>
                      </div>
                      <p className="text-xs text-slate-500">{u.email} • {u.jabatan} ({u.peran})</p>
                      <div className="pt-2 border-t border-slate-100 flex justify-end gap-1">
                        <button
                          onClick={() => setViewingUser(u)}
                          className="p-2 text-slate-500 hover:text-sky-600 min-h-[36px] min-w-[36px]"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenEditUser(u)}
                          className="p-2 text-slate-500 hover:text-amber-600 min-h-[36px] min-w-[36px]"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleResetPassword(u)}
                          className="p-2 text-slate-500 hover:text-amber-600 min-h-[36px] min-w-[36px]"
                        >
                          <KeyRound className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleToggleUserStatus(u)}
                          className={`p-2 min-h-[36px] min-w-[36px] ${u.aktif ? 'text-rose-600' : 'text-emerald-600'}`}
                        >
                          {u.aktif ? <XCircle className="w-4 h-4" /> : <CheckCircle className="w-4 h-4" />}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* Content for Teams Tab */}
      {activeTab === 'teams' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {teams.map((t) => (
            <div key={t.id} className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4">
              <div>
                <div className="flex justify-between items-start gap-2">
                  <h3 className="font-bold text-slate-900 text-base">{t.nama}</h3>
                  <div className="flex items-center gap-1">
                    <button
                      onClick={() => handleOpenEditTeam(t)}
                      className="p-1.5 text-slate-400 hover:text-amber-600 min-h-[36px] min-w-[36px] flex items-center justify-center"
                      title="Edit Tim"
                    >
                      <Edit className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => handleDeleteTeam(t)}
                      className="p-1.5 text-slate-400 hover:text-rose-600 min-h-[36px] min-w-[36px] flex items-center justify-center"
                      title="Hapus Tim"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
                <p className="text-xs text-slate-500 mt-1">{t.keterangan || 'Tidak ada deskripsi'}</p>
                <div className="mt-3 pt-3 border-t border-slate-100 text-xs flex justify-between">
                  <span className="text-slate-500">Ketua Tim:</span>
                  <span className="font-semibold text-sky-700">{t.ketua?.nama || 'Belum Ditentukan'}</span>
                </div>
              </div>
              <div className="pt-2 flex justify-between items-center text-xs">
                <span className="text-slate-400">{t.members?.length || 0} anggota</span>
                <button
                  onClick={() => handleOpenTeamMembers(t)}
                  className="px-3 py-1.5 bg-sky-50 text-sky-700 hover:bg-sky-100 font-semibold rounded-lg border border-sky-100 transition min-h-[36px]"
                >
                  Kelola Anggota
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal User (Create & Edit) */}
      {showUserModal && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-5 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-base font-bold text-slate-900 mb-4">
              {editingUser ? 'Edit Data Pegawai' : 'Tambah Pegawai Baru'}
            </h2>
            <form onSubmit={handleSaveUser} className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap & Gelar</label>
                <input
                  type="text"
                  required
                  value={userFormData.nama}
                  onChange={(e) => setUserFormData({ ...userFormData, nama: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">NIP (18 Digit)</label>
                <input
                  type="text"
                  required
                  value={userFormData.nip}
                  onChange={(e) => setUserFormData({ ...userFormData, nip: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Alamat Email</label>
                <input
                  type="email"
                  required
                  value={userFormData.email}
                  onChange={(e) => setUserFormData({ ...userFormData, email: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                />
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Peran Akun</label>
                  <select
                    value={userFormData.peran}
                    onChange={(e) => setUserFormData({ ...userFormData, peran: e.target.value as any })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  >
                    <option value="pegawai">Pegawai</option>
                    <option value="pimpinan">Pimpinan</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Jabatan</label>
                  <input
                    type="text"
                    required
                    value={userFormData.jabatan}
                    onChange={(e) => setUserFormData({ ...userFormData, jabatan: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowUserModal(false)}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg min-h-[44px]"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg min-h-[44px]"
                >
                  Simpan Pegawai
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Detail User */}
      {viewingUser && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-sm w-full p-5 space-y-4">
            <h3 className="font-bold text-slate-900 text-base border-b pb-2">Detail Aparatur BPS</h3>
            <div className="text-xs space-y-2 text-slate-700">
              <p><span className="text-slate-400">Nama:</span> <strong>{viewingUser.nama}</strong></p>
              <p><span className="text-slate-400">NIP:</span> {viewingUser.nip}</p>
              <p><span className="text-slate-400">Email:</span> {viewingUser.email}</p>
              <p><span className="text-slate-400">Jabatan:</span> {viewingUser.jabatan}</p>
              <p><span className="text-slate-400">Peran:</span> {viewingUser.peran}</p>
              <p><span className="text-slate-400">Status:</span> {viewingUser.aktif ? 'Aktif' : 'Nonaktif'}</p>
            </div>
            <div className="text-right pt-2 border-t">
              <button
                onClick={() => setViewingUser(null)}
                className="px-4 py-2 text-xs bg-slate-100 hover:bg-slate-200 rounded-lg min-h-[36px]"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Tim (Create & Edit) */}
      {showTeamModal && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-5 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-base font-bold text-slate-900 mb-4">
              {editingTeam ? 'Edit Tim Kerja' : 'Tambah Tim Kerja Baru'}
            </h2>
            <form onSubmit={handleSaveTeam} className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Tim</label>
                <input
                  type="text"
                  required
                  placeholder="Misal: Tim Statistik Sosial"
                  value={teamFormData.nama}
                  onChange={(e) => setTeamFormData({ ...teamFormData, nama: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Ketua Tim</label>
                <select
                  value={teamFormData.ketua_id}
                  onChange={(e) => setTeamFormData({ ...teamFormData, ketua_id: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 min-h-[40px]"
                >
                  <option value="">Belum Ada Ketua</option>
                  {users.map((u) => (
                    <option key={u.id} value={u.id}>{u.nama}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Deskripsi / Ruang Lingkup</label>
                <textarea
                  rows={2}
                  value={teamFormData.keterangan}
                  onChange={(e) => setTeamFormData({ ...teamFormData, keterangan: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowTeamModal(false)}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg min-h-[44px]"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg min-h-[44px]"
                >
                  Simpan Tim
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Manajemen Anggota Tim */}
      {viewingTeam && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-5 sm:p-6 max-h-[85vh] flex flex-col">
            <h3 className="font-bold text-slate-900 text-base mb-2">
              Anggota: {viewingTeam.nama}
            </h3>

            {/* Form Tambah Anggota */}
            <div className="flex gap-2 py-2 border-b border-slate-100">
              <select
                value={selectedAddMemberId}
                onChange={(e) => setSelectedAddMemberId(e.target.value)}
                className="flex-1 text-xs border border-slate-200 rounded-lg px-2.5 py-2 min-h-[40px]"
              >
                <option value="">Pilih Pegawai untuk Ditambahkan</option>
                {users.map((u) => (
                  <option key={u.id} value={u.id}>{u.nama} ({u.nip})</option>
                ))}
              </select>
              <button
                onClick={handleAddMemberToTeam}
                disabled={!selectedAddMemberId}
                className="px-3 py-2 bg-sky-600 text-white rounded-lg text-xs font-semibold disabled:opacity-50 min-h-[40px]"
              >
                <UserPlus className="w-4 h-4 inline mr-1" /> Tambah
              </button>
            </div>

            {/* List Anggota */}
            <div className="flex-1 overflow-y-auto divide-y divide-slate-100 my-2">
              {teamMembers.length === 0 ? (
                <p className="text-xs text-slate-400 py-6 text-center">Belum ada anggota di tim ini.</p>
              ) : (
                teamMembers.map((m) => (
                  <div key={m.id} className="py-2.5 flex justify-between items-center text-xs">
                    <div>
                      <p className="font-semibold text-slate-800">{m.nama}</p>
                      <p className="text-slate-400 font-mono">{m.nip}</p>
                    </div>
                    <button
                      onClick={() => handleRemoveMemberFromTeam(m.id)}
                      className="p-1.5 text-slate-400 hover:text-rose-600 transition min-h-[36px] min-w-[36px] flex items-center justify-center"
                      title="Hapus dari Tim"
                    >
                      <UserMinus className="w-4 h-4" />
                    </button>
                  </div>
                ))
              )}
            </div>

            <div className="pt-2 border-t border-slate-100 text-right">
              <button
                onClick={() => setViewingTeam(null)}
                className="px-4 py-2 text-xs bg-slate-100 hover:bg-slate-200 rounded-lg min-h-[36px]"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
