export type Role = 'admin' | 'pimpinan' | 'pegawai';

export interface User {
  id: number;
  nama: string;
  email: string;
  nip: string;
  jabatan: string;
  peran: Role;
  aktif: boolean;
  must_change_password: boolean;
  teams?: { id: number; nama: string }[];
}

export interface Activity {
  id: number;
  nama: string;
  jenis: string;
  team_id: number;
  team?: { id: number; nama: string };
  mulai: string;
  selesai: string;
  bobot: number;
  kegiatan_besar: boolean;
  selesai_manual: boolean;
  selesai_pada?: string;
  status: 'Belum Mulai' | 'Berjalan' | 'Selesai' | 'Terlambat';
  keterangan?: string;
  assignments_count?: number;
}

export interface Assignment {
  id: number;
  activity_id: number;
  user_id: number;
  peran: string;
  volume: number;
  satuan: string;
  mulai?: string;
  selesai?: string;
  effective_mulai: string;
  effective_selesai: string;
  poin_beban: number;
  user?: User;
  activity?: Activity;
}

export interface Team {
  id: number;
  nama: string;
  keterangan?: string;
  ketua_id?: number | null;
  ketua?: User | null;
  aktif?: boolean;
  members_count?: number;
  members?: User[];
}

export interface WeeklyIssue {
  id: number;
  activity_id?: number;
  activity?: { id: number; nama: string };
  team_id: number;
  team?: { id: number; nama: string };
  pelapor_id: number;
  pelapor?: User;
  pic_id?: number;
  pic?: User;
  minggu_mulai: string;
  kendala: string;
  solusi?: string;
  tindak_lanjut?: string;
  tenggat?: string;
  status: 'terbuka' | 'diproses' | 'selesai';
  is_overdue: boolean;
  catatan_penutupan?: string;
  created_at: string;
}

export interface NotificationItem {
  id: number;
  tipe: string;
  pesan: string;
  tautan?: string;
  is_read: boolean;
  dibaca_pada?: string;
  created_at: string;
}

export interface AuditLogItem {
  id: number;
  user_id?: number;
  user?: { id: number; nama: string; email: string };
  aksi: string;
  objek: string;
  objek_id?: number;
  sebelum?: Record<string, unknown> | null;
  sesudah?: Record<string, unknown> | null;
  created_at: string;
}

export interface WorkloadSummary {
  user: {
    id: number;
    nama: string;
    nip: string;
    email: string;
    peran: Role;
    teams: string[];
  };
  bulan: string;
  poin: number;
  persentase: number;
  indikator: 'aman' | 'hampir_penuh' | 'melebihi';
  jumlah_penugasan: number;
  penugasan_bersamaan_maks: number;
}

export interface WorkloadWarning {
  jenis: 'beban' | 'penugasan_bersamaan' | 'kegiatan_besar_bersamaan';
  tipe: string;
  tingkat_risiko: 'rendah' | 'sedang' | 'tinggi';
  pegawai_id: number | null;
  pegawai_nama: string | null;
  user: {
    id: number;
    nama: string;
  } | null;
  bulan: string;
  pesan: string;
  detail?: Record<string, unknown>;
}

export interface AssignmentPreviewResult {
  current_workload: number;
  simulated_workload: number;
  indicator_before: string;
  indicator_after: string;
  is_over_threshold: boolean;
  has_concurrency_warning: boolean;
  concurrent_assignments_peak: number;
  points_added: number;
}
