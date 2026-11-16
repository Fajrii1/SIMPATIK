import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { PageMeta } from '../types';

interface PaginationProps {
  meta?: PageMeta | null;
  onPageChange: (page: number) => void;
  disabled?: boolean;
  label?: string;
}

export const Pagination: React.FC<PaginationProps> = ({
  meta,
  onPageChange,
  disabled = false,
  label = 'data',
}) => {
  if (!meta || meta.total === 0) {
    return null;
  }

  const { current_page: currentPage, last_page: lastPage, total, from, to } = meta;
  const isFirst = currentPage <= 1;
  const isLast = currentPage >= lastPage;

  return (
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-3">
      <p className="text-[11px] text-slate-500">
        Menampilkan {from ?? 0}–{to ?? 0} dari {total} {label}
      </p>
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={disabled || isFirst}
          aria-label="Halaman sebelumnya"
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed min-h-[36px]"
        >
          <ChevronLeft className="w-3.5 h-3.5" />
          Sebelumnya
        </button>
        <span className="text-[11px] font-semibold text-slate-700">
          {currentPage} / {lastPage}
        </span>
        <button
          type="button"
          onClick={() => onPageChange(currentPage + 1)}
          disabled={disabled || isLast}
          aria-label="Halaman berikutnya"
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed min-h-[36px]"
        >
          Berikutnya
          <ChevronRight className="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  );
};
