# Fix reports.php: remove old style block (lines 160-496) and keep rest
$file = 'c:\xampp\htdocs\mangocareadmin\admin\reports.php'
$lines = Get-Content $file -Encoding UTF8
# Lines 159-496 in 1-based = index 158-495 in 0-based. Keep 0..157 and 496..end
$keep = $lines[0..157] + $lines[496..($lines.Length-1)]
Set-Content $file $keep -Encoding UTF8
Write-Host "reports.php done. Lines kept: $($keep.Length)"

# Fix index.php: remove old style block (lines 288-723) and keep rest
$file2 = 'c:\xampp\htdocs\mangocareadmin\admin\index.php'
$lines2 = Get-Content $file2 -Encoding UTF8
# Lines 288-723 in 1-based = index 287-722 in 0-based. Keep 0..286 and 723..end
$keep2 = $lines2[0..286] + $lines2[723..($lines2.Length-1)]
Set-Content $file2 $keep2 -Encoding UTF8
Write-Host "index.php done. Lines kept: $($keep2.Length)"
