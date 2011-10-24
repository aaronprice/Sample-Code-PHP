<?php
/**
 * This class is used to Upload a file / files.
 * 
 * @author 		Aaron Price
 * @since 		April 30, 2010
 * @version 	2.2
 */

/* 
   Usage: 
   
	------------------------------------------------------
						Service
	------------------------------------------------------
    // Include the FileUploader class so you can use it. The safest way to do so is as follows:
    require_once( 'path/to/FileUploader.php' );
    
    // Define Uploader settings.
    $config = array( 
    	'upload_path'	=> 'uploads/', 
    	'allowed_types' => 'gif|jpg|png', 
    	'max_size'		=> '5M', 
    	'required'		=> 'none', 
    	'overwrite'		=> true, 
    	'encrypt_name'	=> false, 
    	'url_safe'		=> true
    );
    
    // Instanciate the FileUploader with settings.
    $uploader = new FileUploader( $config );
    
	// There are two ways to upload files. You can:
    // Upload all files.
    if( $uploader->upload() ) {
    	echo 'Upload Sucessful';
    } else {
    	echo $uploader->getErrorMessage();
	}
		
	// OR Upload a specific file.
	if( $uploader->upload( '<input_name>' ) ) {
    	echo 'Upload Sucessful';
    } else {
    	echo $uploader->getErrorMessage();
	}
    
    // Use getUploadedFilenames() to see the files you've just uploaded.
    echo '<pre>';
    print_r( $uploader->getUploadedFilenames() );
    echo '</pre>';
   
	------------------------------------------------------
						HTML/PHP form
	------------------------------------------------------
    <!-- Form must have enctype as shown and inputs can be HTML arrays or multiple names. -->
    <form action="/path/to/processingfile.php" method="post" enctype="multipart/form-data">
     	<input name="image1" type="file"/><br/>
     	<input name="image2" type="file"/><br/>
     	<input name="image3" type="file"/><br/>
     	<input name="image4[]" type="file"/><br/>
     	<input name="image4[]" type="file"/><br/>
     	<input name="image4[]" type="file"/><br/>
     	<br/>
     	<input type="submit" value="upload"/>
    </form>
 */
class FileUploader {

	protected $uploadPath 		= '';
	protected $input_name		= '';
	protected $name				= '';
	protected $tmpName 			= '';
	protected $type 			= '';
	protected $size 			= '';
	protected $error 			= '';
	protected $maxFileSize 		= 0;
	protected $acceptedExt 		= '';
	protected $overwrite		= false;
	protected $encryptName		= false;
	protected $urlSafe			= false;
	protected $required			= 'none';
	protected $totalFiles		= 0;
	protected $filesUploaded	= 0;
	protected $namesArr			= array();
	protected $config 			= array( 
										'max_size' 		=> '5M', 	// 5 Megabytes
										'allowed_types' => 'gif|jpg|jpeg|png|doc|docx|pdf|txt|xls|ppt|zip|rar', // Use empty string ( '' ) to allow all file types.
										'upload_path'	=> '', 		// Path where files should be uploaded.
										'overwrite'		=> false, 	// Overwrite file if exists.
										'encrypt_name'	=> false, 	// Generates unique name ( more secure )
										'required'		=> 'none', 	// all|[ 0-9 ]{1, }|none
										'url_safe'		=> false,	// Set the name of the files being uploaded to URL safe names.
										'filename'		=> ''		// Set the name of files. ONLY FOR SINGLE UPLOADS.
						  			 );
	protected $error_prefix 	= '<p>';
	protected $error_suffix 	= '</p>'; 
	
	/**
	 * Intanciate the FileUploader class.
	 * 
	 * @access public
	 * @param array $config configuration options for the file uploader. You can also do this after by using setConfig( $config ) method.
	 * @return FileUploader
	 */
	public function FileUploader( $config = array() ) {
		$this->setConfig( $config );
	}
	
	
	/**
	 * Gets the error message of file/files being uploaded if any.
	 * 
	 * @access public
	 * @return string Error message( s ). 
	 */
	public function getErrorMessage() {
		return $this->error;
	}
	
	
	/**
	 * Returns an array of file names that were uploading using the uploader.
	 *
	 * @access public
	 * @return array
	 */
	public function getUploadedFilenames() {
		return $this->namesArr;
	}
	
	
	/**
	 * Sets configuration options for the file uploader.
	 *
	 * @access public
	 * @param array $config
	 */
	public function setConfig( $config ) {
		// Get user settings
		$this->config = array_merge( $this->config, $config );
		
		// Set object vars.
		$this->setMaxSize( $this->config[ 'max_size' ] );
		$this->uploadPath 	= $this->config[ 'upload_path' ];
		$this->overwrite 	= $this->config[ 'overwrite' ];
		$this->acceptedExt 	= $this->config[ 'allowed_types' ];
		$this->encryptName 	= $this->config[ 'encrypt_name' ];
		$this->urlSafe		= $this->config[ 'url_safe' ];
		$this->setRequired( $this->config[ 'required' ] );
	}
	
	/**
	 * Sets the delimiters for the error message. Default is <p> and </p>.
	 *
	 * @access public
	 * @param string $prefix
	 * @param string $suffix
	 */
	public function setErrorDelimiters( $prefix = '', $suffix = '' ) {
		$this->error_prefix = $prefix;
		$this->error_suffix = $suffix;
	}
	
	/**
	 * Used for uploading files.
	 * 
	 * @access public
	 * @param string $inputName Name of the input. If this is not set, all files will be uploaded.
	 */
	public function upload( $inputName = '' ) {
		if( $_FILES ) {
			if( $this->isValidPath() ) {
				
				// Check which file to upload.
				if( empty( $inputName ) ) {
					// Attempt to upload all files.
					// Check for files declared with different names under $_FILES.
					foreach( $_FILES as $key => $value ) {
						// An input exists. Add to total files.
						// Files in HTML Arrays are counted separately.
						if( !is_array( $value[ 'name' ] ) ) {
							++ $this->totalFiles;
							
							// Set the input name
							$this->input_name = $key;
						}
						
						$this->prepareFile( $key );
					}
				} else {
					// Upload a specfic file/set of files based on input name.
					$this->prepareFile( $inputName );
				}
				
				return ( $this->checkRequired() && empty( $this->error ) );
			
			// Close if( $this->isValidPath() ) {		
			} else {
				// No file can be uploaded because the path to "../gfx/projects/<projectid>/" 
				// does not exists or has incorrect permissions.
				$this->error = $this->error_prefix.'Path to folder ( "'.$this->uploadPath.'" ) either does not exist or has incorrect permissions.'.$this->error_suffix; 
				return false;
			
			// End if( $this->isValidPath() ) {
			}
		// End if( $_FILES ) {	
		} else {
			return $this->checkRequired();
		}
	}
	
	
	/**
	 * Check if and how many files are required. NOTE: if you're going to use this option it's better to set OVERWRITE to TRUE.
	 *
	 * @access private
	 * @return boolean
	 */
	private function checkRequired() {
		switch( $this->required ) {
			case '': return true; break;
			case 'none': return true; break;
			case 'all': 
				if ( $this->totalFiles == $this->filesUploaded )
					return true;
				else {
					$this->error .= $this->error_prefix.( ( $this->totalFiles == 1 ) ? 'This file is' : 'All ( '.$this->totalFiles.' ) files are' ).' required.'.$this->error_suffix;
					return false; 
				}
				break;
			default: 
				if ( $this->filesUploaded >= $this->required ) 
					return true;
				else {
					$this->error .= $this->error_prefix.'At least '.$this->required.' '.( ( intval( $this->required ) == 1 ) ? 'file is' : 'files are' ).' required.'.$this->error_suffix;
					return false; 
				}
				break;
		}
	}
	
	
	/**
	 * Checks if the file already exists.
	 * 
	 * @access private
	 * @return boolean true if file exists, otherwise false.
	 */
	private function exists() {
		return file_exists( $this->uploadPath.$this->name );
	}
	
	
	/**
	 * Moves the uploaded file from it's temporary location to it's permanent location.
	 * 
	 * @access private
	 * @return boolean Whether or not the transfer was successfull.
	 */
	private function finalize() {
		// Encrypt name if option set.
		if( $this->encryptName == true ) {
			$ext = strrchr( $this->name, '.' );
			$this->name = $this->getRandomString().$ext;
		}
		
		// Make name URL Safe if option set.
		if( $this->urlSafe == true ) {
			$this->name = $this->getSafeName();
		}
		
		// Add name to names array.
		$this->namesArr[ $this->input_name ] = $this->name;
		
		return move_uploaded_file( $this->tmpName, $this->uploadPath.$this->name );
	}
	
	
	/**
	 * Private method for encrypting filenames.
	 *
	 * @access private
	 * @param int $length
	 * @return string
	 */
	private function getRandomString( $length = 8 ) {
		return substr( uniqid(), -$length );
	}
	
	
	/**
	 * Gets the name of the file.
	 * 
	 * @access private
	 * @return string Name of the file.
	 */
	private function getName() {
		return $this->name;
	}
	
	
	/**
	 * Gets a URL Safe name of the file.
	 * 
	 * @access private
	 * @return string Safe name of the file.
	 */
	private function getSafeName() {
		$ext = substr( $this->name, strrpos( $this->name, '.' ) );
		$filename = substr( $this->name, 0, strrpos( $this->name, '.' ) );
		return strtolower( preg_replace( "/[^A-Za-z0-9]/", "_", $filename ).$ext );
	}
	
	
	/**
	 * Checks if the file has any errors.
	 * 
	 * @access private
	 * @return boolean false if file has errors, otherwise true.
	 */
	private function hasNoErrors() {
		return empty( $this->error );
	}
	
	
	/**
	 * Checks if the file is valid.
	 * Can't upload an empty file.
	 * 
	 * @access private
	 * @return boolean true if file is valid, otherwise false.
	 */
	private function isValidFile() {
		return ( !empty( $this->name ) );
	}
	
	
	/**
	 * Checks if the destination of the upload exists and has the proper permissions. 
	 * If not it attemps to create the directory structure. It is able to handle nesting.
	 * 
	 * @access private
	 */
	private function isValidPath() {
		if( is_dir( $this->uploadPath ) ) {
			return true;
		} else return ( $this->mkrdir( $this->uploadPath ) ) ? true : false; 
	}
	
	
	/**
	 * Checks if the file is below the max size specified.
	 * Can't upload an empty file.
	 * 
	 * @access private
	 * @return boolean true if file is valid, otherwise false.
	 */
	private function isValidSize() {
		return ( ( $this->maxFileSize == 0 ) || ( $this->size <= $this->maxFileSize ) );
	}
	
	
	/**
	 * Checks if the file is of a specified type. Allowed types must be set manually.
	 * 
	 * @access private
	 * @return boolean true if file is valid, otherwise false.
	 */
	private function isValidType() {
		return ( empty( $this->acceptedExt ) || in_array( strtolower( substr( strrchr( $this->name, '.' ), 1 ) ), explode( '|', $this->acceptedExt ) ) );
	}
	
	
	/**
	 * Creates a directory structure recursively.
	 * Obtained from: http://www.php.net/manual/en/function.mkdir.php#81656
	 * 
	 * @access private
	 * @param string $pathname Directory structure to be created.
	 * @param int $mode UNIX Permissions
	 */
	private function mkrdir( $pathname, $mode = 0777 ) {
	    is_dir( dirname( $pathname ) ) || $this->mkrdir( dirname( $pathname ), $mode );
	    return is_dir( $pathname ) || @mkdir( $pathname, $mode );
	}
	
	
	/**
	 * Private function used to prepare files for upload.
	 *
	 * @access private
	 * @param string $inputName
	 * @return boolean
	 */
	private function prepareFile( $inputName ) {
		
		if( !empty( $_FILES[ $inputName ] ) ) {
			
			if( is_array( $_FILES[ $inputName ][ 'name' ] ) ) {
				
				// File is part of an HTML array. Loop!
				foreach( $_FILES[ $inputName ][ 'name' ] as $key => $value ) {
					
					// An input exists. Add to total files.
					++ $this->totalFiles;
					
					// Set the input name.
					$this->input_name = $inputName.'_'.$key;
					
					// If file exists.
					if( !empty( $_FILES[ $inputName ][ 'name' ][ $key ] ) ) {
						
						// Set file.
						$this->setFile( $_FILES[ $inputName ][ 'name' ][ $key ], 
										$_FILES[ $inputName ][ 'tmp_name' ][ $key ], 
										$_FILES[ $inputName ][ 'type' ][ $key ], 
										$_FILES[ $inputName ][ 'size' ][ $key ], 
										$_FILES[ $inputName ][ 'error' ][ $key ] );
										
						// Validate and Upload File.
						$this->validateFile();
					}
				}
			// Close if( is_array( $_FILES[ $inputName ][ 'name' ] ) ) {
			} else {
				
				// Set and upload that one file.
				// If file exists.
				
				if( !empty( $_FILES[ $inputName ][ 'name' ] ) ) {
					
					// Set file.
					$this->setFile( $_FILES[ $inputName ][ 'name' ], 
									$_FILES[ $inputName ][ 'tmp_name' ], 
									$_FILES[ $inputName ][ 'type' ], 
									$_FILES[ $inputName ][ 'size' ], 
									$_FILES[ $inputName ][ 'error' ] );
							
					// Validate and Upload File.
					$this->validateFile();
				}
			}
		} else {
			// Nothing to upload.
			return $this->checkRequired();
		}
	}
	
	
	/**
	 * Sets information about that file ( get information from $_FILES )
	 * 
	 * @access private
	 * @param string $name Name of the file.
	 * @param string $tmpName Temporary Name of the file ( assigned by php ).
	 * @param string $type Type of the file.
	 * @param string $size Size of the file in bytes.
	 * @param string $error Report error in transfer ( usually assigned by php ).
	 */
	private function setFile( $name, $tmpName, $type, $size, $error ) {
		
		$pathinfo = pathinfo( $name );
		
		$this->name = empty( $this->config[ 'filename' ] ) ? $name : $this->config[ 'filename' ].'.'.$pathinfo[ 'extension' ];
		$this->tmpName = $tmpName;
		$this->type = $type;
		$this->size = $size;
		$this->error = $error;
	}
	
	
	/**
	 * Set the maximum size a file can be for the file to be uploaded.
	 *
	 * @access private
	 * @param string $size
	 */
	private function setMaxSize( $size ) {
	
		/* 
		 * Accepted format for max size:
		 * $config[ 'max_size' ] = '500'; 	// 500 bytes.
		 * $config[ 'max_size' ] = '500b'; 	// 500 bytes.
		 * $config[ 'max_size' ] = '2k'; 		// 2 Kilobytes or 2048 bytes.
		 * $config[ 'max_size' ] = '2m'; 		// 2 Megabytes.
		 * $config[ 'max_size' ] = '5M'; 		// 5 Megabytes. not case sensative.
		 */
		 
		if( preg_match( '/^[0-9]{1,}+[b|k|m|g|t]$/i', $size ) ) {
			$unit = strtolower( substr( $size, -1 ) );
			$value = intval( substr( $size, 0, -1 ) );
			switch( $unit ) {
				case 'b': $this->maxFileSize = $value; break;
				case 'k': $this->maxFileSize = $value * 1024; break;
				case 'm': $this->maxFileSize = $value * ( 1024 * 1024 ); break;
				case 'g': $this->maxFileSize = $value * ( 1024 * 1024 * 1024 ); break;
				case 't': $this->maxFileSize = $value * ( 1024 * 1024 * 1024 * 1024 ); break;
			}
		} else if( preg_match( '/^[0-9]{1,}$/i', $size ) ) {
			$this->maxFileSize = $size;
		}
	}
	
	
	/**
	 * Sets whether uploads should be required and how many. Could be: none, all, or a numerical value.
	 *
	 * @access private
	 * @param string $required
	 */
	private function setRequired( $required ) {
		if( preg_match( '/^(none|all|[ 0-9 ]{1,})$/i', $required ) ) {
			$this->required = $required;
		} else $this->required = 'none';
	}
	
	
	/**
	 * Private function that checks the file for errors and finalizes the upload.
	 *
	 * @access private
	 */
	private function validateFile() {
		if( $this->isValidFile() ) {
			if( !$this->hasNoErrors() ) {
				// Send message with $this->error();
				$this->error = $this->error_prefix.'Problem with "'.$this->name.'": '.$this->error.$this->error_suffix;
			} else if( !$this->isValidSize() ) {
				// Send message that File is too big.
				$this->error = $this->error_prefix.'"'.$this->name.'" exceeds the '.$this->config[ 'max_size' ].' limit.'.$this->error_suffix;
			} else if( !$this->isValidType() ) {
				// Send message that file is invalid Type.
				$this->error = $this->error_prefix.'"'.$this->name.'" is not a recognised file type.'.$this->error_suffix;
			} else if( $this->exists() && $this->config[ 'overwrite' ] == false ) {
				 // Send message that file is invalid Type.
				$this->error = $this->error_prefix.'"'.$this->name.'" already exists. Please delete or rename the existing file and try again.'.$this->error_suffix;
			} else if( !$this->finalize() ) {
				// File could not be uploaded, but I don't know why.
				$this->error = $this->error_prefix.'"'.$this->name.'" could not be uploaded at the moment. Please try again later.'.$this->error_suffix;
			} else {
				// Increment count of files uploaded.
				++ $this->filesUploaded;
			}
		}
	}
}