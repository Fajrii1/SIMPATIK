import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import {
  TrendingUp,
  AlertTriangle,
  Search,
  Loader2,
  ChevronRight,
  ShieldAlert,
  X,
} from 'lucide-react';
import { WorkloadSummary, WorkloadWarning, Team } from '../types';

export const WorkloadPage: React.FC = () => {
  const { user } = useAuth();
  const [month, setMonth] = useState<string>(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  });
  const [teamId, setTeamId] = useState<string>('');
  const [teams, setTeams] = useState<Team[]>([]);
  const [workloads, setWorkloads] = useState<WorkloadSummary[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [warnings, setWarnings] = useState<WorkloadWarning[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  // Selected User Detail Modal
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
  const [userDetail, setUserDetail] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const fetchWorkload = async () => {
    setLoading(true);
    try {
      const [wlRes, warnRes] = await Promise.all([
        api.get('/workload', { params: { month, team_id: teamId || undefined } }),
        api.get('/workload/warnings', { params: { month, team_id: teamId || undefined } }),
      ]);
      setWorkloads(wlRes.data.data || []);
      setSummary(wlRes.data.ringkasan || null);
      setWarnings(warnRes.data.data || []);
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
    fetchWorkload();
  }, [month, teamId]);

  const handleOpenDetail = async (targetId: number) => {
    setSelectedUserId(targetId);
    setDetailLoading(true);
    try {
      const res = await api.get(`/workload/users/${targetId}`);
      setUserDetail(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setDetailLoading(false);
    }
  };

  const filteredWorkloads = workloads.filter(
    (w) =>
      w.user.nama.toLowerCase().includes(search.toLowerCase()) ||
      w.user.nip.includes(search)
  );

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Header & Controls */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-lg sm:text-xl font-bold text-slate-900">Monitoring Beban Kerja Pegawai</h1>
          <p className="text-xs sm:text-sm text-slate-500">
            Perhitungan matematis beban bulanan dan deteksi benturan tugas secara real-time
          </p>
        </div>
        <div className="flex items-center gap-2 self-start sm:self-auto">
          <input
            type="month"
            value={month}
            onChange={(e) => setMonth(e.target.value)}
            className="text-xs sm:text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white font-medium text-slate-700 shadow-xs focus:ring-2 focus:ring-sky-600 min-h-[40px]"
          />
        </div>
      </div>

      {/* Summary Stat Cards */}
      {summary && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Pegawai Dipantau</p>
            <p className="text-xl sm:text-2xl font-bold text-slate-900 mt-1">{summary.total_pegawai} orang</p>
          </div>
          <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Rata-Rata Beban</p>
            <p className="text-xl sm:text-2xl font-bold text-sky-700 mt-1">{summary.rata_rata_poin} pt</p>
          </div>
          <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Hampir Penuh (80-99%)</p>
            <p className="text-xl sm:text-2xl font-bold text-amber-600 mt-1">{summary.pegawai_hampir_penuh} orang</p>
          </div>
          <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Melebihi Ambang (≥100%)</p>
            <p className="text-xl sm:text-2xl font-bold text-rose-600 mt-1">{summary.pegawai_melebihi_beban} orang</p>
          </div>
        </div>
      )}

      {/* Warnings Banner */}
      {warnings.length > 0 && (
        <div className="bg-amber-50/70 border border-amber-200 p-4 sm:p-5 rounded-xl space-y-3">
          <h2 className="text-xs sm:text-sm font-bold text-amber-900 flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
            Peringatan Benturan Jadwal & Kelebihan Beban ({warnings.length} Terdeteksi)
          </h2>
          <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
            {warnings.map((w, idx) => (
              <div
                key={idx}
                className="bg-white p-3 rounded-lg border border-amber-100 flex flex-col sm:flex-row sm:items-center justify-between text-xs gap-2"
              >
                <div>
                  <span className="font-bold text-slate-800">
                    {w.pegawai_nama || w.user?.nama || 'Tingkat Kantor'}:
                  </span>{' '}
                  <span className="text-slate-600">{w.pesan}</span>
                </div>
                <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
                  <span
                    className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                      w.tingkat_risiko === 'tinggi'
                        ? 'bg-rose-100 text-rose-800'
                        : 'bg-amber-100 text-amber-800'
                    }`}
                  >
                    Risiko {w.tingkat_risiko || 'sedang'}
                  </span>
                  <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 uppercase">
                    {w.jenis || w.tipe}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Filter and Search */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col sm:flex-row gap-3">
        <div className="flex-1 relative">
          <Search className="w-4 h-4 absolute left-3 top-3.5 text-slate-400" />
          <input
            type="text"
            placeholder="Cari nama pegawai atau NIP..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-sky-600 min-h-[40px]"
          />
        </div>

        <select
          value={teamId}
          onChange={(e) => setTeamId(e.target.value)}
          className="text-xs sm:text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-hidden focus:ring-2 focus:ring-sky-600 min-h-[40px] sm:w-60"
        >
          <option value="">Semua Tim Kerja</option>
          {teams.map((t) => (
            <option key={t.id} value={t.id}>{t.nama}</option>
          ))}
        </select>
      </div>

      {/* Workload Table (Desktop/Tablet) + Card List (Mobile) */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="py-16 flex justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
          </div>
        ) : filteredWorkloads.length === 0 ? (
          <div className="py-16 text-center text-slate-500 text-sm">
            Tidak ada data beban kerja yang cocok dengan kriteria pencarian.
          </div>
        ) : (
          <>
            {/* Desktop & Tablet Table */}
            <div className="hidden sm:block overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                  <tr>
                    <th className="py-3 px-4">Nama Pegawai / NIP</th>
                    <th className="py-3 px-4">Tim Kerja</th>
                    <th className="py-3 px-4">Poin Beban</th>
                    <th className="py-3 px-4">Persentase</th>
                    <th className="py-3 px-4">Status Indikator</th>
                    <th className="py-3 px-4">Total Tugas</th>
                    <th className="py-3 px-4">Puncak Bersamaan</th>
                    <th className="py-3 px-4 text-right">Rincian</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filteredWorkloads.map((item) => (
                    <tr key={item.user.id} className="hover:bg-slate-50/70 transition">
                      <td className="py-3 px-4">
                        <p className="font-semibold text-slate-900">{item.user.nama}</p>
                        <p className="text-xs text-slate-400 font-mono">{item.user.nip}</p>
                      </td>
                      <td className="py-3 px-4 text-xs text-slate-600">
                        {item.user.teams.length > 0 ? item.user.teams.join(', ') : '-'}
                      </td>
                      <td className="py-3 px-4 font-bold text-slate-800 whitespace-nowrap">{item.poin} pt</td>
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-2">
                          <div className="w-16 bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div
                              className={`h-full ${
                                item.persentase >= 100
                                  ? 'bg-rose-500'
                                  : item.persentase >= 80
                                  ? 'bg-amber-500'
                                  : 'bg-sky-500'
                              }`}
                              style={{ width: `${Math.min(item.persentase, 100)}%` }}
                            />
                          </div>
                          <span className="text-xs font-medium text-slate-700">{item.persentase}%</span>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <span
                          className={`text-xs px-2.5 py-0.5 rounded-full font-medium whitespace-nowrap ${
                            item.indikator === 'aman'
                              ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                              : item.indikator === 'hampir_penuh'
                              ? 'bg-amber-50 text-amber-700 border border-amber-200'
                              : 'bg-rose-50 text-rose-700 border border-rose-200'
                          }`}
                        >
                          {item.indikator === 'aman'
                            ? 'Aman'
                            : item.indikator === 'hampir_penuh'
                            ? 'Hampir Penuh'
                            : 'Melebihi'}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-slate-600 whitespace-nowrap">{item.jumlah_penugasan} tugas</td>
                      <td className="py-3 px-4 whitespace-nowrap">
                        <span
                          className={`font-semibold ${
                            item.penugasan_bersamaan_maks >= 3 ? 'text-amber-600' : 'text-slate-700'
                          }`}
                        >
                          {item.penugasan_bersamaan_maks} tugas
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right">
                        <button
                          onClick={() => handleOpenDetail(item.user.id)}
                          className="p-2 text-slate-400 hover:text-sky-600 hover:bg-slate-100 rounded-lg transition min-h-[36px] min-w-[36px] inline-flex items-center justify-center"
                          title="Lihat Rincian Tugas"
                          aria-label="Lihat Rincian Tugas"
                        >
                          <ChevronRight className="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Mobile Card Stack (<640px) */}
            <div className="sm:hidden divide-y divide-slate-100">
              {filteredWorkloads.map((item) => (
                <div key={item.user.id} className="p-4 space-y-2.5">
                  <div className="flex justify-between items-start gap-2">
                    <div>
                      <p className="font-semibold text-slate-900 text-sm">{item.user.nama}</p>
                      <p className="text-xs text-slate-400 font-mono">{item.user.nip}</p>
                    </div>
                    <span
                      className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${
                        item.indikator === 'aman'
                          ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                          : item.indikator === 'hampir_penuh'
                          ? 'bg-amber-50 text-amber-700 border border-amber-200'
                          : 'bg-rose-50 text-rose-700 border border-rose-200'
                      }`}
                    >
                      {item.indikator === 'aman' ? 'Aman' : item.indikator === 'hampir_penuh' ? 'Hampir' : 'Lebih'}
                    </span>
                  </div>

                  <div className="flex items-center justify-between text-xs text-slate-600">
                    <span>Total Poin: <strong className="text-slate-900">{item.poin} pt</strong> ({item.persentase}%)</span>
                    <span>Puncak: <strong className="text-slate-900">{item.penugasan_bersamaan_maks}</strong> tugas/hari</span>
                  </div>

                  <div className="flex justify-end pt-1">
                    <button
                      onClick={() => handleOpenDetail(item.user.id)}
                      className="text-xs font-semibold text-sky-600 hover:text-sky-700 flex items-center gap-1 min-h-[36px]"
                    >
                      Rincian Lengkap <ChevronRight className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
      </div>

      {/* Modal Detail Pegawai */}
      {selectedUserId && (
        <div className="fixed inset-0 bg-bps/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-2xl w-full p-5 sm:p-6 max-h-[85vh] flex flex-col">
            <div className="flex justify-between items-start mb-4 border-b border-slate-100 pb-3">
              <div>
                <h2 className="text-base sm:text-lg font-bold text-slate-900">{userDetail?.user?.nama || 'Memuat...'}</h2>
                <p className="text-xs text-slate-500 font-mono">NIP: {userDetail?.user?.nip}</p>
              </div>
              <button
                onClick={() => setSelectedUserId(null)}
                aria-label="Tutup modal rincian"
                className="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {detailLoading ? (
              <div className="py-12 flex justify-center">
                <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
              </div>
            ) : (
              <div className="overflow-y-auto space-y-5 flex-1 pr-1">
                {/* Tren 5 Bulan */}
                <div>
                  <h3 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Tren Beban Kerja Multi-Bulan
                  </h3>
                  <div className="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs">
                    {userDetail?.tren_bulanan?.map((t: any) => (
                      <div key={t.bulan} className="p-2 sm:p-2.5 rounded-lg border border-slate-100 bg-slate-50">
                        <p className="font-semibold text-slate-600 text-[11px]">{t.bulan}</p>
                        <p className="text-xs sm:text-sm font-bold text-slate-900 mt-0.5">{t.poin} pt</p>
                        <p className="text-[10px] text-slate-500">{t.persentase}%</p>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Rincian Penugasan */}
                <div>
                  <h3 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Daftar Penugasan Aktif & Periode Terkait
                  </h3>
                  {userDetail?.penugasan?.length === 0 ? (
                    <p className="text-xs text-slate-400 py-3">Tidak ada penugasan tercatat.</p>
                  ) : (
                    <div className="divide-y divide-slate-100 border border-slate-100 rounded-lg overflow-hidden">
                      {userDetail?.penugasan?.map((as: any) => (
                        <div key={as.id} className="p-3 text-xs flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 bg-white">
                          <div>
                            <p className="font-semibold text-slate-800">{as.kegiatan}</p>
                            {as.metrics_visible === false ? (
                              <p className="text-slate-500 mt-0.5">
                                {as.tim} • Peran: <span className="font-medium text-slate-700">{as.peran}</span>
                              </p>
                            ) : (
                              <p className="text-slate-500 mt-0.5">
                                {as.tim} • Peran: <span className="font-medium text-slate-700">{as.peran}</span> • Vol: {as.volume} {as.satuan}
                              </p>
                            )}
                            <p className="text-[11px] text-slate-400 font-mono mt-0.5">
                              {as.tanggal_mulai} s.d. {as.tanggal_selesai}
                            </p>
                          </div>
                          <div className="sm:text-right shrink-0">
                            {as.metrics_visible === false ? (
                              <span className="text-slate-400">—</span>
                            ) : (
                              <span className="font-bold text-sky-700">{as.poin_total} pt total</span>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}

            <div className="mt-4 pt-3 border-t border-slate-100 text-right">
              <button
                onClick={() => setSelectedUserId(null)}
                className="px-4 py-2 text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition min-h-[44px]"
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
