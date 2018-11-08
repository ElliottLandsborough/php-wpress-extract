<?php

require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Ai1wm_Extractor.php';

$params = [];
$file = __DIR__ . DIRECTORY_SEPARATOR . 'test_archive.wpress';

// Set archive bytes offset
if (isset($params['archive_bytes_offset'])) {
    $archive_bytes_offset = (int) $params['archive_bytes_offset'];
} else {
    $archive_bytes_offset = 0;
}

// Set file bytes offset
if (isset($params['file_bytes_offset'])) {
    $file_bytes_offset = (int) $params['file_bytes_offset'];
} else {
    $file_bytes_offset = 0;
}

// Get processed files size
if (isset($params['processed_files_size'])) {
    $processed_files_size = (int) $params['processed_files_size'];
} else {
    $processed_files_size = 0;
}

// Get total files size
if (isset($params['total_files_size'])) {
    $total_files_size = (int) $params['total_files_size'];
} else {
    $total_files_size = 1;
}

// Get total files count
if (isset($params['total_files_count'])) {
    $total_files_count = (int) $params['total_files_count'];
} else {
    $total_files_count = 1;
}

// What percent of files have we processed?
$progress = (int) min(( $processed_files_size / $total_files_size ) * 100, 100);

// Flag to hold if file data has been processed
$completed = true;

// Start time
$start = microtime(true);

// Open the archive file for reading
$archive = new Ai1wm_Extractor($file);

// Set the file pointer to the one that we have saved
$archive->set_file_pointer($archive_bytes_offset);

$old_paths = array();
$new_paths = array();

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
}

// End of the archive?
if ($archive->has_reached_eof()) {
    // Unset archive bytes offset
    unset($params['archive_bytes_offset']);

    // Unset file bytes offset
    unset($params['file_bytes_offset']);

    // Unset processed files size
    unset($params['processed_files_size']);

    // Unset total files size
    unset($params['total_files_size']);

    // Unset total files count
    unset($params['total_files_count']);

    // Unset completed flag
    unset($params['completed']);
} else {
    // Set archive bytes offset
    $params['archive_bytes_offset'] = $archive_bytes_offset;

    // Set file bytes offset
    $params['file_bytes_offset'] = $file_bytes_offset;

    // Set processed files size
    $params['processed_files_size'] = $processed_files_size;

    // Set total files size
    $params['total_files_size'] = $total_files_size;

    // Set total files count
    $params['total_files_count'] = $total_files_count;

    // Set completed flag
    $params['completed'] = $completed;
}

// Close the archive file
$archive->close();
