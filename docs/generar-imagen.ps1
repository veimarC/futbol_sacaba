$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$schema = Get-Content (Join-Path $PSScriptRoot 'esquema-base-datos.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$rows = @(
    @('users', 'campeonatos', 'fases', 'partidos'),
    @('equipos', 'campeonato_equipo', 'jugadores', 'incidencias'),
    @('notifications', 'match_whatsapp_deliveries', 'nominas_partido', 'nomina_partido_jugadores'),
    @('sessions', 'password_reset_tokens', 'migrations', 'jobs'),
    @('cache', 'cache_locks', 'job_batches', 'failed_jobs')
)
$positions = @{}
$rowY = 300
$width = 690
$lineHeight = 29
$colors = @('#D6E7FA', '#D2EED6', '#FFE0BF', '#CDEAF5', '#F6CDD0', '#FFF0B7', '#DDD0F3', '#E4D1F4')
$index = 0
foreach ($row in $rows) {
    $maxHeight = 0
    for ($col = 0; $col -lt 4; $col++) {
        $name = $row[$col]
        $height = 48 + $schema.$name.columns.Count * $lineHeight
        $positions[$name] = @{X = 140 + $col * 845; Y = $rowY; H = $height; Color = $colors[$index % $colors.Count]; Row = [int][math]::Floor($index / 4)}
        $maxHeight = [math]::Max($maxHeight, $height)
        $index++
    }
    $rowY += $maxHeight + 175
}
$bitmap = New-Object System.Drawing.Bitmap(3520, ($rowY + 30))
$g = [System.Drawing.Graphics]::FromImage($bitmap)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
$g.Clear([System.Drawing.Color]::White)
$font = New-Object System.Drawing.Font('Arial', 16)
$small = New-Object System.Drawing.Font('Arial', 13)
$bold = New-Object System.Drawing.Font('Arial', 17, ([System.Drawing.FontStyle]::Bold))
$title = New-Object System.Drawing.Font('Arial', 28, ([System.Drawing.FontStyle]::Bold))
$black = [System.Drawing.Brushes]::Black
$pen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(50, 50, 50), 2)
$g.DrawString('PLATAFORMA FUTBOL SACABA - DIAGRAMA COMPLETO', $title, $black, 85, 26)
$g.DrawString('20 tablas | 186 campos | PK: primaria | FK: clave foranea | UK: unica | ?: admite NULL | 1 / 0..1 a N', $font, $black, 85, 83)
$g.DrawString('Lineas continuas: FK declaradas. Lineas discontinuas: relaciones logicas. Notifications: relacion polimorfica.', $small, $black, 85, 116)
$relations = @()
foreach ($table in $schema.PSObject.Properties) {
    foreach ($fk in $table.Value.foreign_keys) {
        $relations += ,@($fk.foreign_table, $table.Name, $fk.columns[0], $true)
    }
}
$relations += ,@('partidos', 'incidencias', 'partido_id', $false)
$relations += ,@('jugadores', 'incidencias', 'jugador_id', $false)
$relations += ,@('users', 'incidencias', 'creado_por', $false)
$relations += ,@('equipos', 'partidos', 'wo_equipo_perdedor_id', $false)
$relations += ,@('users', 'partidos', 'wo_registrado_por', $false)
$relations += ,@('users', 'sessions', 'user_id', $false)
$relations += ,@('users', 'notifications', 'notifiable_id', $false)
$edgeIndex = 0
foreach ($rel in $relations) {
    $source = $positions[$rel[0]]; $target = $positions[$rel[1]]
    $columnIndex = 0
    foreach ($c in $schema.($rel[1]).columns) { if ($c.name -eq $rel[2]) { break }; $columnIndex++ }
    $sy = $source.Y + 62
    $ty = $target.Y + 62 + $columnIndex * $lineHeight
    $sx = $source.X + $width
    $tx = $target.X
    $laneOffset = 18 + ($edgeIndex % 9) * 11
    $lane1 = $sx + $laneOffset
    $lane2 = $tx - $laneOffset
    $bridgeY = [math]::Min($source.Y, $target.Y) - 25 - ($edgeIndex % 9) * 12
    $edgePen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(110, 120, 135), 1.6)
    if (!$rel[3]) { $edgePen.DashStyle = [System.Drawing.Drawing2D.DashStyle]::Dash; $edgePen.Color = [System.Drawing.Color]::FromArgb(155, 115, 170) }
    $points = [System.Drawing.PointF[]]@(
        [System.Drawing.PointF]::new($sx, $sy), [System.Drawing.PointF]::new($lane1, $sy),
        [System.Drawing.PointF]::new($lane1, $bridgeY), [System.Drawing.PointF]::new($lane2, $bridgeY),
        [System.Drawing.PointF]::new($lane2, $ty), [System.Drawing.PointF]::new($tx, $ty)
    )
    $g.DrawLines($edgePen, $points)
    $g.DrawLine($edgePen, $tx - 16, $ty, $tx, $ty - 8)
    $g.DrawLine($edgePen, $tx - 16, $ty, $tx, $ty + 8)
    $g.DrawString('N', $small, $black, ($tx - 19), ($ty - 26))
    $nullable = $schema.($rel[1]).columns[$columnIndex].nullable
    $g.DrawString($(if ($nullable) {'0..1'} else {'1'}), $small, $black, ($sx + 3), ($sy - 25))
    $edgePen.Dispose()
    $edgeIndex++
}
foreach ($name in $positions.Keys) {
    $p = $positions[$name]; $t = $schema.$name
    $g.FillRectangle([System.Drawing.Brushes]::White, $p.X, $p.Y, $width, $p.H)
    $brush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml($p.Color))
    $g.FillRectangle($brush, $p.X, $p.Y, $width, 44)
    $g.DrawRectangle($pen, $p.X, $p.Y, $width, $p.H)
    $g.DrawLine($pen, $p.X, ($p.Y + 44), ($p.X + $width), ($p.Y + 44))
    $g.DrawLine($pen, ($p.X + 90), ($p.Y + 44), ($p.X + 90), ($p.Y + $p.H))
    $g.DrawString($name, $bold, $black, ($p.X + 12), ($p.Y + 8))
    $j = 0
    foreach ($c in $t.columns) {
        $keys = @()
        foreach ($idx in $t.indexes) {
            if ($idx.columns -contains $c.name) {
                if ($idx.primary) { $keys += 'PK' }
                elseif ($idx.unique -and $idx.columns.Count -eq 1) { $keys += 'UK' }
            }
        }
        foreach ($fk in $t.foreign_keys) { if ($fk.columns -contains $c.name) { $keys += 'FK' } }
        $y = $p.Y + 49 + $j * $lineHeight
        $g.DrawString(($keys -join ','), $small, $black, ($p.X + 8), $y)
        $g.DrawString(($c.name + $(if ($c.nullable) {' ?'} else {''})), $font, $black, ($p.X + 101), $y)
        $g.DrawString($c.type_name, $small, [System.Drawing.Brushes]::DimGray, ($p.X + 565), ($y + 2))
        $j++
    }
    $brush.Dispose()
}
$output = Join-Path $PSScriptRoot 'base-datos-completa.png'
$bitmap.Save($output, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose(); $bitmap.Dispose()
$font.Dispose(); $small.Dispose(); $bold.Dispose(); $title.Dispose(); $pen.Dispose()
Write-Output $output
