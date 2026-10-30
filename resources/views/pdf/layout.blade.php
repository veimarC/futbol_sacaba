<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
<style>
@page { margin: 28px 26px 35px; }
body {font-family:DejaVu Sans,sans-serif;font-size:10px;color:#172b46}
h1 {font-size:19px;margin:8px 0} h2 {font-size:13px} p {margin:6px 0}
.center {text-align:center} .muted {color:#555} .score {font-size:22px;font-weight:bold}
table {width:100%;border-collapse:collapse;table-layout:auto} th,td {padding:5px;border:1px solid #bbc5cf;word-wrap:break-word}
th {background:#edf1f5} thead {display:table-header-group} tr {page-break-inside:avoid}
.photo {width:25px;height:28px;vertical-align:middle} .crest {width:32px;height:32px;vertical-align:middle}
.team {width:50%;text-align:center;font-weight:bold;font-size:12px}
.tag {font-weight:bold;border:1px solid #555;padding:5px;margin:10px 0}
footer {position:fixed;bottom:-22px;font-size:8px;color:#666}
.score-box {display:inline-block;min-width:48px;height:30px;border:1px solid #bbc5cf;vertical-align:middle;text-align:center}
.roster td {text-align:center;padding:6px 4px;vertical-align:middle;height:16px}
.roster td.player-name {text-align:left}
.signatures {page-break-inside:avoid;margin-top:18px;text-align:center;font-size:9px}
.signature-table td {width:50%;border:0;padding:8px 18px}
.signature-line {border-top:1px solid #555;margin:36px 12px 7px}
.table-official {width:50%;margin:0 auto}
</style>
@yield('styles')
</head><body><div class="center"><b>FÚTBOL SACABA</b>@yield('heading')</div>
@yield('content')
<footer>Emitido: {{ now()->format('d/m/Y H:i') }} · Fútbol Sacaba</footer></body></html>
