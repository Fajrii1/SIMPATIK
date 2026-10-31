<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kegiatan Statistik</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #0284c7; padding-bottom: 12px; }
        .header h2 { margin: 0; color: #0369a1; font-size: 16px; text-transform: uppercase; }
        .header p { margin: 4px 0 0 0; color: #64748b; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; text-align: right; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Badan Pusat Statistik Kota Palembang</h2>
        <p>Daftar Kegiatan Statistik - Tahun {{ $tahun }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Nama Kegiatan</th>
                <th style="width: 20%;">Tim</th>
                <th style="width: 15%;">Jenis</th>
                <th style="width: 15%;">Periode</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($activities as $idx => $act)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ $act->nama }}</td>
                <td>{{ $act->team ? $act->team->nama : '-' }}</td>
                <td>{{ $act->jenis->value }}</td>
                <td class="text-center">{{ $act->mulai->format('d/m/Y') }} - {{ $act->selesai->format('d/m/Y') }}</td>
                <td class="text-center">{{ $act->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ $tanggal_cetak }} melalui Sistem Penjadwalan BPS Kota Palembang</p>
    </div>
</body>
</html>
