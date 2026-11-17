$ErrorActionPreference = 'Stop'
# Reutiliza la estructura real y el trazado del diagrama completo.
$source = Get-Content (Join-Path $PSScriptRoot 'generar-imagen.ps1') -Raw -Encoding UTF8
$start = $source.IndexOf('$rows = @(')
$end = $source.IndexOf('$positions = @{}')
$layout = @'
$rows = @(
    @('users', 'campeonatos', 'fases', 'partidos', 'incidencias'),
    @('equipos', 'campeonato_equipo', 'jugadores', 'nominas_partido', 'nomina_partido_jugadores'),
    @('sessions', 'notifications', 'match_whatsapp_deliveries', 'password_reset_tokens', 'migrations'),
    @('cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs')
)

'@
$source = $source.Substring(0, $start) + $layout + $source.Substring($end)
$source = $source.Replace('$rowY = 300', '$rowY = 75').Replace('$width = 690', '$width = 340').Replace('$lineHeight = 29', '$lineHeight = 16')
$source = $source.Replace('$col -lt 4', '$col -lt 5').Replace('$height = 48 +', '$height = 31 +').Replace('140 + $col * 845', '30 + $col * 390').Replace('$index / 4', '$index / 5').Replace('$maxHeight + 175', '$maxHeight + 65')
$source = $source.Replace('Bitmap(3520, ($rowY + 30))', 'Bitmap(1950, ($rowY - 40))')
$source = $source.Replace("Font('Arial', 16)", "Font('Arial', 9.5)").Replace("Font('Arial', 13)", "Font('Arial', 8)").Replace("Font('Arial', 17,", "Font('Arial', 10,")
$source = $source -replace '(?m)^\$g.DrawString\(''.*\r?\n', ''
$source = $source.Replace('$source.Y + 62', '$source.Y + 38').Replace('$target.Y + 62', '$target.Y + 38')
$source = $source.Replace('18 + ($edgeIndex % 9) * 11', '6 + ($edgeIndex % 9) * 2').Replace('25 - ($edgeIndex % 9) * 12', '10 - ($edgeIndex % 9) * 5')
$source = $source.Replace('FromArgb(110, 120, 135), 1.6', 'Black, 1.2').Replace('FromArgb(155, 115, 170)', 'Black')
$source = $source.Replace('$tx - 16', '$tx - 9').Replace('$ty - 8', '$ty - 5').Replace('$ty + 8', '$ty + 5').Replace('$tx - 19', '$tx - 12').Replace('$ty - 26', '$ty - 17').Replace('$sy - 25', '$sy - 17')
$source = $source.Replace('$width, 44', '$width, 27').Replace('$p.Y + 44', '$p.Y + 27').Replace('$p.X + 90', '$p.X + 35').Replace('$p.Y + 49', '$p.Y + 30').Replace('$p.X + 101', '$p.X + 43').Replace('$p.X + 8', '$p.X + 4')
$source = $source.Replace('$g.DrawString($name, $bold, $black, ($p.X + 12), ($p.Y + 8))', '$labelWidth = $g.MeasureString($name, $bold).Width; $g.DrawString($name, $bold, $black, ($p.X + ($width - $labelWidth) / 2), ($p.Y + 4))')
$source = $source -replace '(?m)^\s*\$g.DrawString\(\$c.type_name.*\r?\n', ''
$source = $source.Replace("elseif (`$idx.unique -and `$idx.columns.Count -eq 1) { `$keys += 'UK' }", '')
$source = $source -replace '(?m)^\s*foreach \(\$fk in \$t.foreign_keys\).*\r?\n', ''
$source = $source.Replace("(`$c.name + `$(if (`$c.nullable) {' ?'} else {''}))", '$c.name')
$source = $source.Replace("'base-datos-completa.png'", "'base-datos-estilo-referencia.png'")
$save = @'
$final = New-Object System.Drawing.Bitmap(975, 650)
$fg = [System.Drawing.Graphics]::FromImage($final)
$fg.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$fg.DrawImage($bitmap, 0, 0, 975, 650)
$final.Save($output, [System.Drawing.Imaging.ImageFormat]::Png)
$fg.Dispose(); $final.Dispose()
'@
$source = $source.Replace('$bitmap.Save($output, [System.Drawing.Imaging.ImageFormat]::Png)', $save)
$diagramDirectory = $PSScriptRoot
$source = $source.Replace('$PSScriptRoot', '$diagramDirectory')
Invoke-Expression $source
