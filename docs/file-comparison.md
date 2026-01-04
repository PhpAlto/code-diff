# File Comparison

Learn how to compare full files and multi-file bundles by defining their contents directly in the code.

## Compare Two Files

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Renderer\UnifiedRenderer;

// --- Define file contents directly ---
$oldContent = <<< 'EOF'
Line 1
Line 2
Line 3
EOF;

$newContent = <<< 'EOF'
Line 1
Modified Line 2
Line 3
New Line 4
EOF;
// ------------------------------------

$result = Diff::build()
    ->contextLines(3) // Adjusted for example, default is 3
    ->compare($oldContent, $newContent);

$renderer = new UnifiedRenderer('old_file.txt', 'new_file.txt');
echo $renderer->render($result);
```

**Output:**
```diff
--- old_file.txt
+++ new_file.txt
@@ -1,3 +1,4 @@
 Line 1
-Line 2
+Modified Line 2
 Line 3
+New Line 4
```

## Compare Multiple Files

```php
use Alto\Code\Diff\Diff;
use Alto\Code\Diff\Model\DiffBundle;
use Alto\Code\Diff\Model\DiffFile;
use Alto\Code\Diff\Renderer\UnifiedRenderer;

// --- Define file contents directly ---
$file1Old = <<< 'EOF'
File 1: Original content A
File 1: Original content B
EOF;

$file1New = <<< 'EOF'
File 1: Original content A
File 1: Modified content B
File 1: New content C
EOF;

$file2Old = <<< 'EOF'
File 2: Original content X
EOF;

$file2New = <<< 'EOF'
File 2: Modified content Y
EOF;
// ------------------------------------

$diffFiles = [];

// Compare file1
$result1 = Diff::build()->compare($file1Old, $file1New);
$diffFiles[] = new DiffFile('dir_v1/file1.txt', 'dir_v2/file1.txt', $result1);

// Compare file2
$result2 = Diff::build()->compare($file2Old, $file2New);
$diffFiles[] = new DiffFile('dir_v1/file2.txt', 'dir_v2/file2.txt', $result2);

$bundle = new DiffBundle($diffFiles);
$renderer = new UnifiedRenderer();
echo $renderer->render($bundle);
```

**Output:**
```diff
--- dir_v1/file1.txt
+++ dir_v2/file1.txt
@@ -1,2 +1,3 @@
 File 1: Original content A
-File 1: Original content B
+File 1: Modified content B
+File 1: New content C
--- dir_v1/file2.txt
+++ dir_v2/file2.txt
@@ -1 +1 @@
-File 2: Original content X
+File 2: Modified content Y
```