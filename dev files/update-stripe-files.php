<?php
// Define the directory where the namespaced Stripe PHP library is located
$directory = __DIR__ . '/build';

// Function to recursively add namespace and ABSPATH check
function update_files($dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($files as $file) {
        // Process only PHP files
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getRealPath();
            $contents = file_get_contents($filePath);

            // Ensure namespace declaration is the first statement
            if (preg_match('/namespace\s+[^;]+;/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                $namespaceStart = $matches[0][1];
                $namespaceEnd = $namespaceStart + strlen($matches[0][0]);

                // Split the file into parts
                $beforeNamespace = substr($contents, 0, $namespaceStart);
                $namespaceDeclaration = substr($contents, $namespaceStart, strlen($matches[0][0]));
                $afterNamespace = substr($contents, $namespaceEnd);

                // Add ABSPATH check right after namespace declaration
                $newContents = "<?php\n" . trim($namespaceDeclaration) . "\n\nif ( ! defined( 'ABSPATH' ) ) exit;\n\n" . trim($afterNamespace);

                // Write the updated content back to the file
                file_put_contents($filePath, $newContents);
                echo "Updated: $filePath\n";
            }
        }
    }
}

// Run the function
update_files($directory);
