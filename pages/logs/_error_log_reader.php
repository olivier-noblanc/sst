<?php

/**
 * Error Log Reader — Tail-reading algorithm + categorization
 *
 * Reads the last N lines from a PHP error log file without loading
 * the entire file into memory. Categorizes and filters entries.
 */

/**
 * Read and categorize PHP error log entries.
 *
 * Uses a tail-like approach: reads from the end of the file in chunks,
 * so even a 500 MB log file uses only ~50 KB of RAM.
 *
 * @param string $logFile     Path to the log file
 * @param int    $maxLines    Maximum number of lines to read from the end
 * @param string $errorFilter Category filter ('all' or a specific category key)
 * @return array{categorized: list<array{text: string, category: string, label: string}>, logCount: int, logFileSize: int, errorFilter: string, filteredLines: list<array{text: string, category: string, label: string}>}
 */
function readErrorLog(string $logFile, int $maxLines, string $errorFilter = 'all'): array
{
    $logLines = [];
    $logCount = 0;
    $chunkSize = 8192; // 8 KB read chunks

    if (file_exists($logFile) && is_readable($logFile)) {
        $fileSize = filesize($logFile);
        if ($fileSize > 0) {
            $fp = fopen($logFile, 'r');
            if (!is_resource($fp)) {
                $logLines = [];
            } else {
                $collected = [];       // lines collected from the end
                $buffer = '';          // partial line at chunk boundary
                $position = $fileSize; // current seek position (start of next chunk)

                while ($position > 0 && count($collected) < $maxLines) {
                    // How much to read in this chunk
                    $readLen = min($chunkSize, $position);
                    $position -= $readLen;
                    fseek($fp, $position);
                    $chunk = fread($fp, $readLen);

                    // Prepend chunk to buffer, then split into lines
                    $buffer = $chunk . $buffer;
                    $lines = explode("\n", $buffer);

                    // The first element is a partial line (unless we're at position 0)
                    if ($position > 0) {
                        $buffer = array_shift($lines); // keep partial for next iteration
                    } else {
                        $buffer = ''; // we've read from the very start
                    }

                    // Collect lines from the end (they come in reverse order from our iteration)
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if ($trimmed !== '') {
                            $collected[] = $trimmed;
                            if (count($collected) >= $maxLines) {
                                break;
                            }
                        }
                    }
                }
                // Handle any remaining partial line
                if ($buffer !== '' && trim($buffer) !== '' && count($collected) < $maxLines) {
                    $collected[] = trim($buffer);
                }
                fclose($fp);

                // $collected is in reverse chronological order (newest first)
                $logCount = count($collected);

                // Group multi-line entries (stack traces belong to the previous entry)
                $entries = [];
                $currentEntry = '';
                foreach ($collected as $line) {
                    if (preg_match('/^\[?\d{2}-\w{3}-\d{4}/', $line) === 1) {
                        if ($currentEntry !== '') {
                            $entries[] = $currentEntry;
                        }
                        $currentEntry = $line;
                    } else {
                        $currentEntry .= "\n" . $line;
                    }
                }
                if ($currentEntry !== '') {
                    $entries[] = $currentEntry;
                }
                $logLines = $entries;
            }
        }
    }

    // Categorize entries
    $categorized = [];
    foreach ($logLines as $line) {
        $category = 'info';
        $categoryLabel = 'Info';
        if (stripos($line, 'Fatal error') !== false || stripos($line, 'critical') !== false) {
            $category = 'fatal';
            $categoryLabel = 'Fatal';
        } elseif (stripos($line, 'Warning') !== false || stripos($line, 'warning') !== false) {
            $category = 'warning';
            $categoryLabel = 'Warning';
        } elseif (stripos($line, '[SST-DB]') !== false) {
            $category = 'db';
            $categoryLabel = 'Base de données';
        } elseif (stripos($line, '[SST-MAIL]') !== false) {
            $category = 'mail';
            $categoryLabel = 'E-mail';
        } elseif (stripos($line, '[SST-BACKUP]') !== false) {
            $category = 'backup';
            $categoryLabel = 'Sauvegarde';
        } elseif (stripos($line, '[SST-MIGRATION]') !== false) {
            $category = 'migration';
            $categoryLabel = 'Migration';
        } elseif (stripos($line, '[SST-AUDIT]') !== false) {
            $category = 'audit';
            $categoryLabel = 'Audit';
        } elseif (stripos($line, '[SST-RESPOND]') !== false) {
            $category = 'respond';
            $categoryLabel = 'Réponse';
        } elseif (stripos($line, '[SST-ERROR-MAIL]') !== false) {
            $category = 'mail';
            $categoryLabel = 'E-mail';
        } elseif (stripos($line, 'SST App:') !== false) {
            $category = 'app';
            $categoryLabel = 'Application';
        }
        $categorized[] = ['text' => $line, 'category' => $category, 'label' => $categoryLabel];
    }

    $filteredLines = $errorFilter === 'all'
        ? $categorized
        : array_values(array_filter($categorized, fn($e) => $e['category'] === $errorFilter));

    $logFileSize = file_exists($logFile) ? (int) filesize($logFile) : 0;

    return [
        'categorized'   => $categorized,
        'logCount'      => $logCount,
        'logFileSize'   => $logFileSize,
        'errorFilter'   => $errorFilter,
        'filteredLines' => $filteredLines,
    ];
}
