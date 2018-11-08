<?php

require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Ai1wm_Extractor.php';

$file = __DIR__ . DIRECTORY_SEPARATOR . 'test_archive.wpress';

// Open the archive file for reading
$archive = new Ai1wm_Extractor($file);

// Set archive bytes offset
$archive_bytes_offset = 0;

// Set file bytes offset
$file_bytes_offset = 0;

// Get processed files size
$processed_files_size = 0;

// Get total files size
$total_files_size = $archive->get_total_files_size();

// What percent of files have we processed?
$progress = (int) min(( $processed_files_size / $total_files_size ) * 100, 100);

// Flag to hold if file data has been processed
$completed = true;

// Set the file pointer to the one that we have saved
$archive->set_file_pointer($archive_bytes_offset);

while ($archive->has_not_reached_eof()) {
    $file_bytes_written = 0;

    // Extract a file from archive to WP_CONTENT_DIR
    if (( $completed = $archive->extract_one_file_to(__DIR__ . DIRECTORY_SEPARATOR . 'test_output' . DIRECTORY_SEPARATOR, [], [], [], $file_bytes_written, $file_bytes_offset) )) {
        $file_bytes_offset = 0;
    }

    // Get archive bytes offset
    $archive_bytes_offset = $archive->get_file_pointer();

    // Increment processed files size
    $processed_files_size += $file_bytes_written;

    // What percent of files have we processed?
    $progress = (int) min(( $processed_files_size / $total_files_size ) * 100, 100);

    echo $progress.PHP_EOL;
}

// Close the archive file
$archive->close();
