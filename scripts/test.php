<?php

$inputFile = "../registers/hygeia_extracted.pdf";
$outputFile = "../outputs/hygeia_extracted_august_2025.csv";
$binaryPath = 'C:/poppler/Library/bin/pdftotext.exe';

// Extract text using pdftotext -layout
$cmd = "\"$binaryPath\" -layout \"$inputFile\" -";
$text = shell_exec($cmd);

if (!$text) {
    die("Failed to extract text from PDF.\n");
}

// Save debug output
file_put_contents("debug_output.txt", $text);

// Load lines
$lines = preg_split('/\r\n|\n|\r/', $text);

$rows = [];
$providerNumber = '';
$familyName = '';
$familyCode = '';
$collecting = false;

foreach ($lines as $line) {
    $line = trim($line);

    // Detect Provider Number
    if (preg_match('/^Provider Number:\s+([A-Z0-9\/]+)/', $line, $match)) {
        $providerNumber = $match[1];
        continue;
    }

    // Detect Family Code line
    if (preg_match('/Family\s+([A-Z ]+)\s+Code\s+-\s+(\d+)/i', $line, $match)) {
        $familyName = trim($match[1]);
        $familyCode = $match[2];
        continue;
    }

    // Start collecting if table header found
    if (preg_match('/^S\/N\s+NHIA Number\s+Relationship\s+FirstName\s+LastName\s+Sex\s+DOB\s+Emp Code$/', $line)) {
        $collecting = true;
        continue;
    }

    // If we're collecting data, parse each row
    if ($collecting && preg_match('/^\s*(\d+)\s+(\d{6,}-\d+)\s+([A-Z]+(?:\s+\d+)?)\s+([A-Z\'\-]+)\s+([A-Z\'\-]+)\s+([MF])\s+(\d{2}\/\d{2}\/\d{4})\s+(\d+)/i', $line, $matches)) {
        $rows[] = [
            'provider_number' => $providerNumber,
            'family_name'     => $familyName,
            'family_code'     => $familyCode,
            's_n'             => $matches[1],
            'nhia_number'     => $matches[2],
            'relationship'    => $matches[3],
            'first_name'      => $matches[4],
            'last_name'       => $matches[5],
            'sex'             => $matches[6],
            'dob'             => $matches[7],
            'emp_code'        => $matches[8],
        ];
    }


    // Stop collecting when encountering page break or empty line
    if ($collecting && (preg_match('/^Page\s+\d+/', $line) || empty($line))) {
        $collecting = false;
    }
}

// Write to CSV
if (!empty($rows)) {
    $fp = fopen($outputFile, 'w');
    fputcsv($fp, array_keys($rows[0]));

    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
    echo "✅ CSV file generated: $outputFile\n";
} else {
    echo "⚠️ No enrollee data found.\n";
}
?>
