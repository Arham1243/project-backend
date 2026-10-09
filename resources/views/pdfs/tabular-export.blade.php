<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        header {
            text-align: center;
            margin-bottom: 16px;
        }

        header h2 {
            margin: 0 0 4px;
            font-size: 16px;
        }

        header small {
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 4px 5px;
            font-size: 9px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <h2>{{ $title }}</h2>
        <small>Generated on: {{ $generatedAt }}</small>
    </header>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    @php
                        $label = $headerMap[$col] ?? ucwords(str_replace(['_id', '_', '.'], ['', ' ', ' '], $col));
                    @endphp
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $row)
                <tr>
                    @foreach ($columns as $key)
                        <td>{{ $row[$key] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}">No data found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
