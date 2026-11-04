import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import { useSearchParams } from 'react-router-dom';
import {
  AlertTriangle,
  Plus,
  Loader2,
  Eye,
  Pencil,
  Trash2,
} from 'lucide-react';
import { WeeklyIssue, WeeklyIssueHistory, Team, Activity, User } from '../types';
import { getApiErrorMessage } from '../services/errors';

type ActivityOption = Pick<Activity, 'id' | 'nama'>;
type PicOption = Pick<User, 'id' | 'nama' | 'nip' | 'aktif'>;

const statusLabel = (value?: string | null): string => {
  switch (value) {
    case 'terbuka':
      return 'Terbuka';
    case 'diproses':
      return 'Diproses';
    case 'selesai':
      return 'Selesai';
    default:
      return 'Tidak diketahui';
  }
};

const aksiLabel = (aksi: string): string => {
  switch (aksi) {
    case 'create':
      return 'Kendala dilaporkan';
    case 'update':
      return 'Isi kendala diubah';
    case 'update_status':
      return 'Status kendala diubah';
    case 'delete':
      return 'Kendala dihapus';
    default:
      return aksi;
  }
};

const emptyForm = {
  team_id: '',
  activity_id: '',
  minggu_mulai: '',
  kendala: '',
  solusi: '',
  tindak_lanjut: '',
  pic_id: '',
  tenggat: '',
};

export const IssuesPage: React.FC = () => {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const idParam = searchParams.get('id');

  const [issues, setIssues] = useState<WeeklyIssue[]>([]);
  const [carriedIssues, setCarriedIssues] = useState<WeeklyIssue[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [selectedTeam, setSelectedTeam] = useState<string>('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [deleteError, setDeleteError] = useState('');
  const [allowPegawaiIssue, setAllowPegawaiIssue] = useState(false);

  // Buat Kendala
  const [showModal, setShowModal] = useState(false);
  const [createError, setCreateError] = useState('');
  const [createLoading, setCreateLoading] = useState(false);
  const [createFormData, setCreateFormData] = useState(emptyForm);
  const [createKegiatan, setCreateKegiatan] = useState<ActivityOption[]>([]);
  const [createPicOptions, setCreatePicOptions] = useState<PicOption[]>([]);

  // Ubah Kendala
  const [showEditModal, setShowEditModal] = useState(false);
  const [editTarget, setEditTarget] = useState<WeeklyIssue | null>(null);
  const [editFormData, setEditFormData] = useState(emptyForm);
  const [editError, setEditError] = useState('');
  const [editLoading, setEditLoading] = useState(false);
  const [editKegiatan, setEditKegiatan] = useState<ActivityOption[]>([]);
  const [editPicOptions, setEditPicOptions] = useState<PicOption[]>([]);

  // Detail Kendala
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [detailIssue, setDetailIssue] = useState<WeeklyIssue | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState('');

  // Modal Status Update
  const [activeIssue, setActiveIssue] = useState<WeeklyIssue | null>(null);
  const [statusFormData, setStatusFormData] = useState({
    status: 'diproses',
    solusi: '',
    tindak_lanjut: '',
    catatan_penutupan: '',
  });
  const [statusError, setStatusError] = useState('');
  const [statusLoading, setStatusLoading] = useState(false);

  const isAdmin = user?.peran === 'admin';

  const isKetuaOfTeam = (teamId: number) =>
    Boolean(user?.led_team_ids?.includes(teamId));

  const canEditIssue = (item: WeeklyIssue) => {
    if (item.status === 'selesai') {
      return isAdmin || isKetuaOfTeam(item.team_id);
    }
    return (
      isAdmin ||
      isKetuaOfTeam(item.team_id) ||
      item.pelapor_id === user?.id
    );
  };

  const canManageIssue = (item: WeeklyIssue) =>
    isAdmin || isKetuaOfTeam(item.team_id) || item.pic_id === user?.id;

  // PIC tidak boleh membuka kembali kendala yang sudah selesai.
  const manageRestricted =
    activeIssue?.status === 'selesai' &&
    !isAdmin &&
    !isKetuaOfTeam(activeIssue?.team_id ?? -1);

  const canDeleteIssue = (item: WeeklyIssue) =>
    isAdmin || isKetuaOfTeam(item.team_id);

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

  const fetchTeams = async () => {
    try {
      const res = await api.get('/teams');
      setTeams(res.data?.data ?? []);
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

  const fetchKegiatan = async (
    teamId: number,
    setter: React.Dispatch<React.SetStateAction<ActivityOption[]>>
  ) => {
    try {
      const res = await api.get('/activities', {
        params: { team_id: teamId, per_page: 100 },
      });
      const data = res.data?.data ?? [];
      setter(data.map((activity: Activity) => ({
        id: activity.id,
        nama: activity.nama,
      })));
    } catch {
      setter([]);
    }
  };

  const fetchPics = async (
    teamId: number,
    setter: React.Dispatch<React.SetStateAction<PicOption[]>>
  ) => {
    try {
      const res = await api.get(`/teams/${teamId}/members`);
      const data: PicOption[] = res.data?.data ?? [];
      setter(data.filter((member) => member.aktif !== false));
    } catch {
      setter([]);
    }
  };

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
      setIssues(issueRes.data?.data ?? []);
      setCarriedIssues(carriedRes.data?.data ?? []);
    } catch (err: unknown) {
      setError(getApiErrorMessage(err, 'Gagal memuat data kendala mingguan.'));
    } finally {
      setLoading(false);
    }
  };

  const openDetailById = async (id: number) => {
    setShowDetailModal(true);
    setDetailLoading(true);
    setDetailError('');
    setDetailIssue(null);

    try {
      const res = await api.get(`/issues/${id}`);
      setDetailIssue(res.data?.data ?? null);
    } catch (err: unknown) {
      setDetailError(
        getApiErrorMessage(err, 'Kendala tidak ditemukan atau Anda tidak berwenang melihatnya.')
      );
    } finally {
      setDetailLoading(false);
    }
  };

  const closeDetail = () => {
    setShowDetailModal(false);
    setDetailIssue(null);
    setDetailError('');

    if (idParam) {
      const next = new URLSearchParams(searchParams);
      next.delete('id');
      setSearchParams(next, { replace: true });
    }
  };

  useEffect(() => {
    fetchTeams();
    fetchSettings();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    fetchIssues();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedTeam, selectedStatus]);

  useEffect(() => {
    if (!idParam) {
      return;
    }

    const id = Number(idParam);
    if (Number.isInteger(id) && id > 0) {
      openDetailById(id);
    } else {
      setDetailIssue(null);
      setDetailError('Parameter ID kendala tidak valid.');
      setShowDetailModal(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idParam]);

  // Pilihan kegiatan dan PIC dikosongkan/diisi ulang ketika tim pada form berubah.
  useEffect(() => {
    if (!createFormData.team_id) {
      setCreateKegiatan([]);
      setCreatePicOptions([]);
      return;
    }

    const teamId = Number(createFormData.team_id);
    setCreateFormData((prev) => ({ ...prev, activity_id: '', pic_id: '' }));
    fetchKegiatan(teamId, setCreateKegiatan);
    fetchPics(teamId, setCreatePicOptions);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [createFormData.team_id]);

  useEffect(() => {
    if (!editFormData.team_id) {
      setEditKegiatan([]);
      setEditPicOptions([]);
      return;
    }

    const teamId = Number(editFormData.team_id);
    setEditFormData((prev) => ({ ...prev, activity_id: '', pic_id: '' }));
    fetchKegiatan(teamId, setEditKegiatan);
    fetchPics(teamId, setEditPicOptions);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editFormData.team_id]);

  const openCreateModal = () => {
    setCreateError('');
    setCreateFormData(emptyForm);
    setCreateKegiatan([]);
    setCreatePicOptions([]);
    setShowModal(true);
  };

  const openEditModal = (item: WeeklyIssue) => {
    setEditError('');
    setEditTarget(item);
    setEditFormData({
      team_id: String(item.team_id),
      activity_id: item.activity_id ? String(item.activity_id) : '',
      minggu_mulai: item.minggu_mulai,
      kendala: item.kendala,
      solusi: item.solusi ?? '',
      tindak_lanjut: item.tindak_lanjut ?? '',
      pic_id: item.pic_id ? String(item.pic_id) : '',
      tenggat: item.tenggat ?? '',
    });
    setShowEditModal(true);
  };

  const payloadFromForm = (form: typeof emptyForm): Record<string, unknown> => {
    const payload: Record<string, unknown> = {
      team_id: Number(form.team_id),
      minggu_mulai: form.minggu_mulai,
      kendala: form.kendala,
    };

    if (form.activity_id) payload.activity_id = Number(form.activity_id);
    if (form.solusi.trim()) payload.solusi = form.solusi.trim();
    if (form.tindak_lanjut.trim()) payload.tindak_lanjut = form.tindak_lanjut.trim();
    if (form.pic_id) payload.pic_id = Number(form.pic_id);
    if (form.tenggat) payload.tenggat = form.tenggat;

    return payload;
  };

  const resetCreateForm = () => setCreateFormData(emptyForm);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateError('');
    setCreateLoading(true);
    try {
      await api.post('/issues', payloadFromForm(createFormData));
      setShowModal(false);
      resetCreateForm();
      fetchIssues();
    } catch (err: unknown) {
      setCreateError(getApiErrorMessage(err, 'Gagal melaporkan kendala mingguan.'));
    } finally {
      setCreateLoading(false);
    }
  };

  const handleEditSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editTarget) return;

    setEditError('');
    setEditLoading(true);
    try {
      await api.put(`/issues/${editTarget.id}`, payloadFromForm(editFormData));
      setShowEditModal(false);
      setEditTarget(null);
      fetchIssues();
    } catch (err: unknown) {
      setEditError(getApiErrorMessage(err, 'Gagal memperbarui kendala.'));
    } finally {
      setEditLoading(false);
    }
  };

  const handleDelete = async (item: WeeklyIssue) => {
    if (!window.confirm(`Hapus kendala: "${item.kendala}"?`)) {
      return;
    }

    setDeleteError('');
    try {
      await api.delete(`/issues/${item.id}`);
      fetchIssues();
    } catch (err: unknown) {
      setDeleteError(getApiErrorMessage(err, 'Gagal menghapus kendala.'));
    }
  };

  const openStatusModal = (item: WeeklyIssue) => {
    setStatusError('');
    setActiveIssue(item);
    setStatusFormData({
      status: item.status,
      solusi: item.solusi ?? '',
      tindak_lanjut: item.tindak_lanjut ?? '',
      catatan_penutupan: item.catatan_penutupan ?? '',
    });
  };

  const handleUpdateStatus = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeIssue) return;

    setStatusError('');
    setStatusLoading(true);
    try {
      await api.patch(`/issues/${activeIssue.id}/status`, statusFormData);
      setActiveIssue(null);
      fetchIssues();
    } catch (err: unknown) {
      setStatusError(getApiErrorMessage(err, 'Gagal memperbarui status kendala.'));
    } finally {
      setStatusLoading(false);
    }
  };

  const renderTeamField = (
    prefix: 'create' | 'edit',
    formData: typeof emptyForm,
    setter: React.Dispatch<React.SetStateAction<typeof emptyForm>>
  ) => (
    <div>
      <label
        htmlFor={`${prefix}-team`}
        className="block text-xs font-semibold text-slate-700 mb-1"
      >
        Tim Terkait
      </label>
      <select
        id={`${prefix}-team`}
        required
        value={formData.team_id}
        onChange={(e) => setter({ ...formData, team_id: e.target.value })}
        className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
      >
        <option value="">Pilih Tim</option>
        {reportableTeams.map((t) => (
          <option key={t.id} value={t.id}>
            {t.nama}
          </option>
        ))}
      </select>
    </div>
  );

  const renderActivityField = (
    prefix: 'create' | 'edit',
    formData: typeof emptyForm,
    setter: React.Dispatch<React.SetStateAction<typeof emptyForm>>,
    options: ActivityOption[]
  ) => (
    <div>
      <label
        htmlFor={`${prefix}-activity`}
        className="block text-xs font-semibold text-slate-700 mb-1"
      >
        Kegiatan Terkait (opsional)
      </label>
      <select
        id={`${prefix}-activity`}
        value={formData.activity_id}
        onChange={(e) => setter({ ...formData, activity_id: e.target.value })}
        className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
      >
        <option value="">Tanpa kegiatan</option>
        {options.map((activity) => (
          <option key={activity.id} value={activity.id}>
            {activity.nama}
          </option>
        ))}
      </select>
    </div>
  );

  const renderPicField = (
    prefix: 'create' | 'edit',
    formData: typeof emptyForm,
    setter: React.Dispatch<React.SetStateAction<typeof emptyForm>>,
    options: PicOption[]
  ) => (
    <div>
      <label
        htmlFor={`${prefix}-pic`}
        className="block text-xs font-semibold text-slate-700 mb-1"
      >
        Penanggung Jawab / PIC (opsional)
      </label>
      <select
        id={`${prefix}-pic`}
        value={formData.pic_id}
        onChange={(e) => setter({ ...formData, pic_id: e.target.value })}
        className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
      >
        <option value="">Tanpa PIC</option>
        {options.map((pic) => (
          <option key={pic.id} value={pic.id}>
            {pic.nama}
          </option>
        ))}
      </select>
    </div>
  );

  const renderFormBody = (
    prefix: 'create' | 'edit',
    formData: typeof emptyForm,
    setter: React.Dispatch<React.SetStateAction<typeof emptyForm>>,
    kegiatan: ActivityOption[],
    picOptions: PicOption[]
  ) => (
    <>
      {renderTeamField(prefix, formData, setter)}
      {renderActivityField(prefix, formData, setter, kegiatan)}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label
            htmlFor={`${prefix}-week`}
            className="block text-xs font-semibold text-slate-700 mb-1"
          >
            Minggu Mulai Kendala
          </label>
          <input
            id={`${prefix}-week`}
            type="date"
            required
            value={formData.minggu_mulai}
            onChange={(e) => setter({ ...formData, minggu_mulai: e.target.value })}
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
          />
        </div>
        <div>
          <label
            htmlFor={`${prefix}-due`}
            className="block text-xs font-semibold text-slate-700 mb-1"
          >
            Tenggat Target Penyelesaian
          </label>
          <input
            id={`${prefix}-due`}
            type="date"
            value={formData.tenggat}
            onChange={(e) => setter({ ...formData, tenggat: e.target.value })}
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
          />
        </div>
      </div>

      <div>
        <label
          htmlFor={`${prefix}-kendala`}
          className="block text-xs font-semibold text-slate-700 mb-1"
        >
          Uraian Hambatan / Kendala
        </label>
        <textarea
          id={`${prefix}-kendala`}
          required
          rows={3}
          placeholder="Misal: Akses jalan tergenang banjir sehingga pendataan tertunda..."
          value={formData.kendala}
          onChange={(e) => setter({ ...formData, kendala: e.target.value })}
          className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
        />
      </div>

      <div>
        <label
          htmlFor={`${prefix}-solusi`}
          className="block text-xs font-semibold text-slate-700 mb-1"
        >
          Rencana Solusi / Usulan
        </label>
        <textarea
          id={`${prefix}-solusi`}
          rows={2}
          placeholder="Usulan tindakan penanganan..."
          value={formData.solusi}
          onChange={(e) => setter({ ...formData, solusi: e.target.value })}
          className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
        />
      </div>

      <div>
        <label
          htmlFor={`${prefix}-tindaklanjut`}
          className="block text-xs font-semibold text-slate-700 mb-1"
        >
          Tindak Lanjut (opsional)
        </label>
        <textarea
          id={`${prefix}-tindaklanjut`}
          rows={2}
          placeholder="Langkah yang telah atau akan dilakukan..."
          value={formData.tindak_lanjut}
          onChange={(e) => setter({ ...formData, tindak_lanjut: e.target.value })}
          className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
        />
      </div>

      {renderPicField(prefix, formData, setter, picOptions)}
    </>
  );

  const formatHistoryValue = (field: string, value: string | null) => {
    if (!value) {
      return '-';
    }

    if (field === 'tenggat') {
      const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
      if (match) {
        return `${match[3]}/${match[2]}/${match[1]}`;
      }
    }

    if (field === 'status') {
      return statusLabel(value);
    }

    return value;
  };

  const renderHistory = (history?: WeeklyIssueHistory[]) => {
    if (!history || history.length === 0) {
      return <p className="text-xs text-slate-500">Belum ada riwayat status.</p>;
    }

    return (
      <ol className="space-y-3">
        {history.map((entry) => (
          <li key={entry.id} className="border-l-2 border-sky-200 pl-3">
            <p className="text-xs font-semibold text-slate-500">
              {new Date(entry.waktu).toLocaleString('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
              })}
            </p>
            <p className="text-sm text-slate-800">
              {entry.aktor} - {aksiLabel(entry.aksi)}
            </p>
            {(entry.status_lama || entry.status_baru) && (
              <p className="text-xs text-slate-600 mt-0.5">
                {statusLabel(entry.status_lama)} → {statusLabel(entry.status_baru)}
              </p>
            )}
            {entry.perubahan && entry.perubahan.length > 0 && (
              <ul className="mt-1 space-y-0.5">
                {entry.perubahan.map((change) => (
                  <li key={change.field} className="text-xs text-slate-600">
                    {change.label}: {formatHistoryValue(change.field, change.lama)} →{' '}
                    {formatHistoryValue(change.field, change.baru)}
                  </li>
                ))}
              </ul>
            )}
            {entry.catatan && (
              <p className="text-xs text-slate-600 mt-0.5">Catatan: {entry.catatan}</p>
            )}
          </li>
        ))}
      </ol>
    );
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
            onClick={openCreateModal}
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
              <div
                key={ci.id}
                className="bg-white p-3 rounded-lg border border-rose-100 flex items-center justify-between text-xs"
              >
                <div>
                  <span className="font-bold text-slate-800">{ci.team?.nama}:</span>{' '}
                  {ci.kendala}
                  <span className="ml-2 text-slate-400 font-mono">(Sejak {ci.minggu_mulai})</span>
                </div>
                <button
                  onClick={() => openStatusModal(ci)}
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
          aria-label="Filter tim"
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
          aria-label="Filter status"
        >
          <option value="">Semua Status Kendala</option>
          <option value="terbuka">Terbuka</option>
          <option value="diproses">Sedang Diproses</option>
          <option value="selesai">Selesai</option>
        </select>
      </div>

      {deleteError && (
        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {deleteError}
        </div>
      )}

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
                    <td className="py-3 px-4 text-slate-600 text-xs">
                      {item.pelapor?.nama || '-'}
                    </td>
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
                        {statusLabel(item.status)}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex flex-wrap items-center justify-end gap-2">
                        <button
                          onClick={() => openDetailById(item.id)}
                          className="inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-800 font-semibold"
                        >
                          <Eye className="w-3.5 h-3.5" />
                          Lihat
                        </button>
                        {canEditIssue(item) && (
                          <button
                            onClick={() => openEditModal(item)}
                            className="inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-800 font-semibold"
                          >
                            <Pencil className="w-3.5 h-3.5" />
                            Ubah
                          </button>
                        )}
                        {canManageIssue(item) && (
                          <button
                            onClick={() => openStatusModal(item)}
                            className="text-xs text-sky-600 hover:text-sky-700 font-semibold"
                          >
                            Kelola
                          </button>
                        )}
                        {canDeleteIssue(item) && (
                          <button
                            onClick={() => handleDelete(item)}
                            className="inline-flex items-center gap-1 text-xs text-rose-600 hover:text-rose-700 font-semibold"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                            Hapus
                          </button>
                        )}
                      </div>
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
        <div className="fixed inset-0 bg-bps/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-4 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-bold text-slate-900 mb-4">Laporkan Kendala Lapangan</h2>
            <form onSubmit={handleCreate} className="space-y-4">
              {renderFormBody(
                'create',
                createFormData,
                setCreateFormData,
                createKegiatan,
                createPicOptions
              )}

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
                  disabled={createLoading}
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition"
                >
                  {createLoading ? 'Menyimpan...' : 'Kirim Laporan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Ubah Kendala */}
      {showEditModal && editTarget && (
        <div className="fixed inset-0 bg-bps/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-4 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-bold text-slate-900 mb-4">Ubah Data Kendala</h2>
            <form onSubmit={handleEditSubmit} className="space-y-4">
              {renderFormBody(
                'edit',
                editFormData,
                setEditFormData,
                editKegiatan,
                editPicOptions
              )}

              {editError && (
                <p role="alert" className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                  <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                  <span>{editError}</span>
                </p>
              )}

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => {
                    setShowEditModal(false);
                    setEditTarget(null);
                  }}
                  className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={editLoading}
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition"
                >
                  {editLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Detail Kendala */}
      {showDetailModal && (
        <div className="fixed inset-0 bg-bps/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="detail-kendala-title"
            className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-4 sm:p-6 max-h-[90vh] overflow-y-auto space-y-4"
          >
            <div className="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
              <h2 id="detail-kendala-title" className="text-lg font-bold text-slate-900">
                Detail Kendala
              </h2>
              <button
                onClick={closeDetail}
                className="px-3 py-2 text-xs text-slate-600 hover:bg-slate-100 rounded-lg min-h-[40px]"
              >
                Tutup
              </button>
            </div>

            {detailLoading ? (
              <div className="py-12 flex justify-center">
                <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
              </div>
            ) : detailError ? (
              <p className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                <span>{detailError}</span>
              </p>
            ) : detailIssue ? (
              <div className="space-y-4 text-sm text-slate-700">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                  <div>
                    <p className="text-slate-400">Tim</p>
                    <p className="font-medium text-slate-800">{detailIssue.team?.nama ?? '-'}</p>
                  </div>
                  <div>
                    <p className="text-slate-400">Minggu Mulai</p>
                    <p className="font-medium text-slate-800">{detailIssue.minggu_mulai}</p>
                  </div>
                  <div>
                    <p className="text-slate-400">Pelapor</p>
                    <p className="font-medium text-slate-800">{detailIssue.pelapor?.nama ?? '-'}</p>
                  </div>
                  <div>
                    <p className="text-slate-400">PIC</p>
                    <p className="font-medium text-slate-800">{detailIssue.pic?.nama ?? '-'}</p>
                  </div>
                  <div>
                    <p className="text-slate-400">Kegiatan Terkait</p>
                    <p className="font-medium text-slate-800">
                      {detailIssue.kegiatan?.nama ?? 'Tidak ada'}
                    </p>
                  </div>
                  <div>
                    <p className="text-slate-400">Tenggat</p>
                    <p className="font-medium text-slate-800">{detailIssue.tenggat ?? '-'}</p>
                  </div>
                  <div>
                    <p className="text-slate-400">Status</p>
                    <p className="font-medium text-slate-800">
                      {statusLabel(detailIssue.status)}
                      {detailIssue.is_overdue && (
                        <span className="ml-2 text-rose-600 font-semibold">(Jatuh tempo)</span>
                      )}
                    </p>
                  </div>
                  <div>
                    <p className="text-slate-400">Waktu Ditutup</p>
                    <p className="font-medium text-slate-800">
                      {detailIssue.ditutup_pada
                        ? new Date(detailIssue.ditutup_pada).toLocaleString('id-ID', {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                          })
                        : '-'}
                    </p>
                  </div>
                </div>

                <div className="space-y-3 border-t border-slate-100 pt-3">
                  <div>
                    <p className="text-xs font-semibold text-slate-400">Uraian Kendala</p>
                    <p className="text-sm text-slate-800">{detailIssue.kendala}</p>
                  </div>
                  {detailIssue.solusi && (
                    <div>
                      <p className="text-xs font-semibold text-slate-400">Usulan Solusi</p>
                      <p className="text-sm text-slate-800">{detailIssue.solusi}</p>
                    </div>
                  )}
                  {detailIssue.tindak_lanjut && (
                    <div>
                      <p className="text-xs font-semibold text-slate-400">Tindak Lanjut</p>
                      <p className="text-sm text-slate-800">{detailIssue.tindak_lanjut}</p>
                    </div>
                  )}
                  {detailIssue.catatan_penutupan && (
                    <div>
                      <p className="text-xs font-semibold text-slate-400">Catatan Penutupan</p>
                      <p className="text-sm text-slate-800">{detailIssue.catatan_penutupan}</p>
                    </div>
                  )}
                </div>

                <div className="border-t border-slate-100 pt-3 space-y-2">
                  <p className="text-xs font-semibold text-slate-400">Riwayat Status</p>
                  {renderHistory(detailIssue.history)}
                </div>
              </div>
            ) : null}
          </div>
        </div>
      )}

      {/* Modal Update Status */}
      {activeIssue && (
        <div className="fixed inset-0 bg-bps/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-4 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-base font-bold text-slate-900 mb-1">Tindak Lanjut & Status Kendala</h2>
            <p className="text-xs text-slate-500 mb-4 font-medium">{activeIssue.kendala}</p>

            <form onSubmit={handleUpdateStatus} className="space-y-4">
              {manageRestricted && (
                <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                  Kendala sudah selesai. Hanya Admin atau Ketua Tim yang dapat membuka kembali.
                </p>
              )}

              <div>
                <label
                  htmlFor="issue-status"
                  className="block text-xs font-semibold text-slate-700 mb-1"
                >
                  Status Kendala
                </label>
                <select
                  id="issue-status"
                  value={statusFormData.status}
                  onChange={(e) => setStatusFormData({ ...statusFormData, status: e.target.value })}
                  disabled={manageRestricted}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 disabled:opacity-60"
                >
                  <option value="terbuka">Terbuka</option>
                  <option value="diproses">Sedang Diproses</option>
                  <option value="selesai">Selesai (Ditutup)</option>
                </select>
              </div>

              <div>
                <label
                  htmlFor="issue-tindaklanjut"
                  className="block text-xs font-semibold text-slate-700 mb-1"
                >
                  Tindak Lanjut yang Dilakukan
                </label>
                <textarea
                  id="issue-tindaklanjut"
                  rows={2}
                  placeholder="Langkah penyelesaian..."
                  value={statusFormData.tindak_lanjut}
                  onChange={(e) =>
                    setStatusFormData({ ...statusFormData, tindak_lanjut: e.target.value })
                  }
                  disabled={manageRestricted}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 disabled:opacity-60"
                />
              </div>

              {statusFormData.status === 'selesai' && (
                <div>
                  <label
                    htmlFor="issue-catatan"
                    className="block text-xs font-semibold text-slate-700 mb-1"
                  >
                    Catatan Penutupan <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    id="issue-catatan"
                    rows={2}
                    required
                    placeholder="Keterangan hasil penyelesaian kendala..."
                    value={statusFormData.catatan_penutupan}
                    onChange={(e) =>
                      setStatusFormData({ ...statusFormData, catatan_penutupan: e.target.value })
                    }
                    disabled={manageRestricted}
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 disabled:opacity-60"
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
                  disabled={statusLoading || manageRestricted}
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition"
                >
                  {statusLoading ? 'Memperbarui...' : 'Simpan Status'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
