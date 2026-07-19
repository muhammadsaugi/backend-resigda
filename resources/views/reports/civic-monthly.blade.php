<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Suara Warga - {{ $bulan }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .summary { margin-bottom: 16px; font-size: 13px; }
        .footer { margin-top: 30px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <h1>Laporan Bulanan Suara Warga — REGSIDA</h1>
    <div class="subtitle">Periode: {{ $bulan }} · Kabupaten Sidoarjo</div>

    <div class="summary">
        <strong>Total Interaksi:</strong> {{ $totalInteraksi }}
    </div>

    <table>
        <thead>
        <tr>
            <th>Topik</th>
            <th>Sentimen</th>
            <th>Jumlah Interaksi</th>
            <th>Kecamatan</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($aggregations as $row)
            <tr>
                <td>{{ $row->topic }}</td>
                <td>{{ $row->sentiment }}</td>
                <td>{{ $row->jumlah_interaksi }}</td>
                <td>{{ $row->kecamatan ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">Belum ada data agregasi untuk periode ini.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini digenerate otomatis oleh sistem REGSIDA pada {{ now()->format('d F Y H:i') }} WIB.
        Data bersumber dari agregasi anonim interaksi warga — tidak memuat identitas atau teks pertanyaan asli.
    </div>
</body>
</html>