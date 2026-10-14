$file = 'c:\xampp\htdocs\mangocareadmin\admin\qr-generator.php'
$lines = Get-Content $file -Encoding UTF8
# Keep lines 0..211 (index, 0-based) and 747..end
# Lines 213-747 in 1-based = index 212-746 in 0-based
$keep = $lines[0..211] + $lines[747..($lines.Length-1)]
Set-Content $file $keep -Encoding UTF8
Write-Host "Done. Lines kept: $($keep.Length)"
