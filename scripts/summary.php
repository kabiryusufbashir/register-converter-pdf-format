<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>PHML Register Converter</title>
</head>
<body class="min-h-screen bg-gray-100 py-20">
    <div class="text-center text-3xl mb-8 font-semibold">
        PHML Register Converter (Summary)
    </div>
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-2xl shadow">
        <?php
            require '../vendor/autoload.php';
            use Spatie\PdfToText\Pdf;

            $inputFile = "../registers/phml_summary.pdf";
            $outputFile = "../outputs/phml_summary_layout_final.csv";
            $binaryPath = 'C:/poppler/Library/bin/pdftotext.exe';

            // Extract text using pdftotext -layout
            $cmd = "\"$binaryPath\" -layout \"$inputFile\" -";
            $text = shell_exec($cmd);

            // Normalize text
            $text = str_replace("\xA0", ' ', $text);
            $text = preg_replace("/\r/", "\n", $text);
            $text = preg_replace("/\n+/", "\n", $text);
            $lines = explode("\n", trim($text));

            // ✅ Pattern handles `/P`, ` /P`, and no `/P`
            $pattern = '/([A-Z]{2}\/\d{3,5}(?:\s*\/[A-Z])?)\s+(?:\d{1,3}(?:,\d{3})*\s+){3,6}(\d{1,3}(?:,\d{3})*)$/';

            $results = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;

                if (preg_match($pattern, $line, $m)) {
                    // Clean up provider number spacing
                    $prov = preg_replace('/\s+\/([A-Z])/', '/$1', trim($m[1])); // Converts LA/1632 /P → LA/1632/P
                    $total = (int) str_replace(',', '', $m[2]);

                    if (!isset($results[$prov])) {
                        $results[$prov] = $total;
                    }
                }
            }

            // Write CSV
            $fp = fopen($outputFile, 'w');
            fputcsv($fp, ['Provider Number', 'Total']);
            foreach ($results as $prov => $tot) {
                fputcsv($fp, [$prov, $tot]);
            }
            fclose($fp);

            // === Display summary ===
            echo "<div class='text-lg leading-relaxed'>";
            echo "✅ <b>Total providers extracted:</b> " . count($results) . "<br>";
            echo "</div><br>";

            // === Display first 10 results for verification ===
            echo "<table class='w-full text-sm border border-gray-300 rounded mt-4'>";
            echo "<thead class='bg-gray-200'><tr><th class='border px-2 py-1'>#</th><th class='border px-2 py-1'>Provider Number</th><th class='border px-2 py-1'>Total</th></tr></thead><tbody>";
            $i = 1;
            foreach ($results as $prov => $tot) {
                if ($i > 10) break;
                echo "<tr><td class='border px-2 py-1 text-center'>$i</td><td class='border px-2 py-1'>$prov</td><td class='border px-2 py-1 text-right'>" . number_format($tot) . "</td></tr>";
                $i++;
            }
            echo "</tbody></table><br>";

            echo "<a href='$outputFile' class='inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700'>Download Full CSV</a>";

        ?>
    </div>
</body>
</html>
