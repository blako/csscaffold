<?php

/**
 * Controller
 *
 * Base controller
 * 
 * @author Anthony Short
 */
class Controller
{
	/**
	 * The config settings
	 */
	private static $config;
	
	/**
	 * Internal cache
	 */
	private static $internal_cache;
	
	/**
	 * Include paths
	 *
	 * @var array
	 */
	private static $include_paths;

	/**
	 * Find a resource file in a given directory. Files will be located according
	 * to the order of the include paths. config and i18n files will be
	 * returned in reverse order.
	 *
	 * @throws  Kohana_Exception  if file is required and not found
	 * @param   string   directory to search in
	 * @param   string   filename to look for (without extension)
	 * @param   boolean  file required
	 * @param   string   file extension
	 * @return  array    if the type is config, i18n or l10n
	 * @return  string   if the file is found
	 * @return  FALSE    if the file is not found
	 */
	public static function find_file($directory, $filename, $required = FALSE, $ext = FALSE)
	{
		# NOTE: This test MUST be not be a strict comparison (===), or empty extensions will be allowed!
		$ext = ($ext == '') ? '.php' : '.'.$ext;

		# Search path
		$search = $directory.'/'.$filename.$ext;
		
		if (isset(self::$internal_cache['find_file_paths'][$search]))
			return self::$internal_cache['find_file_paths'][$search];

		# Load include paths
		$paths = self::$include_paths;

		# Nothing found, yet
		$found = NULL;

		if ($directory === 'config')
		{
			# Search in reverse, for merging
			$paths = array_reverse($paths);

			foreach ($paths as $path)
			{
				if (is_file($path.$search))
				{
					# A matching file has been found
					$found[] = $path.$search;
				}
			}
		}
		elseif(in_array($directory, $paths))
		{
			if (is_file($directory.$filename.$ext))
			{
				# A matching file has been found
				$found = $path.$search;

				# Stop searching
				break;
			}
		}
		else
		{
			foreach ($paths as $path)
			{
				if (is_file($path.$search))
				{
					# A matching file has been found
					$found = $path.$search;

					# Stop searching
					break;
				}
			}
		}

		if ($found === NULL)
		{
			if ($required === TRUE)
			{
				# If the file is required, throw an exception
				throw new Scaffold_Exception("Cannot locate the resource: " . $directory . $filename . $ext);
			}
			else
			{
				# Nothing was found, return FALSE
				$found = FALSE;
			}
		}

		return self::$internal_cache['find_file_paths'][$search] = $found;
	}

	/**
	 * Returns the value of a key, defined by a 'dot-noted' string, from an array.
	 *
	 * @param   array   array to search
	 * @param   string  dot-noted string: foo.bar.baz
	 * @return  string  if the key is found
	 * @return  void    if the key is not found
	 */
	public static function key_string($array, $keys)
	{
		if (empty($array))
			return NULL;

		# Prepare for loop
		$keys = explode('.', $keys);

		do 
		{
			// Get the next key
			$key = array_shift($keys);

			if (isset($array[$key]))
			{
				if (is_array($array[$key]) AND ! empty($keys))
				{
					# Dig down to prepare the next loop
					$array = $array[$key];
				}
				else
				{
					# Requested key was found
					return $array[$key];
				}
			}
			else
			{
				# Requested key is not set
				break;
			}
		}
		while ( ! empty($keys));

		return NULL;
	}

	/**
	 * Sets values in an array by using a 'dot-noted' string.
	 *
	 * @param   array   array to set keys in (reference)
	 * @param   string  dot-noted string: foo.bar.baz
	 * @return  mixed   fill value for the key
	 * @return  void
	 */
	public static function key_string_set( & $array, $keys, $fill = NULL)
	{
		if (is_object($array) AND ($array instanceof ArrayObject))
		{
			# Copy the array
			$array_copy = $array->getArrayCopy();

			# Is an object
			$array_object = TRUE;
		}
		else
		{
			if ( ! is_array($array))
			{
				# Must always be an array
				$array = (array) $array;
			}

			# Copy is a reference to the array
			$array_copy =& $array;
		}

		if (empty($keys))
			return $array;

		# Create keys
		$keys = explode('.', $keys);

		# Create reference to the array
		$row =& $array_copy;

		for ($i = 0, $end = count($keys) - 1; $i <= $end; $i++)
		{
			# Get the current key
			$key = $keys[$i];

			if ( ! isset($row[$key]))
			{
				if (isset($keys[$i + 1]))
				{
					# Make the value an array
					$row[$key] = array();
				}
				else
				{
					# Add the fill key
					$row[$key] = $fill;
				}
			}
			elseif (isset($keys[$i + 1]))
			{
				# Make the value an array
				$row[$key] = (array) $row[$key];
			}

			# Go down a level, creating a new row reference
			$row =& $row[$key];
		}

		if (isset($array_object))
		{
			# Swap the array back in
			$array->exchangeArray($array_copy);
		}
	}

	/**
	 * Lists all files and directories in a resource path.
	 *
	 * @param   string   directory to search
	 * @param   boolean  list all files to the maximum depth?
	 * @param   string   full path to search (used for recursion, *never* set this manually)
	 * @return  array    filenames and directories
	 */
	public static function list_files($directory, $recursive = FALSE, $path = FALSE)
	{
		$files = array();

		if ($path === FALSE)
		{
			$paths = array_reverse(self::include_paths());

			foreach ($paths as $path)
			{
				// Recursively get and merge all files
				$files = array_merge($files, self::list_files($directory, $recursive, $path.$directory));
			}
		}
		else
		{
			$path = rtrim($path, '/').'/';

			if (is_readable($path))
			{
				$items = (array) glob($path.'*');
				
				if ( ! empty($items))
				{
					foreach ($items as $index => $item)
					{
						$name = pathinfo($item, PATHINFO_BASENAME);
						
						if(substr($name, 0, 1) == '.' || substr($name, 0, 1) == '-')
						{
							continue;
						}
						
						$files[] = $item = str_replace('\\', '/', $item);

						// Handle recursion
						if (is_dir($item) AND $recursive == TRUE)
						{
							// Filename should only be the basename
							$item = pathinfo($item, PATHINFO_BASENAME);

							// Append sub-directory search
							$files = array_merge($files, self::list_files($directory, TRUE, $path.$item));
						}
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Get a config item or group.
	 *
	 * @param   string   item name
	 * @param   boolean  force a forward slash (/) at the end of the item
	 * @param   boolean  is the item required?
	 * @return  mixed
	 */
	public static function config($key, $slash = FALSE, $required = FALSE)
	{
		// Get the group name from the key
		$group = explode('.', $key, 2);
		$group = $group[0];

		if ( ! isset(self::$config[$group]) && $group != "core")
		{
			// Load the config group
			self::$config[$group] = self::config_load($group, $required);
		}

		// Get the value of the key string
		$value = self::key_string(self::$config, $key);

		if ($slash === TRUE AND is_string($value) AND $value !== '')
		{
			// Force the value to end with "/"
			$value = rtrim($value, '/').'/';
		}

		return $value;
	}

	/**
	 * Clears a config group from the cached config.
	 *
	 * @param   string  config group
	 * @return  void
	 */
	public static function config_clear($group)
	{
		// Remove the group from config
		unset(self::$config[$group], self::$internal_cache['config'][$group]);
	}

	/**
	 * Load a config file.
	 *
	 * @param   string   config filename, without extension
	 * @param   boolean  is the file required?
	 * @return  array
	 */
	public static function config_load($name, $required = TRUE)
	{
		if (isset(self::$internal_cache['config'][$name]))
			return self::$internal_cache['config'][$name];

		// Load matching configs
		$config = array();

		if ($files = self::find_file('config', $name, $required))
		{
			foreach ($files as $file)
			{
				require $file;

				if (isset($config) AND is_array($config))
				{
					// Merge in config
					$config = array_merge($config, $config);
				}
			}
		}

		return self::$internal_cache['config'][$name] = $config;
	}
	
	/**
	 * Sets a config item, if allowed.
	 *
	 * @param   string   config key string
	 * @param   string   config value
	 * @return  boolean
	 */
	public static function config_set($key, $value)
	{
		// Do this to make sure that the config array is already loaded
		self::config($key);

		// Convert dot-noted key string to an array
		$keys = explode('.', $key);

		// Used for recursion
		$conf =& self::$config;
		$last = count($keys) - 1;

		foreach ($keys as $i => $k)
		{
			if ($i === $last)
			{
				$conf[$k] = $value;
			}
			else
			{
				$conf =& $conf[$k];
			}
		}
		
		if ($key === 'core.modules')
		{
			// Reprocess the include paths
			self::include_paths(TRUE);
		}

		return TRUE;
	}

	/**
	 * Loads a view file and returns it
	 */
	public static function load_view($view)
	{
		if ($view == '')
				return;
		
		# Find the view file
		$view = self::find_file('views/', $view, true);
	
		# Buffering on
		ob_start();
	
		# Views are straight HTML pages with embedded PHP, so importing them
		# this way insures that $this can be accessed as if the user was in
		# the controller, which gives the easiest access to libraries in views
		try
		{
			include $view;
		}
		catch (Exception $e)
		{
			ob_end_clean();
			throw $e;
		}
	
		# Fetch the output and close the buffer
		return ob_get_clean();
	}
	
	/**
	 * Get all include paths. APPPATH is the first path, followed by module
	 * paths in the order they are configured, follow by the self::config('core.path.system').
	 *
	 * @param   boolean  re-process the include paths
	 * @return  array
	 */
	public static function include_paths($process = FALSE)
	{
		if ($process === TRUE)
		{
			// Add APPPATH as the first path
			self::$include_paths = array
			(
				self::config('core.path.css'),
				self::config('core.path.system') . 'modules/'
			);
			
			# Find the modules and plugins installed	
			$modules = self::list_files('modules', FALSE, self::config('core.path.system') . '/modules');
			
			foreach ($modules as $path)
			{
				$path = str_replace('\\', '/', realpath($path));
				
				if (is_dir($path))
				{
					// Add a valid path
					self::$include_paths[] = $path.'/';
				}
			}

			# Add self::config('core.path.system') as the last path
			self::$include_paths[] = self::config('core.path.system');
			self::$include_paths[] = SCAFFOLD_DIR.'/';
		}

		return self::$include_paths;
	}
}