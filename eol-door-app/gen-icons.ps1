# Genera los iconos de DOORTRIM DEFECTOS: logo TBAR rojo sobre fondo negro
# (inverso de EOL Defectos, que es logo rojo sobre fondo blanco).
Add-Type -AssemblyName System.Drawing
$src = Join-Path $PSScriptRoot "assets\logo-tbar-sinfondo.png"
$resBase = Join-Path $PSScriptRoot "android\app\src\main\res"
$orig = [System.Drawing.Image]::FromFile($src)

$names = "mipmap-mdpi", "mipmap-hdpi", "mipmap-xhdpi", "mipmap-xxhdpi", "mipmap-xxxhdpi"
$lgDims = 48, 72, 96, 144, 192
$fgDims = 108, 162, 216, 324, 432   # foreground del icono adaptativo (fondo negro en colors.xml)

function Draw($size, $ratio, $bg) {
    $bmp = New-Object System.Drawing.Bitmap($size, $size)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = 'AntiAlias'
    $g.InterpolationMode = 'HighQualityBicubic'
    $g.PixelOffsetMode = 'HighQuality'
    $g.Clear($bg)
    $w = [int]($size * $ratio)
    $h = [int]($w * $orig.Height / $orig.Width)
    $x = [int](($size - $w) / 2)
    $y = [int](($size - $h) / 2)
    $g.DrawImage($orig, $x, $y, $w, $h)
    $g.Dispose()
    return $bmp
}

for ($i = 0; $i -lt $names.Length; $i++) {
    $lg = Draw $lgDims[$i] 0.74 ([System.Drawing.Color]::Black)
    $lg.Save((Join-Path $resBase ($names[$i] + "\ic_launcher.png")), [System.Drawing.Imaging.ImageFormat]::Png)
    $lg.Save((Join-Path $resBase ($names[$i] + "\ic_launcher_round.png")), [System.Drawing.Imaging.ImageFormat]::Png)
    $lg.Dispose()

    $fg = Draw $fgDims[$i] 0.62 ([System.Drawing.Color]::Transparent)
    $fg.Save((Join-Path $resBase ($names[$i] + "\ic_launcher_foreground.png")), [System.Drawing.Imaging.ImageFormat]::Png)
    $fg.Dispose()
    Write-Output ("OK " + $names[$i])
}

# Icono grande para Expo (app.json) y el splash
$big = Draw 1024 0.74 ([System.Drawing.Color]::Black)
$big.Save((Join-Path $PSScriptRoot "assets\icon-doortrim.png"), [System.Drawing.Imaging.ImageFormat]::Png)
$big.Dispose()

$orig.Dispose()
Write-Output "Listo."
