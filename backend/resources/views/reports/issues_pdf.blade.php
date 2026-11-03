<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kendala Mingguan</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 9px; color: #1e293b; margin: 16px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0284c7; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #0369a1; font-size: 15px; text-transform: uppercase; }
        .header p { margin: 4px 0 0 0; color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .badge-terbuka { color: #991b1b; font-weight: bold; }
        .badge-diproses { color: #854d0e; font-weight: bold; }
        .badge-selesai { color: #166534; font-weight: bold; }
        .footer { margin-top: 24px; text-align: right; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Badan Pusat Statistik Kota Palembang</h2>
        <p>Laporan Kendala Mingguan</p>
        <p>
            @if($week_from || $week_to)
                Rentang Minggu: {{ $week_from ?? 'awal' }} s.d. {{ $week_to ?? 'akhir' }}
            @endif
            @if($status)
                &nbsp;|&nbsp; Status: {{ $status }}
            @endif
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 10%;">Minggu Mulai</th>
                <th style="width: 12%;">Tim</th>
                <th style="width: 18%;">Kegiatan</th>
                <th style="width: 22%;">Kendala</th>
                <th style="width: 10%;">PIC</th>
                <th style="width: 9%;">Tenggat</th>
                <th style="width: 9%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $idx => $row)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ $row['minggu_mulai'] }}</td>
                <td>{{ $row['tim'] }}</td>
                <td>{{ $row['kegiatan'] }}</td>
                <td>
                    {{ $row['kendala'] }}
                    @if($row['tindak_lanjut'] !== '-')
                        <br><br><strong>Tindak lanjut:</strong> {{ $row['tindak_lanjut'] }}
                    @endif
                    @if($row['catatan_penutupan'] !== '-')
                        <br><br><strong>Catatan penutupan:</strong> {{ $row['catatan_penutupan'] }}
                    @endif
                </td>
                <td>{{ $row['pic'] }}</td>
                <td>{{ $row['tenggat'] }}</td>
                <td class="text-center badge-{{ $row['status_key'] }}">{{ $row['status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ $tanggal_cetak }} melalui Sistem Penjadwalan BPS Kota Palembang</p>
    </div>
</body>
</html>
