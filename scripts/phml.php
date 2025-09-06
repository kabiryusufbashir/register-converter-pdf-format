<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="https://cdn.tailwindcss.com"></script>
        <title>PHML Register</title>
    </head>
    <body class="min-h-screen my-64 bg-gray-100">
        <div class="text-center text-3xl mb-6">
            PHML Register Converter (PDF Version)
        </div>
        <div class="text-center">
            <?php

                $inputFile = "../registers/phml_extracted_sept.pdf";
                $outputFile = "../outputs/phml_extracted_sept_2025.csv";
                $binaryPath = 'C:/poppler/Library/bin/pdftotext.exe';

                // Extract text using pdftotext -layout
                $cmd = "\"$binaryPath\" -layout \"$inputFile\" -";
                $text = shell_exec($cmd);

                if (!$text) {
                    die("Failed to extract text from PDF.\n");
                }

                // Save debug for inspection
                file_put_contents("debug_output.txt", $text);

                // Split into lines
                $lines = preg_split('/\r\n|\n|\r/', $text);

                function normalizeName(string $name): string {
                    $name = preg_replace("/'+/", "'", $name);                  // collapse multiple apostrophes
                    $name = preg_replace("/[^\p{L}'\- ]+/u", "", $name);       // keep only valid chars
                    return ucwords(strtolower(trim($name)));                   // title case
                }

                $providerNumber = null;
                $familyName = '';
                $familyCode = '';
                $collecting = false;

                $outHandle = fopen($outputFile, 'w');

                // Write rearranged header
                fputcsv($outHandle, [
                    'Category',
                    'Enrollee Type',
                    'Primary HCP',
                    'NHIA No',
                    'First Name',
                    'Surname',
                    'Dependant Type',
                    'Sex',
                    'DOB',
                    'HMO',
                    'HMO Acronym'
                ]);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;

                    // Match Provider Number
                    if (preg_match('/Provider Number:\s+([A-Z0-9\/]+)/', $line, $m)) {
                        $providerNumber = $m[1];
                        continue;
                    }

                    // Match Family Code
                    if (preg_match('/Family\s+([A-Z]+)\s+Code\s+-\s+(\d+)/i', $line, $m)) {
                        $familyName = $m[1];
                        $familyCode = $m[2];
                        continue;
                    }

                    // Detect header line → start collecting
                    if (preg_match('/^S\/N\s+NHIA\s+Number/i', $line)) {
                        $collecting = true;
                        continue;
                    }

                    // Skip page footers
                    if (preg_match('/^Page\s+\d+/i', $line)) {
                        continue;
                    }

                    if (!$collecting) continue;

                    // Enrollee row detection
                    if (preg_match(
                        '/^\s*(\d+)\s+([\d-]+)\s+(.*?)\s+([MF])\s+(\d{2}\/\d{2}\/\d{4})(?:\s+(\d+))?/i',
                        $line,
                        $m
                    )) {
                        $nhia   = $m[2];
                        $middle = trim($m[3]); // relationship + names
                        $sex    = $m[4];
                        $dob    = $m[5];
                        $emp    = $m[6] ?? '';

                        // --- Improved split logic ---
                        $relationship = '';
                        $first_name   = '';
                        $last_name    = '';

                        if (preg_match('/^(PRINCIPAL|SPOUSE|CHILD\s*\d+|EXTRA DEPENDENT\s*\d+|DEPENDENT|MEMBER)/i', $middle, $relMatch)) {
                            $relationship = strtoupper($relMatch[1]);
                            $afterRel = trim(substr($middle, strlen($relMatch[0])));
                            $nameParts = preg_split('/\s+/', $afterRel);

                            if (count($nameParts) >= 2) {
                                $first_name = array_shift($nameParts);
                                $last_name  = implode(' ', $nameParts);
                            } elseif (count($nameParts) === 1) {
                                $first_name = $nameParts[0];
                            }
                        } else {
                            $parts = preg_split('/\s+/', $middle);
                            if (count($parts) >= 2) {
                                $last_name  = array_pop($parts);
                                $first_name = array_pop($parts);
                                $relationship = implode(' ', $parts);
                            } elseif (count($parts) === 1) {
                                $first_name = array_pop($parts);
                            }
                        }

                        // Static fields
                        $category = 'MAIN';
                        $hmo = 'Police Health Maintenance Limited';
                        $hmo_accronym = 'PHML';

                        // Rearranged row
                        $rearrangedData = [
                            $category,
                            strtoupper(trim($relationship)),  // Enrollee Type
                            $providerNumber ?? '',            // Primary HCP
                            $nhia,                            // NHIA No
                            normalizeName($first_name),       // First Name
                            normalizeName($last_name),        // Surname
                            strtoupper(trim($relationship)),  // Dependant Type
                            $sex,
                            $dob,
                            $hmo,
                            $hmo_accronym
                        ];

                        fputcsv($outHandle, $rearrangedData);
                    }
                }

                fclose($outHandle);

                echo "<div class='my-2 text-xl'>";
                echo "PHML Register has been converted <br> Click <a class='underline' href='../output/$outputFile'>Here</a> to download the file.";
                echo "</div>";
                echo "<br><br> <a class='text-2xl' href='../index.php'>Return Home Page</a>";
            ?>
        </div>
    </body>
</html>