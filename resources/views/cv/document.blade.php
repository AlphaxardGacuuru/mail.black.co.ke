{{-- A CV drawn by App\Services\Cv\CvRenderer: one column, plain text and
     standard headings, which application tracking systems read best. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 18mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.4; color: #1f2933; }
        h1 { font-size: 20pt; margin: 0; color: #111827; }
        .headline { font-size: 11pt; margin: 2pt 0 4pt; color: #374151; }
        .contact { font-size: 9pt; color: #4b5563; margin-bottom: 10pt; }
        h2 { font-size: 10.5pt; text-transform: uppercase; letter-spacing: 0.8pt; color: #111827; border-bottom: 1pt solid #9ca3af; padding-bottom: 2pt; margin: 12pt 0 6pt; }
        .entry { margin-bottom: 7pt; page-break-inside: avoid; }
        .entry-head { width: 100%; border-collapse: collapse; }
        .entry-head td { padding: 0; vertical-align: top; }
        .title { font-weight: bold; }
        .period { text-align: right; white-space: nowrap; color: #4b5563; font-size: 9pt; }
        .subtitle { color: #4b5563; font-size: 9.5pt; }
        p { margin: 2pt 0; }
        ul { margin: 3pt 0 0 14pt; padding: 0; }
        li { margin-bottom: 2pt; }
    </style>
</head>
<body>
    <h1>{{ $cv['name'] }}</h1>
    @if ($cv['headline'] !== '')
        <div class="headline">{{ $cv['headline'] }}</div>
    @endif
    @if ($cv['contact'])
        <div class="contact">{{ implode('  |  ', $cv['contact']) }}</div>
    @endif

    @foreach ($groups as $heading => $entries)
        <h2>{{ $heading }}</h2>
        @foreach ($entries as $entry)
            <div class="entry">
                @if ($entry['title'] !== '' || $entry['period'] !== '')
                    <table class="entry-head">
                        <tr>
                            <td class="title">{{ $entry['title'] }}</td>
                            <td class="period">{{ $entry['period'] }}</td>
                        </tr>
                    </table>
                @endif
                @if ($entry['subtitle'] !== '')
                    <div class="subtitle">{{ $entry['subtitle'] }}</div>
                @endif
                @if ($entry['text'] !== '')
                    <p>{{ $entry['text'] }}</p>
                @endif
                @if ($entry['bullets'])
                    <ul>
                        @foreach ($entry['bullets'] as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endforeach
</body>
</html>
