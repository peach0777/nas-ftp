<?php

function logMessage($message, $level = 'info') {
    $logFile = 'transfer_log.txt'; // Log file location
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "$timestamp - $level: $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function moveNASftp() {
    set_time_limit(60);
    $startTime = microtime(true);

    // FTP credentials
    define("FTP_SERVER", "darwin7771.synology.me");
    define("FTP_USERNAME", "funkit");
    define("FTP_PASSWORD", "dwin7771");

    // Use public path for CSV files
    // Changed
    $csvFilePath = 'fordeployment/NasFilesToTransfer.csv';
    $csvDeletedFileRecords = 'fordeployment/TransferredFilesRecord.csv';

    // FTP Connection
    $ftpConn = ftp_connect(FTP_SERVER);
    if (!$ftpConn) {
        logMessage("Could not connect to FTP server: " . FTP_SERVER);
        return 1;
    } else {
        logMessage("Connected to FTP server: " . FTP_SERVER);
    }

    if (!ftp_login($ftpConn, FTP_USERNAME, FTP_PASSWORD)) {
        ftp_close($ftpConn);
        logMessage("Could not log in to FTP server with username: " . FTP_USERNAME);
        return 1;
    } else {
        logMessage("Successfully logged in to FTP server with username: " . FTP_USERNAME);
    }

    ftp_pasv($ftpConn, true);

    if (($handle = fopen($csvFilePath, 'r')) !== FALSE) {
        $allData = [];
        $rowsToKeep = [];
        $deletedItem = [];

        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $allData[] = $data;
        }

        fclose($handle);

        $uploadCount = 0;
        foreach ($allData as $index => $data) {
            $temp_dir = sys_get_temp_dir();
            $directoryName = $data[0];

            if (!is_dir($temp_dir . DIRECTORY_SEPARATOR . 'FTP')) {
                mkdir($temp_dir . DIRECTORY_SEPARATOR . 'FTP');
            }

            $temp_files = $temp_dir . DIRECTORY_SEPARATOR . 'FTP' . DIRECTORY_SEPARATOR . basename($directoryName);
            $convert = mb_convert_encoding($directoryName, 'SJIS', 'UTF-8');

                $remoteFile = mb_convert_encoding($directoryName, 'SJIS', 'UTF-8');
                $localFile = $temp_files;

                if (ftp_get($ftpConn, $localFile, $remoteFile, FTP_BINARY)) {
                    $converted_remote = mb_convert_encoding('/他社物件/trash_yamato', 'SJIS', 'UTF-8');

                    if (!ftp_chdir($ftpConn, $converted_remote)) {
                        ftp_close($ftpConn);
                        logMessage("Could not change to directory: $converted_remote");
                        return 1;
                    }

                    foreach ($data as $datum) {
                        $parts = explode('/', trim($datum, '/'));
                        $fileName = array_pop($parts);
                        $subDirectory = implode('/', $parts);
                        $newPath = mb_convert_encoding($subDirectory . '/', 'SJIS', 'UTF-8');

                        $files = explode('/', $datum);
                        $text = mb_convert_encoding('/他社物件/trash_yamato', 'SJIS', 'UTF-8');
                        $isDirExist = false;

                        foreach ($files as $index2 => $fileName) {
                            if (count($files) != $index2 + 1) {
                                $text .= mb_convert_encoding($fileName . '/', 'SJIS', 'UTF-8');
                                if (@ftp_chdir($ftpConn, $text)) {
                                    $isDirExist = true;
                                } else {
                                    ftp_mkdir($ftpConn, $text);
                                }
                            } else {
                                if ($isDirExist) {
                                    $localImagePath = $temp_dir . DIRECTORY_SEPARATOR . 'FTP' . DIRECTORY_SEPARATOR . $fileName;
                                    if (ftp_put($ftpConn, $text . $fileName, $localImagePath, FTP_BINARY)) {
                                        logMessage("Moved " . $directoryName);
                                        $deletedItem = [$datum];

                                        $fp = fopen($csvDeletedFileRecords, 'a');
                                        fputcsv($fp, $deletedItem);
                                        fclose($fp);
                                        $uploadCount++;

                                        if ($uploadCount >= 20) {
                                            logMessage("Uploaded 20 items. Stopping further execution.");
                                            ftp_close($ftpConn);
                                            return;
                                        }

                                        unset($allData[$index]);

                                        $handle = fopen($csvFilePath, 'w');
                                        foreach ($allData as $row) {
                                            fputcsv($handle, $row);
                                        }
                                        fclose($handle);

                                        continue 2;
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $rowsToKeep[] = $data;
                }
        }
    } else {
        logMessage("Could not open the CSV file.");
        return 1;
    }

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    $executionTimeMs = number_format($executionTime, 2);
    $logEntry = date('Y-m-d H:i:s') . " - Execution Time: " . $executionTimeMs . " ms\n";
    logMessage($logEntry);
    ftp_close($ftpConn);

    // Output success message
    echo "Process completed successfully in " . $executionTimeMs . " ms.";
    return 0;

}
$counter = 0;
while (true) {
    moveNASftp();  
    echo $counter++;
    sleep(60);
}