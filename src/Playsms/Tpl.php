<?php

/*
 * The MIT License
 *
 * Copyright 2014 Anton Raharja <araharja at pm dot me>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Playsms;

/**
 * Dead simple PHP template engine
 *
 * @author Anton Raharja
 * @link https://github.com/playsms/tpl
 */
class Tpl
{

	// actual template file full path
	private $_filename;

	// variables holding the content
	private $_content;
	private $_result;
	private $_compiled;

	// default configuration
	private $_config_echo = 'echo';
	private $_config_dir_template = './templates';
	private $_config_dir_cache = './cache';
	private $_config_extension = '.html';

	// array holding configuration
	public $config = [];

	// template rules
	public $name;
	public $vars = [];
	public $ifs = [];
	public $loops = [];
	public $injects = [];

	/**
	 * Constructor
	 * @param array $config Default configuration
	 */
	function __construct($config = [])
	{
		$default = array(
			'echo' => $this->_config_echo,
			'dir_template' => $this->_config_dir_template,
			'dir_cache' => $this->_config_dir_cache,
			'extension' => $this->_config_extension,
		);

		$this->config = array_merge($default, $config);
	}

	// private methods


	/**
	 * Sanitize inputs
	 * @param string $inputs Input contents before processing
	 * @return string Sanitized input contents
	 */
	private function _sanitize($inputs)
	{
		$inputs = str_ireplace('`', '', (string) $inputs);
		$inputs = str_ireplace('<?php', '', $inputs);
		$inputs = str_ireplace('<?', '', $inputs);
		$inputs = str_ireplace('?>', '', $inputs);

		return $inputs;
	}

	/**
	 * Template string manipulation
	 * @param  string $key Template key
	 * @param  string $val Template value
	 * @return Tpl Tpl object
	 */
	private function _setString($key, $val)
	{
		$val = $this->_sanitize($val);
		$this->_result = str_replace('{{' . $key . '}}', $val, $this->_result);

		return $this;
	}

	/**
	 * Template loop manipulation
	 * @param  string $key Template key
	 * @param  array $val Template value
	 * @return Tpl Tpl object
	 */
	private function _setArray($key, $val = [])
	{
		$val = is_array($val) ? $val : [];

		preg_match("/<loop\." . $key . ">(.*?)<\/loop\." . $key . ">/s", $this->_result, $l);

		$loop_content = '';
		$loop = $l[1];
		foreach ( $val as $v ) {
			$loop_replaced = $loop;
			foreach ( $v as $x => $y ) {
				$loop_replaced = str_replace('{{' . $key . '.' . $x . '}}', $y, $loop_replaced);
			}
			$loop_content .= $loop_replaced;
		}

		$this->_result = preg_replace("/<loop\." . $key . ">(.*?)<\/loop\." . $key . ">/s", preg_quote($loop_content), $this->_result);
		$this->_result = stripslashes($this->_result);

		$this->_result = str_replace("<loop." . $key . ">", '', $this->_result);
		$this->_result = str_replace("</loop." . $key . ">", '', $this->_result);

		return $this;
	}

	/**
	 * Template boolean manipulation
	 * @param  string $key     Template key
	 * @param  bool $val     Template value
	 * @return Tpl Tpl object
	 */
	private function _setBool($key, $val)
	{
		if ($key && !$val) {
			$this->_result = preg_replace("/<if\." . $key . ">(.*?)<\/if\." . $key . ">/s", '', $this->_result);
		}
		$this->_result = str_replace("<if." . $key . ">", '', $this->_result);
		$this->_result = str_replace("</if." . $key . ">", '', $this->_result);

		return $this;
	}

	/**
	 * Set content from file
	 * @return Tpl Tpl object
	 */
	private function _setContentFromFile()
	{

		// empty original template content
		$this->setContent('');

		// check for template file and load it
		if ($filename = $this->getTemplate()) {
			if (file_exists($filename)) {
				if ($content = file_get_contents($filename)) {
					$this->setContent(trim($content));
				}
			}
		}

		return $this;
	}

	/**
	 * Process original content according to template rules and settings
	 * @return Tpl Tpl object
	 */
	private function _compile()
	{

		// remove spaces
		$this->_result = str_replace('{{ ', '{{', $this->getContent());
		$this->_result = str_replace(' }}', '}}', $this->_result);

		// check if
		if ($this->ifs) {
			foreach ( $this->ifs as $key => $val ) {
				$this->_setBool($key, $val);
			}
			unset($this->ifs);
		}

		// check loop
		if ($this->loops) {
			foreach ( $this->loops as $key => $val ) {
				$this->_setArray($key, $val);
			}
			unset($this->loops);
		}

		// check static replaces
		if (isset($this->vars) && is_array($this->vars) && $this->vars) {
			foreach ( $this->vars as $key => $val ) {
				$this->_setString($key, $val);
			}
			unset($this->vars);
		}

		// include global vars
		if (isset($this->injects) && is_array($this->injects) && $this->injects) {
			foreach ( $this->injects as $inject ) {
				global ${
				$inject
				};
			}
			extract($this->injects);
		}

		// remove if and loop traces
		$this->_result = preg_replace("/<if\..*?>(.*?)<\/if\..*?>/s", '', $this->_result);
		$this->_result = preg_replace("/<loop\..*?>(.*?)<\/loop\..*?>/s", '', $this->_result);

		// check dynamic variables
		$pattern = "\{\{(.*?)\}\}";
		preg_match_all("/" . $pattern . "/", $this->_result, $matches, PREG_SET_ORDER);
		foreach ( $matches as $block ) {
			$chunk = $block[0];

			// fixme anton - allow only PHP variables
			$block[1] = trim($block[1]);
			if (preg_match("/^([\$][a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*[a-zA-Z0-9_\[\]\'\"\-\>]*)$/", $block[1])) {
				$codes = '<?php ' . $this->config['echo'] . '(' . $block[1] . ')' . '; ?>';

				$this->_result = str_replace($chunk, $codes, $this->_result);
			} else {
				$this->_result = str_replace($chunk, '', $this->_result);
			}
		}

		// at this point $this->_result contains final manipulation ready to be included or eval-ed
		// $this->_result

		// attempt to create cache file for this template in storage directory
		$cache_file = 'playsms_tpl_' . md5(str_shuffle($this->_filename) . mt_rand()) . '.compiled';
		$cache = $this->config['dir_cache'] . '/' . $cache_file;

		// cache file must not be exists
		if (is_file($cache)) {
			if (!unlink($cache)) {
				$this->_compiled = '';
				return $this;
			}
		}

		if ($fd = fopen($cache, 'w+')) {
			fwrite($fd, $this->_result);
			fclose($fd);
		}

		// when failed, try to create in /tmp
		if (!is_file($cache)) {
			$cache = '/tmp/' . $cache_file;
			if ($fd = @fopen($cache, 'w+')) {
				fwrite($fd, $this->_result);
				fclose($fd);
			}
		}

		// if template cache file created then include it, else use eval() to compile
		ob_start();
		if (is_file($cache)) {
			include $cache;
			unlink($cache);
		} else {
			eval('?>' . $this->_result . '<?php ');
		}
		$_temp_compiled = ob_get_contents();
		ob_end_clean();

		// final check - remove unwanted templates
		$pattern = "\{\{(.*?)\}\}";
		preg_match_all("/" . $pattern . "/", $_temp_compiled, $matches, PREG_SET_ORDER);
		foreach ( $matches as $block ) {
			$chunk = $block[0];
			$_temp_compiled = str_replace($chunk, '', $_temp_compiled);
		}

		// save finals
		$this->_compiled = $_temp_compiled;

		return $this;
	}

	// public methods



	/**
	 * Set configuration
	 * - echo         : PHP display/print command (default: echo)
	 * - dir_template : Template files path (default: ./templates)
	 * - dir_cache    : Compiled files path (default: ./cache)
	 * - extension    : File extension (default: .html)
	 * @param array $config Default configuration
	 * @return Tpl Tpl object
	 */
	public function setConfig($config)
	{
		if (is_array($config)) {
			$this->config = array_merge($this->config, $config);
		}

		$this->config['echo'] = ($this->config['echo'] ? $this->config['echo'] : $this->_config_echo);
		$this->config['dir_template'] = ($this->config['dir_template'] ? $this->config['dir_template'] : $this->_config_dir_template);
		$this->config['dir_cache'] = ($this->config['dir_cache'] ? $this->config['dir_cache'] : $this->_config_dir_cache);
		$this->config['extension'] = ($this->config['extension'] ? $this->config['extension'] : $this->_config_extension);

		return $this;
	}

	/**
	 * Get configuration
	 * @return array Default configuration
	 */
	public function getConfig()
	{
		return $this->config;
	}

	/**
	 * Set template name
	 * @param string $name Name
	 * @return Tpl Tpl object
	 */
	function setName($name)
	{
		$this->name = !is_array($name) ? (string) $name : '';

		return $this;
	}

	/**
	 * Set template static variables
	 * @param array $vars Variables
	 * @return Tpl Tpl object
	 */
	function setVars($vars)
	{
		if (isset($vars) && is_array($vars) && $vars) {
			$this->vars = $vars;
		}

		return $this;
	}

	/**
	 * Set template logic rules
	 * @param array $ifs IF logic rules
	 * @return Tpl Tpl object
	 */
	function setIfs($ifs)
	{
		if (isset($ifs) && is_array($ifs) && $ifs) {
			$this->ifs = $ifs;
		}

		return $this;
	}

	/**
	 * Set template loop rules
	 * @param array $loops Loop rules
	 * @return Tpl Tpl object
	 */
	function setLoops($loops)
	{
		if (isset($loops) && is_array($loops) && $loops) {
			$this->loops = $loops;
		}

		return $this;
	}

	/**
	 * Set template injected global variables
	 * @param array $injects List of injected global variables
	 * @return Tpl Tpl object
	 */
	function setInjects($injects)
	{
		if (isset($injects) && is_array($injects) && $injects) {
			$this->injects = $injects;
		}

		return $this;
	}

	/**
	 * Compile template
	 * @return Tpl Tpl object
	 */
	function compile()
	{

		// if no setContent() then load the from file
		if (!$this->getContent()) {

			// if no setTemplate() then use default template file
			if (!$this->getTemplate()) {
				$this->setTemplate($this->config['dir_template'] . '/' . $this->name . $this->config['extension']);
			}

			$this->_setContentFromFile();
		}

		$this->_compile();

		return $this;
	}

	/**
	 * Set full path template file
	 * @param string $filename Filename
	 * @return Tpl Tpl object
	 */
	function setTemplate($filename)
	{
		$this->_filename = !is_array($filename) ? (string) $filename : '';

		return $this;
	}

	/**
	 * Get full path template filename
	 * @return string Filename
	 */
	function getTemplate()
	{
		return $this->_filename;
	}

	/**
	 * Set original template content
	 * @param string $content Original content
	 * @return Tpl Tpl object
	 */
	function setContent($content)
	{
		$content = $this->_sanitize(!is_array($content) ? (string) $content : '');
		$this->_content = $content;

		return $this;
	}

	/**
	 * Get original template content
	 * @return string Original content
	 */
	function getContent()
	{
		return $this->_content;
	}

	/**
	 * Get manipulated template content
	 * @return string Manipulated content
	 */
	function getResult()
	{
		return $this->_result;
	}

	/**
	 * Get compiled template content
	 * @return string Compiled content
	 */
	function getCompiled()
	{
		return $this->_compiled;
	}
}