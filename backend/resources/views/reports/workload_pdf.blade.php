<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Beban Kerja Pegawai</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #0284c7; padding-bottom: 12px; }
        .header h2 { margin: 0; color: #0369a1; font-size: 16px; text-transform: uppercase; }
        .header p { margin: 4px 0 0 0; color: #64748b; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge-aman { color: #166534; font-weight: bold; }
        .badge-hampir { color: #854d0e; font-weight: bold; }
        .badge-melebihi { color: #991b1b; font-weight: bold; }
        .footer { margin-top: 30px; text-align: right; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Badan Pusat Statistik Kota Palembang</h2>
        <p>Laporan Beban Kerja Pegawai - Periode {{ $bulan }}</p>
        <p>Ambang Batas Standar: {{ $ambang }} Poin</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Nama Pegawai</th>
                <th style="width: 20%;">NIP</th>
                <th style="width: 20%;">Tim Kerja</th>
                <th style="width: 10%;">Poin</th>
                <th style="width: 10%;">% Ambang</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $idx => $row)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ $row['nama'] }}</td>
                <td>{{ $row['nip'] }}</td>
                <td>{{ $row['tim'] }}</td>
                <td class="text-right">{{ $row['poin'] }}</td>
                <td class="text-right">{{ $row['persentase'] }}%</td>
                <td class="text-center">
                    @if($row['indikator'] === 'aman')
                        <span class="badge-aman">Aman</span>
                    @elseif($row['indikator'] === 'hampir_penuh')
                        <span class="badge-hampir">Hampir Penuh</span>
                    @else
                        <span class="badge-melebihi">Melebihi Batas</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ $tanggal_cetak }} melalui Sistem Penjadwalan BPS Kota Palembang</p>
    </div>
</body>
</html>

