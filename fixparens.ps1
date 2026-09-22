$p = $args[0]
$lines = [System.IO.File]::ReadAllLines($p)
$rp = [string]([char]41)
for ($i = 0; $i -lt $lines.Count; $i++) {
    $l = $lines[$i]
    $open = ([regex]::Matches($l, '\(')).Count
    $close = ([regex]::Matches($l, '\)')).Count
    if ($open -le $close) { continue }
    $missing = $open - $close
    $pad = ' + ($rp * $missing)
    if ($l -match '\ { \s*$') {
        $idx = $l.LastIndexOf('{ ')
        $lines[$i] = $l.Substring(0, $idx).TrimEnd() + $pad + ' { '
        Write-Output ("ctrl {0}" -f ($i + 1))
    } elseif ($l -match '; \s*$') {
        $semi = $l.LastIndexOf('; ')
        $lines[$i] = $l.Substring(0, $semi).TrimEnd() + $pad + '; '
        Write-Output ("stmt {0}" -f ($i + 1))
    }
}
[System.IO.File]::WriteAllLines($p, $lines)
[System.IO.File]::WriteAllLines($p, $lines)
