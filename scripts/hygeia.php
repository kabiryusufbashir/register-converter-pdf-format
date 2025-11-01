<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="https://cdn.tailwindcss.com"></script>
        <title>PHML Register</title>
    </head>
    <body class="min-h-screen my-64 bg-gray-100">
        <div class="text-center text-3xl mb-6">
            PHML Register Converter (PDF Version - Enhanced)
        </div>
        <div class="text-center">
            <?php

                $inputFile = "../registers/phml_full.pdf";
                $outputFile = "../outputs/phml_extracted_nov_2025.csv";
                $binaryPath = 'C:/poppler/Library/bin/pdftotext.exe';

                // Extract text using pdftotext -layout
                $cmd = "\"$binaryPath\" -layout \"$inputFile\" -";
                $text = shell_exec($cmd);

                if (!$text) {
                    die("Failed to extract text from PDF.\n");
                }

                // Save debug output (for verification)
                file_put_contents("debug_output.txt", $text);
                
                // Initialize debug tracking
                $skippedLines = [];
                $processedCount = 0;

                // Split into lines
                $lines = preg_split('/\r\n|\n|\r/', $text);

                // === Helper to normalize names ===
                function normalizeName(string $name): string {
                    // Remove special characters like ∅, ~, strikethrough marks
                    $name = preg_replace('/[∅~øØ]/u', '', $name);
                    $name = preg_replace("/'+/", "'", $name);                  // collapse multiple apostrophes
                    $name = preg_replace("/[^\p{L}'\- ]+/u", "", $name);       // keep only letters, apostrophes, and hyphens
                    $name = trim($name);
                    if (empty($name)) return '';
                    return ucwords(strtolower($name));                         // convert to Title Case
                }

                $providerNumber = null;
                $familyName = '';
                $familyCode = '';
                $collecting = false;

                // Open CSV output file
                $outHandle = fopen($outputFile, 'w');

                // Write header
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

                foreach ($lines as $lineNum => $line) {
                    $line = trim($line);
                    if ($line === '') continue;

                    // Match Provider Number
                    if (preg_match('/Provider Number:\s+([A-Z0-9\/]+)/', $line, $m)) {
                        $providerNumber = $m[1];
                        continue;
                    }

                    // Match Family Code (improved pattern)
                    if (preg_match('/Family\s+([A-Z]+)\s+Code\s*-?\s*(\d+)/i', $line, $m)) {
                        $familyName = $m[1];
                        $familyCode = $m[2];
                        continue;
                    }

                    // Detect header line → start collecting
                    if (preg_match('/^S\/N\s+NHIA\s+Number/i', $line)) {
                        $collecting = true;
                        continue;
                    }

                    // Skip page footer/header lines
                    if (preg_match('/^Page\s+\d+/i', $line)) {
                        continue;
                    }

                    if (!$collecting) continue;

                    // Skip facility/location headers (contain institutional keywords without dates)
                    if (preg_match('/\b(BATTALION|NAF|HOSPITAL|BRIGADE|BDE|DIVISION|DIV|ARMED FORCES|MEDICAL CENTRE|AIRFORCE|REFERENCE|RECCE|QUICK RESPONSE)\b/i', $line) 
                        && !preg_match('/\d{2}\/\d{2}\/\d{4}/', $line)) {
                        continue;
                    }

                    // === IMPROVED Enrollee Row Detection ===
                    // Pattern that handles both with and without NHIA numbers
                    $matched = false;
                    $sn = '';
                    $nhia = '';
                    $relationship = '';
                    $first_name = '';
                    $last_name = '';
                    $sex = '';
                    $dob = '';
                    $empCode = '';
                    
                    // Try pattern WITH NHIA number first
                    if (preg_match(
                        '/^\s*(\d+)\s+([\d-]+)\s+(PRINCIPAL|SPOUSE|CHILD\s*\d*|EXTRA\s*DEPENDENT\s*\d*|DEPENDENT|MEMBER|FATHER|MOTHER)\s+(.+?)\s+([MF])\s+(\d{2}\/\d{2}\/\d{4}|N\/A)\s*(.*)$/i',
                        $line,
                        $m
                    )) {
                        // Pattern with NHIA number
                        $matched = true;
                        $sn           = $m[1];
                        $nhia         = trim($m[2]);
                        $relationship = strtoupper(trim($m[3]));
                        $namesPart    = trim($m[4]);
                        $sex          = $m[5];
                        $dob          = $m[6];
                        $empCode      = isset($m[7]) ? trim($m[7]) : '';
                        
                        // Parse names
                        $namesPart = preg_replace('/\s+/', ' ', $namesPart);
                        $nameParts = preg_split('/\s+/', $namesPart, -1, PREG_SPLIT_NO_EMPTY);
                        
                        if (count($nameParts) >= 2) {
                            $first_name = $nameParts[0];
                            array_shift($nameParts);
                            $last_name = implode(' ', $nameParts);
                        } elseif (count($nameParts) === 1) {
                            $first_name = $nameParts[0];
                        }
                        
                    } elseif (preg_match(
                        '/^\s*(\d+)\s+(PRINCIPAL|SPOUSE|CHILD\s*\d*|EXTRA\s*DEPENDENT\s*\d*|DEPENDENT|MEMBER|FATHER|MOTHER)\s+(.+?)\s+([MF])\s+(\d{2}\/\d{2}\/\d{4}|N\/A)\s*(.*)$/i',
                        $line,
                        $m
                    )) {
                        // Pattern WITHOUT NHIA number
                        $matched = true;
                        $sn           = $m[1];
                        $nhia         = 'N/A';
                        $relationship = strtoupper(trim($m[2]));
                        $namesPart    = trim($m[3]);
                        $sex          = $m[4];
                        $dob          = $m[5];
                        $empCode      = isset($m[6]) ? trim($m[6]) : '';
                        
                        // Parse names
                        $namesPart = preg_replace('/\s+/', ' ', $namesPart);
                        $nameParts = preg_split('/\s+/', $namesPart, -1, PREG_SPLIT_NO_EMPTY);
                        
                        if (count($nameParts) >= 2) {
                            $first_name = $nameParts[0];
                            array_shift($nameParts);
                            $last_name = implode(' ', $nameParts);
                        } elseif (count($nameParts) === 1) {
                            $first_name = $nameParts[0];
                        }
                    } elseif (preg_match(
                        '/^\s*(\d+)\s+([\d-]+)\s+(.+?)\s+([MF])\s+(\d{2}\/\d{2}\/\d{4})\s*(.*)$/i',
                        $line,
                        $m
                    )) {
                        // Pattern with NHIA but NO explicit relationship keyword
                        $matched = true;
                        $sn           = $m[1];
                        $nhia         = trim($m[2]);
                        $relationship = '';  // No relationship specified
                        $namesPart    = trim($m[3]);
                        $sex          = $m[4];
                        $dob          = $m[5];
                        $empCode      = isset($m[6]) ? trim($m[6]) : '';
                        
                        // Parse names
                        $namesPart = preg_replace('/\s+/', ' ', $namesPart);
                        $nameParts = preg_split('/\s+/', $namesPart, -1, PREG_SPLIT_NO_EMPTY);
                        
                        if (count($nameParts) >= 2) {
                            $first_name = $nameParts[0];
                            array_shift($nameParts);
                            $last_name = implode(' ', $nameParts);
                        } elseif (count($nameParts) === 1) {
                            $first_name = $nameParts[0];
                        }
                    }
                    
                    // If line matched one of the patterns, process it
                    if ($matched) {
                        // Normalize relationship type
                        $relationship = preg_replace('/\s+/', ' ', $relationship);

                        // Static fields
                        $category = 'MAIN';
                        $hmo = 'Hygeia HMO Limited';
                        $hmo_acronym = 'Hygeia HMO';

                        // Clean and normalize names
                        $first_name_clean = normalizeName($first_name);
                        $last_name_clean = normalizeName($last_name);

                        // Skip if both names are empty after normalization
                        if (empty($first_name_clean) && empty($last_name_clean)) {
                            $skippedLines[] = "Line $lineNum (empty names): $line";
                            continue;
                        }

                        // Rearrange and write data
                        $rearrangedData = [
                            $category,
                            $relationship,                // Enrollee Type
                            $providerNumber ?? '',        // Primary HCP
                            $nhia,                        // NHIA No
                            $first_name_clean,            // First Name (normalized)
                            $last_name_clean,             // Surname (normalized)
                            $relationship,                // Dependant Type
                            $sex,
                            $dob,
                            $hmo,
                            $hmo_acronym
                        ];

                        fputcsv($outHandle, $rearrangedData);
                        $processedCount++;
                    } else {
                        // Track potential data lines that don't match
                        if ($collecting && preg_match('/^\s*\d+\s+/', $line)) {
                            $skippedLines[] = "Line $lineNum: $line";
                        }
                    }
                }

                fclose($outHandle);

                // Save skipped lines for debugging
                if (!empty($skippedLines)) {
                    file_put_contents("skipped_lines_detailed.txt", implode("\n\n", $skippedLines));
                }

                echo "<div class='my-2 text-xl'>";
                echo "✅ PHML Register has been converted successfully.<br>";
                echo "📊 Processed: <strong>$processedCount</strong> records<br>";
                if (!empty($skippedLines)) {
                    echo "⚠️ Skipped: <strong>" . count($skippedLines) . "</strong> lines (see skipped_lines_detailed.txt)<br>";
                }
                echo "📄 Click <a class='underline' href='../outputs/" . basename($outputFile) . "'>Here</a> to download the file.";
                echo "</div>";
                echo "<br><br> <a class='text-2xl' href='../index.php'>Return Home Page</a>";
            ?>
        </div>
    </body>
</html>