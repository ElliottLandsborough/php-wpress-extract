<?php

class Ai1wm_Extractor {
	/**
	 * Handle to the file
	 *
	 * @type resource
	 */
	protected $file_handle = null;

	/**
	 * Header block format of a file
	 *
	 * Field Name    Offset    Length    Contents
	 * name               0       255    filename (no path, no slash)
	 * size             255        14    size of file contents
	 * mtime            269        12    last modification time
	 * prefix           281      4096    path name, no trailing slashes
	 *
	 * @type array
	 */
	protected $block_format = array(
		'a255',  // filename
		'a14',   // size of file contents
		'a12',   // last time modified
		'a4096', // path
	);

	/**
	 * Total files size
	 *
	 * @type int
	 */
	protected $total_files_size = null;

	/**
	 * Default constructor
	 *
	 * Initializes filename and end of file block
	 *
	 * @param string $file_name Archive file
	 */
	public function __construct( $file_name ) {
		$this->file_name = $file_name;

		// Initialize end of file block
		$this->eof = pack( 'a4377', '' );

		// Open archive file for reading
		if ( ( $this->file_handle = @fopen( $file_name, 'rb' ) ) === false ) {
			throw new Ai1wm_Not_Accessible_Exception( sprintf( 'Unable to open file for reading. File: %s', $this->file_name ) );
		}
	}

	/**
	 * Set current file pointer
	 *
	 * @param int $offset Archive offset
	 *
	 * @throws \Ai1wm_Not_Seekable_Exception
	 *
	 * @return void
	 */
	public function set_file_pointer( $offset ) {
		if ( @fseek( $this->file_handle, $offset, SEEK_SET ) === -1 ) {
			throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset of file. File: %s Offset: %d', $this->file_name, $offset ) );
		}
	}

	/**
	 * Check if file has reached end of file
	 * Returns true if file has NOT reached eof, false otherwise
	 *
	 * @return bool
	 */
	public function has_not_reached_eof() {
		return ! @feof( $this->file_handle );
	}

	/**
	 * Extract one file to location
	 *
	 * @param string $location     Destination path
	 * @param array  $exclude      Files to exclude
	 * @param array  $old_paths    Old replace paths
	 * @param array  $new_paths    New replace paths
	 * @param int    $file_written File written (in bytes)
	 * @param int    $file_offset  File offset (in bytes)
	 *
	 * @throws \Ai1wm_Not_Directory_Exception
	 * @throws \Ai1wm_Not_Seekable_Exception
	 *
	 * @return bool
	 */
	public function extract_one_file_to( $location, $exclude = array(), $old_paths = array(), $new_paths = array(), &$file_written = 0, &$file_offset = 0 ) {
		if ( false === is_dir( $location ) ) {
			throw new Ai1wm_Not_Directory_Exception( sprintf( 'Location is not a directory: %s', $location ) );
		}

		// Replace forward slash with current directory separator in location
		$location = $this->replace_forward_slash_with_directory_separator( $location );

		// Flag to hold if file data has been processed
		$completed = true;

		// Seek to file offset to archive file
		if ( $file_offset > 0 ) {
			if ( @fseek( $this->file_handle, - $file_offset - 4377, SEEK_CUR ) === -1 ) {
				throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset of file. File: %s Offset: %d', $this->file_name, - $file_offset - 4377 ) );
			}
		}

		// Read file header block
		if ( ( $block = @fread( $this->file_handle, 4377 ) ) ) {

			// We reached end of file, set the pointer to the end of the file so that feof returns true
			if ( $block === $this->eof ) {

				// Seek to end of archive file minus 1 byte
				@fseek( $this->file_handle, 1, SEEK_END );

				// Read 1 character
				@fgetc( $this->file_handle );

			} else {

				// Get file header data from the block
				if ( ( $data = $this->get_data_from_block( $block ) ) ) {

					// Set file name
					$file_name = $data['filename'];

					// Set file size
					$file_size = $data['size'];

					// Set file mtime
					$file_mtime = $data['mtime'];

					// Set file path
					$file_path = $data['path'];

					// Set should exclude file
					$should_exclude_file = false;

					// Should we skip this file?
					for ( $i = 0; $i < count( $exclude ); $i++ ) {
						if ( strpos( $file_name . DIRECTORY_SEPARATOR, $exclude[ $i ] . DIRECTORY_SEPARATOR ) === 0 ) {
							$should_exclude_file = true;
							break;
						}
					}

					// Do we have a match?
					if ( $should_exclude_file === false ) {

						// Replace extract paths
						for ( $i = 0; $i < count( $old_paths ); $i++ ) {
							if ( strpos( $file_path . DIRECTORY_SEPARATOR, $old_paths[ $i ] . DIRECTORY_SEPARATOR ) === 0 ) {
								$file_name = substr_replace( $file_name, $new_paths[ $i ], 0, strlen( $old_paths[ $i ] ) );
								$file_path = substr_replace( $file_path, $new_paths[ $i ], 0, strlen( $old_paths[ $i ] ) );
								break;
							}
						}

						// Escape Windows directory separator in file path
						$file_path = $this->escape_windows_directory_separator( $location . DIRECTORY_SEPARATOR . $file_path );

						// Escape Windows directory separator in file name
						$file_name = $this->escape_windows_directory_separator( $location . DIRECTORY_SEPARATOR . $file_name );

						// Check if location doesn't exist, then create it
						if ( false === is_dir( $file_path ) ) {
							@mkdir( $file_path, $this->get_permissions_for_directory(), true );
						}

						$file_written = 0;

						// We have a match, let's extract the file
						if ( ( $completed = $this->extract_to( $file_name, $file_size, $file_mtime, $file_written, $file_offset ) ) ) {
							$file_offset = 0;
						}
					} else {

						// We don't have a match, skip file content
						if ( @fseek( $this->file_handle, $file_size, SEEK_CUR ) === -1 ) {
							throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset of file. File: %s Offset: %d', $this->file_name, $file_size ) );
						}
					}
				}
			}
		}

		return $completed;
	}

	/**
	 * Replace forward slash with current directory separator
	 *
	 * @param string $path Path
	 *
	 * @return string
	 */
	protected function replace_forward_slash_with_directory_separator( $path ) {
		return str_replace( '/', DIRECTORY_SEPARATOR, $path );
	}

	/**
	 * Get current file pointer
	 *
	 * @throws \Ai1wm_Not_Tellable_Exception
	 *
	 * @return int
	 */
	public function get_file_pointer() {
		if ( ( $offset = @ftell( $this->file_handle ) ) === false ) {
			throw new Ai1wm_Not_Tellable_Exception( sprintf( 'Unable to tell offset of file. File: %s', $this->file_name ) );
		}

		return $offset;
	}

	/**
	 * Get file header data from the block
	 *
	 * @param string $block Binary file header
	 *
	 * @return array
	 */
	private function get_data_from_block( $block ) {
		$data = false;

		// prepare our array keys to unpack
		$format = array(
			$this->block_format[0] . 'filename/',
			$this->block_format[1] . 'size/',
			$this->block_format[2] . 'mtime/',
			$this->block_format[3] . 'path',
		);
		$format = implode( '', $format );

		// Unpack file header data
		if ( ( $data = unpack( $format, $block ) ) ) {

			// Set file details
			$data['filename'] = trim( $data['filename'] );
			$data['size']     = trim( $data['size'] );
			$data['mtime']    = trim( $data['mtime'] );
			$data['path']     = trim( $data['path'] );

			// Set file name
			$data['filename'] = ( $data['path'] === '.' ? $data['filename'] : $data['path'] . DIRECTORY_SEPARATOR . $data['filename'] );

			// Set file path
			$data['path'] = ( $data['path'] === '.' ? '' : $data['path'] );

			// Replace forward slash with current directory separator in file name
			$data['filename'] = $this->replace_forward_slash_with_directory_separator( $data['filename'] );

			// Replace forward slash with current directory separator in file path
			$data['path'] = $this->replace_forward_slash_with_directory_separator( $data['path'] );
		}

		return $data;
	}

	/**
	 * Escape Windows directory separator
	 *
	 * @param string $path Path
	 *
	 * @return string
	 */
	protected function escape_windows_directory_separator( $path ) {
		return preg_replace( '/[\\\\]+/', '\\\\\\\\', $path );
	}

	/**
	 * Extract file to
	 *
	 * @param string $file_name    File name
	 * @param array  $file_size    File size (in bytes)
	 * @param array  $file_mtime   File modified time (in seconds)
	 * @param int    $file_written File written (in bytes)
	 * @param int    $file_offset  File offset (in bytes)
	 *
	 * @throws \Ai1wm_Not_Seekable_Exception
	 * @throws \Ai1wm_Not_Readable_Exception
	 * @throws \Ai1wm_Quota_Exceeded_Exception
	 *
	 * @return bool
	 */
	private function extract_to( $file_name, $file_size, $file_mtime, &$file_written = 0, &$file_offset = 0 ) {
		$file_written = 0;

		// Flag to hold if file data has been processed
		$completed = true;

		// Start time
		$start = microtime( true );

		// Seek to file offset to archive file
		if ( $file_offset > 0 ) {
			if ( @fseek( $this->file_handle, $file_offset, SEEK_CUR ) === -1 ) {
				throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset of file. File: %s Offset: %d', $this->file_name, $file_size ) );
			}
		}

		// Set file size
		$file_size -= $file_offset;

		// Should the extract overwrite the file if it exists?
		if ( ( $file_handle = @fopen( $file_name, ( $file_offset === 0 ? 'wb' : 'ab' ) ) ) !== false ) {
			$file_bytes = 0;

			// Is the filesize more than 0 bytes?
			while ( $file_size > 0 ) {

				// Read the file in chunks of 512KB
				$chunk_size = $file_size > 512000 ? 512000 : $file_size;

				// Read data chunk by chunk from archive file
				if ( $chunk_size > 0 ) {
					$file_content = null;

					// Read the file in chunks of 512KB from archiver
					if ( ( $file_content = @fread( $this->file_handle, $chunk_size ) ) === false ) {
						throw new Ai1wm_Not_Readable_Exception( sprintf( 'Unable to read content from file. File: %s', $this->file_name ) );
					}

					// Remove the amount of bytes we read
					$file_size -= $chunk_size;

					// Write file contents
					if ( ( $file_bytes = @fwrite( $file_handle, $file_content ) ) !== false ) {
						if ( strlen( $file_content ) !== $file_bytes ) {
							throw new Ai1wm_Quota_Exceeded_Exception( sprintf( 'Out of disk space. Unable to write content to file. File: %s', $file_name ) );
						}
					}

					// Set file written
					$file_written += $chunk_size;
				}

				// Time elapsed
				//if ( ( $timeout = apply_filters( 'ai1wm_completed_timeout', 10 ) ) ) {
				//	if ( ( microtime( true ) - $start ) > $timeout ) {
				//		$completed = false;
				//		break;
				//	}
				//}
			}

			// Set file offset
			$file_offset += $file_written;

			// Close the handle
			@fclose( $file_handle );

			// Let's apply last modified date
			@touch( $file_name, $file_mtime );

			// All files should chmoded to 644
			@chmod( $file_name, $this->get_permissions_for_file() );

		} else {

			// We don't have file permissions, skip file content
			if ( @fseek( $this->file_handle, $file_size, SEEK_CUR ) === -1 ) {
				throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset of file. File: %s Offset: %d', $this->file_name, $file_size ) );
			}
		}

		return $completed;
	}

	/**
	 * Get file permissions
	 *
	 * @return int
	 */
	public function get_permissions_for_file() {
		if ( defined( 'FS_CHMOD_FILE' ) ) {
			return FS_CHMOD_FILE;
		}

		return 0644;
	}

	/**
	 * Get directory permissions
	 *
	 * @return int
	 */
	public function get_permissions_for_directory() {
		if ( defined( 'FS_CHMOD_DIR' ) ) {
			return FS_CHMOD_DIR;
		}

		return 0755;
	}

	/**
	 * Check if file has reached end of file
	 * Returns true if file has reached eof, false otherwise
	 *
	 * @return bool
	 */
	public function has_reached_eof() {
		return @feof( $this->file_handle );
	}

	/**
	 * Closes the archive file
	 *
	 * We either close the file or append the end of file block if complete argument is set to true
	 *
	 * @param  bool $complete Flag to append end of file block
	 *
	 * @return void
	 */
	public function close( $complete = false ) {
		// Are we done appending to the file?
		if ( true === $complete ) {
			$this->append_eof();
		}

		if ( @fclose( $this->file_handle ) === false ) {
			throw new Ai1wm_Not_Closable_Exception( sprintf( 'Unable to close file. File: %s', $this->file_name ) );
		}
	}

	/**
	 * Get the total files size in an archive
	 *
	 * @return int
	 */
	public function get_total_files_size() {
		if ( is_null( $this->total_files_size ) ) {

			// Total files count
			$this->total_files_count = 0;

			// Total files size
			$this->total_files_size = 0;

			// Seek to beginning of archive file
			if ( @fseek( $this->file_handle, 0, SEEK_SET ) === -1 ) {
				throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to beginning of file. File: %s', $this->file_name ) );
			}

			// Loop over files
			while ( $block = @fread( $this->file_handle, 4377 ) ) {

				// End block has been reached
				if ( $block === $this->eof ) {
					continue;
				}

				// Get file data from the block
				if ( ( $data = $this->get_data_from_block( $block ) ) ) {

					// We have a file, increment the count
					$this->total_files_count += 1;

					// We have a file, increment the size
					$this->total_files_size += $data['size'];

					// Skip file content so we can move forward to the next file
					if ( @fseek( $this->file_handle, $data['size'], SEEK_CUR ) === -1 ) {
						throw new Ai1wm_Not_Seekable_Exception( sprintf( 'Unable to seek to offset of file. File: %s Offset: %d', $this->file_name, $data['size'] ) );
					}
				}
			}
		}

		return $this->total_files_size;
	}
}