import React, { useCallback, useEffect, useState } from "react";
import api from "../services/api";
import {
  AlertTriangle,
  Clock,
  Edit,
  Loader2,
  Plus,
  Search,
  Trash2,
  X,
} from "lucide-react";
import type { PageMeta, WorkType } from "../types";
import { getApiErrorMessage } from "../services/errors";
import { Pagination } from "../components/Pagination";

const emptyForm = {
  nama: "",
  satuan: "",
  norma_menit: "",
  jenis_norma: "estimasi",
  sumber: "",
  aktif: true,
};

const MAX_NORMA_MENIT = 65535;

export const WorkTypesPage: React.FC = () => {
  const [items, setItems] = useState<WorkType[]>([]);
  const [meta, setMeta] = useState<PageMeta | null>(null);
  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState("");
  const [pageSuccess, setPageSuccess] = useState("");

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<WorkType | null>(null);
  const [formData, setFormData] = useState(emptyForm);
  const [formError, setFormError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const fetchItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get("/work-types", {
        params: {
          search: search || undefined,
          aktif: statusFilter || undefined,
          page,
          per_page: 10,
        },
      });
      setItems(res.data?.data ?? []);
      setMeta(res.data?.meta ?? null);
    } catch (err: unknown) {
      setPageError(getApiErrorMessage(err, "Gagal memuat norma waktu."));
    } finally {
      setLoading(false);
    }
  }, [search, statusFilter, page]);

  useEffect(() => {
    fetchItems();
  }, [fetchItems]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setPage(1);
      setSearch(searchInput.trim());
    }, 350);

    return () => window.clearTimeout(timer);
  }, [searchInput]);

  const openCreate = () => {
    setEditing(null);
    setFormData(emptyForm);
    setFormError("");
    setShowModal(true);
  };

  const openEdit = (item: WorkType) => {
    setEditing(item);
    setFormData({
      nama: item.nama,
      satuan: item.satuan,
      norma_menit: String(item.norma_menit),
      jenis_norma: item.jenis_norma,
      sumber: item.sumber ?? "",
      aktif: item.aktif,
    });
    setFormError("");
    setShowModal(true);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setFormError("");

    if (!formData.nama.trim()) {
      setFormError("Nama jenis pekerjaan wajib diisi.");
      return;
    }
    if (!formData.satuan.trim()) {
      setFormError("Satuan wajib diisi.");
      return;
    }
    if (!(Number(formData.norma_menit) > 0)) {
      setFormError("Norma waktu wajib berupa angka lebih dari 0 (menit).");
      return;
    }
    if (Number(formData.norma_menit) > MAX_NORMA_MENIT) {
      setFormError(
        `Norma waktu maksimal ${MAX_NORMA_MENIT.toLocaleString("id-ID")} menit.`,
      );
      return;
    }

    const payload = {
      nama: formData.nama.trim(),
      satuan: formData.satuan.trim(),
      norma_menit: Number(formData.norma_menit),
      jenis_norma: formData.jenis_norma,
      sumber: formData.sumber.trim() || null,
      aktif: formData.aktif,
    };

    setSubmitting(true);

    try {
      if (editing) {
        await api.put(`/work-types/${editing.id}`, payload);
        setPageSuccess(
          "Norma waktu berhasil diperbarui. Penugasan lama tidak berubah.",
        );
      } else {
        await api.post("/work-types", payload);
        setPageSuccess("Norma waktu baru berhasil ditambahkan.");
      }

      setShowModal(false);
      fetchItems();
    } catch (err: unknown) {
      setFormError(getApiErrorMessage(err, "Gagal menyimpan norma waktu."));
    } finally {
      setSubmitting(false);
    }
  };

  const handleToggleActive = async (item: WorkType) => {
    setPageError("");
    setPageSuccess("");
    try {
      await api.put(`/work-types/${item.id}`, { aktif: !item.aktif });
      setPageSuccess(
        !item.aktif
          ? `Norma waktu "${item.nama}" diaktifkan kembali.`
          : `Norma waktu "${item.nama}" dinonaktifkan dan tidak muncul di form.`,
      );
      fetchItems();
    } catch (err: unknown) {
      setPageError(
        getApiErrorMessage(err, "Gagal mengubah status norma waktu."),
      );
    }
  };

  const handleDelete = async (item: WorkType) => {
    if (
      !confirm(
        `Hapus norma waktu "${item.nama}"? Tindakan ini hanya berlaku bila belum pernah dipakai.`,
      )
    ) {
      return;
    }

    setPageError("");
    setPageSuccess("");
    try {
      await api.delete(`/work-types/${item.id}`);
      setPageSuccess(`Norma waktu "${item.nama}" berhasil dihapus.`);
      fetchItems();
    } catch (err: unknown) {
      setPageError(getApiErrorMessage(err, "Gagal menghapus norma waktu."));
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-lg sm:text-xl font-bold text-slate-900 flex items-center gap-2">
            <Clock className="w-5 h-5 text-sky-600" />
            Norma Waktu
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            Master jenis pekerjaan dan norma waktu untuk perhitungan beban kerja
            (ABK).
          </p>
        </div>
        <button
          onClick={openCreate}
          className="inline-flex items-center justify-center gap-1.5 rounded-lg bg-sky-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-sky-700 min-h-[44px]"
        >
          <Plus className="w-4 h-4" />
          Tambah Norma Waktu
        </button>
      </div>

      {pageError && (
        <p
          role="alert"
          className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"
        >
          <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
          <span>{pageError}</span>
        </p>
      )}

      {pageSuccess && (
        <p
          role="status"
          className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700"
        >
          {pageSuccess}
        </p>
      )}

      <div className="rounded-xl border border-slate-200 bg-white shadow-xs">
        <div className="flex flex-col sm:flex-row gap-2 p-4 border-b border-slate-100">
          <div className="relative flex-1">
            <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
            <input
              type="text"
              placeholder="Cari nama atau satuan..."
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              className="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-sky-600 min-h-[40px]"
            />
          </div>
          <select
            value={statusFilter}
            onChange={(e) => {
              setPage(1);
              setStatusFilter(e.target.value);
            }}
            className="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
          >
            <option value="">Semua Status</option>
            <option value="true">Aktif</option>
            <option value="false">Nonaktif</option>
          </select>
        </div>

        {loading ? (
          <div className="py-12 flex justify-center">
            <Loader2 className="w-6 h-6 animate-spin text-sky-600" />
          </div>
        ) : items.length === 0 ? (
          <p className="py-10 text-center text-sm text-slate-400">
            Belum ada norma waktu.
          </p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm min-w-[760px]">
              <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                <tr>
                  <th className="py-3 px-4">Nama Jenis Pekerjaan</th>
                  <th className="py-3 px-4">Satuan</th>
                  <th className="py-3 px-4">Norma</th>
                  <th className="py-3 px-4">Jenis</th>
                  <th className="py-3 px-4">Sumber</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {items.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/70">
                    <td className="py-3 px-4 font-medium text-slate-900">
                      {item.nama}
                    </td>
                    <td className="py-3 px-4 text-slate-600">{item.satuan}</td>
                    <td className="py-3 px-4 text-slate-600">
                      {item.norma_menit} menit
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                          item.jenis_norma === "standar"
                            ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                            : "bg-amber-50 text-amber-700 border-amber-200"
                        }`}
                      >
                        {item.jenis_norma === "standar"
                          ? "Standar"
                          : "Estimasi"}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-slate-500 text-xs">
                      {item.sumber || "-"}
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                          item.aktif
                            ? "bg-sky-50 text-sky-700 border-sky-200"
                            : "bg-slate-100 text-slate-500 border-slate-200"
                        }`}
                      >
                        {item.aktif ? "Aktif" : "Nonaktif"}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          onClick={() => openEdit(item)}
                          className="p-1.5 text-slate-400 hover:text-sky-600 rounded min-h-[36px] min-w-[36px] flex items-center justify-center"
                          title="Ubah norma waktu"
                          aria-label={`Ubah ${item.nama}`}
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleToggleActive(item)}
                          className="px-2 py-1.5 text-[10px] font-semibold text-slate-500 hover:text-sky-700 rounded min-h-[36px]"
                        >
                          {item.aktif ? "Nonaktifkan" : "Aktifkan"}
                        </button>
                        <button
                          onClick={() => handleDelete(item)}
                          className="p-1.5 text-slate-400 hover:text-rose-600 rounded min-h-[36px] min-w-[36px] flex items-center justify-center"
                          title="Hapus norma waktu"
                          aria-label={`Hapus ${item.nama}`}
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <div className="px-4 pb-3">
          <Pagination
            meta={meta}
            onPageChange={setPage}
            disabled={loading}
            label="norma waktu"
          />
        </div>
      </div>

      {showModal && (
        <div className="fixed inset-0 bg-bps/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-lg w-full p-5 sm:p-6">
            <div className="flex items-start justify-between gap-3 mb-4">
              <h2 className="text-base font-bold text-slate-900">
                {editing ? "Ubah Norma Waktu" : "Tambah Norma Waktu"}
              </h2>
              <button
                type="button"
                onClick={() => setShowModal(false)}
                aria-label="Tutup form norma waktu"
                className="text-slate-400 hover:text-slate-600 p-1.5 -mr-1.5 -mt-1 flex items-center justify-center min-h-[36px] min-w-[36px]"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} noValidate className="space-y-3.5">
              {formError && (
                <p
                  role="alert"
                  className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"
                >
                  <AlertTriangle className="mt-0.5 w-3.5 h-3.5 shrink-0" />
                  <span>{formError}</span>
                </p>
              )}

              <div>
                <label
                  htmlFor="work-type-name"
                  className="block text-xs font-semibold text-slate-700 mb-1"
                >
                  Nama Jenis Pekerjaan <span className="text-rose-500">*</span>
                </label>
                <input
                  id="work-type-name"
                  type="text"
                  required
                  value={formData.nama}
                  onChange={(e) =>
                    setFormData({ ...formData, nama: e.target.value })
                  }
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  placeholder="Misal: Pemeriksaan dokumen"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label
                    htmlFor="work-type-satuan"
                    className="block text-xs font-semibold text-slate-700 mb-1"
                  >
                    Satuan <span className="text-rose-500">*</span>
                  </label>
                  <input
                    id="work-type-satuan"
                    type="text"
                    required
                    value={formData.satuan}
                    onChange={(e) =>
                      setFormData({ ...formData, satuan: e.target.value })
                    }
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                    placeholder="Misal: dokumen"
                  />
                </div>
                <div>
                  <label
                    htmlFor="work-type-norma"
                    className="block text-xs font-semibold text-slate-700 mb-1"
                  >
                    Norma (menit) <span className="text-rose-500">*</span>
                  </label>
                  <input
                    id="work-type-norma"
                    type="number"
                    min="1"
                    max={MAX_NORMA_MENIT}
                    step="1"
                    required
                    value={formData.norma_menit}
                    onChange={(e) =>
                      setFormData({ ...formData, norma_menit: e.target.value })
                    }
                    className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                    placeholder="15"
                  />
                </div>
              </div>

              <div>
                <label
                  htmlFor="work-type-jenis"
                  className="block text-xs font-semibold text-slate-700 mb-1"
                >
                  Jenis Norma <span className="text-rose-500">*</span>
                </label>
                <select
                  id="work-type-jenis"
                  value={formData.jenis_norma}
                  onChange={(e) =>
                    setFormData({ ...formData, jenis_norma: e.target.value })
                  }
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                >
                  <option value="estimasi">Estimasi (belum divalidasi)</option>
                  <option value="standar">Standar (mengikuti ketetapan)</option>
                </select>
              </div>

              <div>
                <label
                  htmlFor="work-type-sumber"
                  className="block text-xs font-semibold text-slate-700 mb-1"
                >
                  Sumber (Opsional)
                </label>
                <input
                  id="work-type-sumber"
                  type="text"
                  value={formData.sumber}
                  onChange={(e) =>
                    setFormData({ ...formData, sumber: e.target.value })
                  }
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                  placeholder="Misal: SK Kepala BPS"
                />
              </div>

              <label className="flex items-center gap-2 text-xs text-slate-700">
                <input
                  type="checkbox"
                  checked={formData.aktif}
                  onChange={(e) =>
                    setFormData({ ...formData, aktif: e.target.checked })
                  }
                  className="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
                />
                Aktif dan dapat dipilih pada form penugasan
              </label>

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
                  disabled={submitting}
                  className="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg shadow-xs disabled:opacity-50 transition min-h-[44px]"
                >
                  {submitting ? "Menyimpan..." : "Simpan"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
