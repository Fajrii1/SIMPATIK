import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import {
  AlertTriangle,
  Plus,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  Loader2,
  MessageSquare,
} from 'lucide-react';
import { WeeklyIssue, Team } from '../types';
import { getApiErrorMessage } from '../services/errors';

export const IssuesPage: React.FC = () => {
  const { user } = useAuth();
  const [issues, setIssues] = useState<WeeklyIssue[]>([]);
  const [carriedIssues, setCarriedIssues] = useState<WeeklyIssue[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [selectedTeam, setSelectedTeam] = useState<string>('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [allowPegawaiIssue, setAllowPegawaiIssue] = useState(false);

  const reportableTeams = useMemo(() => {
    if (user?.peran === 'admin') return teams;
    return teams.filter(
      (team) =>
        team.ketua_id === user?.id ||
        team.members?.some((member) => member.id === user?.id)
    );
  }, [teams, user]);

  const canReportIssue = useMemo(() => {
    if (user?.peran === 'admin') return true;
    if (user?.is_ketua) return true;
    return allowPegawaiIssue && reportableTeams.length > 0;
  }, [user, allowPegawaiIssue, reportableTeams]);

  const canManageIssue = (item: WeeklyIssue) =>
    user?.peran === 'admin' ||
    Boolean(user?.led_team_ids?.includes(item.team_id)) ||
    item.pic_id === user?.id;

  // Modal State
  const [showModal, setShowModal] = useState(false);
  const [modalLoading, setModalLoading] = useState(false);
  const [createError, setCreateError] = useState('');
  const [statusError, setStatusError] = useState('');
  const [formData, setFormData] = useState({
    team_id: '',
    minggu_mulai: '',
    kendala: '',
    solusi: '',
    tenggat: '',
  });

  // Modal Status Update
  const [activeIssue, setActiveIssue] = useState<WeeklyIssue | null>(null);
  const [statusFormData, setStatusFormData] = useState({
    status: 'diproses',
    solusi: '',
    tindak_lanjut: '',
    catatan_penutupan: '',
  });

  const fetchTeams = async () => {
    try {
      const res = await api.get('/teams');
      setTeams(res.data.data);
    } catch (err) {
      console.error(err);
    }
  };

  const fetchSettings = async () => {
    try {
      const res = await api.get('/settings');
      setAllowPegawaiIssue(Boolean(res.data?.settings?.pegawai_boleh_isi_kendala));
    } catch {
      setAllowPegawaiIssue(false);
    }
  };

  useEffect(() => {
    fetchTeams();
    fetchSettings();
  }, []);

  useEffect(() => {
    fetchIssues();
  }, [selectedTeam, selectedStatus]);

  const fetchIssues = async () => {
    setLoading(true);
    setError('');
    try {
      const [issueRes, carriedRes] = await Promise.all([
        api.get('/issues', {
          params: {
            team_id: selectedTeam || undefined,
            status: selectedStatus || undefined,
          },
        }),
        api.get('/issues/carried', {
          params: {
            team_id: selectedTeam || undefined,
          },
        }),
      ]);
      setIssues(issueRes.data.data);
      setCarriedIssues(carriedRes.data.data);
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal memuat data kendala mingguan.');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateError('');
    setModalLoading(true);
    try {
      await api.post('/issues', {
        ...formData,
        team_id: parseInt(formData.team_id),
      });
      setShowModal(false);
      setFormData({
        team_id: '',
        minggu_mulai: '',
        kendala: '',
        solusi: '',
        tenggat: '',
      });
      fetchIssues();
    } catch (err: unknown) {
      setCreateError(getApiErrorMessage(err, 'Gagal melaporkan kendala mingguan.'));
    } finally {
      setModalLoading(false);
    }
  };

  const handleUpdateStatus = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeIssue) return;
    setStatusError('');
    setModalLoading(true);
    try {
      await api.patch(`/issues/${activeIssue.id}/status`, statusFormData);
      setActiveIssue(null);
      fetchIssues();
    } catch (err: unknown) {
      setStatusError(getApiErrorMessage(err, 'Gagal memperbarui status kendala.'));
    } finally {
      setModalLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-xl font-bold text-slate-900">Kendala Mingguan & Solusi</h1>
          <p className="text-sm text-slate-500">
            Pencatatan hambatan lapangan, eskalasi ke pimpinan, dan pemantauan tindak lanjut
          </p>
        </div>
        {canReportIssue && (
          <button
            onClick={() => {
              setCreateError('');
              setShowModal(true);
            }}
            className="flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-xs transition"
          >
            <Plus className="w-4 h-4" />
            Laporkan Kendala
          </button>
        )}
      </div>

      {/* Carried Issues Warning Section */}
      {carriedIssues.length > 0 && (
        <div className="bg-rose-50/70 border border-rose-200 p-5 rounded-xl space-y-3">
          <h2 className="text-sm font-bold text-rose-900 flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-rose-600" />
            Kendala Terbawa Dari Minggu Sebelumnya ({carriedIssues.length} Belum Selesai)
          </h2>
          <div className="space-y-2">
            {carriedIssues.map((ci) => (
              <div key={ci.id} className="bg-white p-3 rounded-lg border border-rose-100 flex items-center justify-between text-xs">
                <div>
                  <span className="font-bold text-slate-800">{ci.team?.nama}:</span> {ci.kendala}
                  <span className="ml-2 text-slate-400 font-mono">(Sejak {ci.minggu_mulai})</span>
                </div>
                <button
                  onClick={() => {
                    setStatusError('');
                    setActiveIssue(ci);
                    setStatusFormData({
                      status: ci.status,
                      solusi: ci.solusi || '',
                      tindak_lanjut: ci.tindak_lanjut || '',
                      catatan_penutupan: ci.catatan_penutupan || '',
                    });
                  }}
                  className="px-2.5 py-1 bg-rose-100 hover:bg-rose-200 text-rose-800 font-semibold rounded transition"
                >
                  Tindak Lanjut
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Filter and Search */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col md:flex-row gap-3">
        <select
          value={selectedTeam}
          onChange={(e) => setSelectedTeam(e.target.value)}
          className="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
        >
          <option value="">Semua Tim Kerja</option>
          {teams.map((t) => (
            <option key={t.id} value={t.id}>{t.nama}</option>
          ))}
        </select>

        <select
          value={selectedStatus}
          onChange={(e) => setSelectedStatus(e.target.value)}
          className="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
        >
          <option value="">Semua Status Kendala</option>
          <option value="terbuka">Terbuka</option>
          <option value="diproses">Sedang Diproses</option>
          <option value="selesai">Selesai</option>
        </select>
      </div>

      {error && (
        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      )}

      {/* Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="py-16 flex justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
          </div>
        ) : issues.length === 0 ? (
          <div className="py-16 text-center text-slate-500 text-sm">
            Tidak ada laporan kendala yang ditemukan.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                <tr>
                  <th className="py-3 px-4">Deskripsi Kendala</th>
                  <th className="py-3 px-4">Tim Kerja</th>
                  <th className="py-3 px-4">Pelapor</th>
                  <th className="py-3 px-4">Minggu Mulai</th>
                  <th className="py-3 px-4">Tenggat</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {issues.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4">
                      <p className="font-medium text-slate-900">{item.kendala}</p>
                      {item.solusi && (
                        <p className="text-xs text-slate-500 mt-0.5">Solusi: {item.solusi}</p>
                      )}
                    </td>
                    <td className="py-3 px-4 text-slate-600">{item.team?.nama || '-'}</td>
                    <td className="py-3 px-4 text-slate-600 text-xs">{item.pelapor?.nama || '-'}</td>
                    <td className="py-3 px-4 text-slate-500 text-xs">{item.minggu_mulai}</td>
                    <td className="py-3 px-4 text-slate-500 text-xs">{item.tenggat || '-'}</td>
                    <td className="py-3 px-4">
                      <span
                        className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${
                          item.status === 'selesai'
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                            : item.status === 'diproses'
                            ? 'bg-amber-50 text-amber-700 border border-amber-200'
                            : 'bg-rose-50 text-rose-700 border border-rose-200'
                        }`}
                      >
                        {item.status}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-right">
                      {canManageIssue(item) ? (
                        <button
                          onClick={() => {
                            setStatusError('');
                            setActiveIssue(item);
                            setStatusFormData({
                              status: item.status,
                              solusi: item.solusi || '',
                              tindak_lanjut: item.tindak_lanjut || '',
                              catatan_penutupan: item.catatan_penutupan || '',
                            });
                          }}
                          className="text-xs text-sky-600 hover:text-sky-700 font-semibold"
                        >
                          Kelola
                        </button>
                      ) : (
                        <span className="text-xs text-slate-400">Lihat</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal Lapor Kendala */}
      {showModal && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-6">
            <h2 className="text-lg font-bold text-slate-900 mb-4">Laporkan Kendala Lapangan</h2>
            <form onSubmit={handleCreate} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Tim Terkait</label>
                <select
                  required
                  value={formData.team_id}
                  onChange={(e) => setFormData({ ...formData, team_id: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                >
                  <option value="">Pilih Tim</option>
                  {reportableTeams.map((t) => (
                    <option key={t.id} value={t.id}>{t.nama}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Minggu Mulai Kendala</label>
                  <input
                    type="date"
                    required
                    value={formData.minggu_mulai}
                    onChange={(e) => setFormData({ ...formData, minggu_mulai: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tenggat Target Penyelesaian</label>
                  <input
                    type="date"
                    value={formData.tenggat}
                    onChange={(e) => setFormData({ ...formData, tenggat: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Uraian Hambatan / Kendala</label>
                <textarea
                  required
                  rows={3}
                  placeholder="Misal: Akses jalan tergenang banjir sehingga pendataan tertunda..."
                  value={formData.kendala}
                  onChange={(e) => setFormData({ ...formData, kendala: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Rencana Solusi / Usulan</label>
                <textarea
                  rows={2}
                  placeholder="Usulan tindakan penanganan..."
                  value={formData.solusi}
                  onChange={(e) => setFormData({ ...formData, solusi: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                />
              </div>

              {createError && (
                <p role="alert" className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                  <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                  <span>{createError}</span>
                </p>
              )}

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={modalLoading}
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition"
                >
                  {modalLoading ? 'Menyimpan...' : 'Kirim Laporan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Update Status */}
      {activeIssue && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-6">
            <h2 className="text-base font-bold text-slate-900 mb-1">Tindak Lanjut & Status Kendala</h2>
            <p className="text-xs text-slate-500 mb-4 font-medium">{activeIssue.kendala}</p>

            <form onSubmit={handleUpdateStatus} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Status Kendala</label>
                <select
                  value={statusFormData.status}
                  onChange={(e) => setStatusFormData({ ...statusFormData, status: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                >
                  <option value="terbuka">Terbuka</option>
                  <option value="diproses">Sedang Diproses</option>
                  <option value="selesai">Selesai (Ditutup)</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Tindak Lanjut yang Dilakukan</label>
                <textarea
                  rows={2}
                  placeholder="Langkah penyelesaian..."
                  value={statusFormData.tindak_lanjut}
                  onChange={(e) => setStatusFormData({ ...statusFormData, tindak_lanjut: e.target.value })}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                />
              </div>

              {statusFormData.status === 'selesai' && (
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Catatan Penutupan <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    rows={2}
                    required
                    placeholder="Keterangan hasil penyelesaian kendala..."
                    value={statusFormData.catatan_penutupan}
                    onChange={(e) => setStatusFormData({ ...statusFormData, catatan_penutupan: e.target.value })}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
                  />
                </div>
              )}

              {statusError && (
                <p role="alert" className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                  <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                  <span>{statusError}</span>
                </p>
              )}

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setActiveIssue(null)}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={modalLoading}
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition"
                >
                  {modalLoading ? 'Memperbarui...' : 'Simpan Status'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

