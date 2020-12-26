<?php

namespace PerryRylance;

require_once(__DIR__ . '/dom-document/class.dom-element.php');
require_once(__DIR__ . '/dom-document/class.dom-query-results.php');

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMDocument\DOMQueryResults;

/**
 * This is the main class which builds on top of PHP's native DOMDocument
 */
class DOMDocument extends \DOMDocument
{
	/**
	 * Constructor for the DOMDocument
	 */
	public function __construct($version='1.0', $encoding='UTF-8')
	{
		\DOMDocument::__construct($version, $encoding);
		
		$this->registerNodeClass('DOMElement', 'PerryRylance\DOMDocument\DOMElement');
	}
	
	/**
	 * Converts UTF8 characters to their HTML entity equivalents
	 * @param string $html A HTML string to perform conversion on
	 * @return string The converted HTML string
	 */
	public static function convertUTF8ToHTMLEntities($html)
	{
		if(function_exists('mb_convert_encoding'))
			return mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
		else
		{
			trigger_error('Using fallback UTF to HTML entity conversion', E_USER_NOTICE);
			return htmlspecialchars_decode(utf8_decode(htmlentities($html, ENT_COMPAT, 'utf-8', false)));
		}
	}
	
	/**
	 * Magic method for getting properties. Only "html" is supported presently.
	 * @property-read The HTML string representing this documents inner body. This is intended for working with components constituting of HTML, such as UI panels, as opposed to an entire HTML document
	 */
	public function __get($name)
	{
		switch($name)
		{
			case "html":
				return $this->saveInnerBody();
				break;
			
			case "body":
				return $this->querySelector("body");
				break;
		}
	}
	
	/**
	 * Magic method for function calls, this can be used to call any methods supported by DOMElement on the documentElement of this document
	 * @method mixed Mixed See DOMElement for a list of supported methods
	 * @see DOMElement for a list of supported methods
	 */
	public function __call($name, $arguments)
	{
		if(method_exists('\\PerryRylance\\DOMDocument\\DOMElement', $name))
		{
			$el = $this->getDocumentElementSafe();
			return call_user_func_array(array($el, $name), $arguments);
		}
		
		throw new \Exception("No such method");
	}
	
	/**
	 * Loads the supplied HTML string
	 * @param string $src The HTML string to parse
	 * @param array $options An array of options. Presently only executePHP is supported, this defaults to TRUE and will execute inline PHP
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 * @return This document, for method chaining
	 */
	public function loadHTML($src, $options=array())
	{
		if(empty($options))
			$options = [];
		
		if(isset($options['executePHP']) && $options['executePHP'])
		{
			ob_start();
			eval("?>$src");
			$src = ob_get_clean();
		}
		
		if(!isset($options['disable_html_ns']))
			$options['disable_html_ns'] = true;
		
		$html5 = new \Masterminds\HTML5([
			'target_document' => $this
		]);
		$html5->loadHTML($src, $options);
		
		if($html5->hasErrors())
		{
			foreach($html5->getErrors() as $err)
				trigger_error($err, E_USER_WARNING);
			
			Parent::loadHTML($src);
		}
		
		return $this;
	}
	
	/**
	 * Loads the named file
	 * @param string $src The filename of the file to read from
	 * @param array $options An array of options. Presently only executePHP is supported, this defaults to TRUE and will execute inline PHP
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 * @return This document, for method chaining
	 */
	public function load($filename, $options=array())
	{
		if(!is_string($filename))
			throw new \Exception('Input must be a string');
		
		if(!is_file($filename))
			throw new \Exception("File $filename not found");
		
		$contents = file_get_contents($filename);
		
		if(preg_match('/^php$/i', pathinfo($filename, PATHINFO_EXTENSION)) && !isset($options['executePHP']))
			$options['executePHP'] = true;
		
		return $this->loadHTML($contents, $options);
	}
	
	/**
	 * Saves the whole document, or specified element, as a HTML string
	 * @param DOMElement|null The element to save, or null to save the entire document
	 * @param array $options An array of options to pass to the HTML5 parser
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 */
	public function saveHTML($element=null, $options=array())
	{
		if($element == null)
			$element = $this;
		
		$html5 = new \Masterminds\HTML5();
		return $html5->saveHTML($element, $options);
	}
	
	/**
	 * Saves the entire document as HTML5, into the specified file.
	 * @param string $filename The name of the file to save to
	 * @param array $options An array of options to pass to the HTML5 parser
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 */
	public function save($filename, $options=array())
	{
		$html5 = new \Masterminds\HTML5();
		return $html5->save($this, $filename, $options);
	}
	
	/**
	 * Function to safely get the document element. Some PHP builds do not implement documentElement, this function can be used to safely retrieve the document element in absense of this property.
	 * @return DOMElement The document element for this document
	 */
	public function getDocumentElementSafe()
	{
		// Workaround for some installations of PHP missing documentElement property
		if(property_exists($this, 'documentElement'))
		{
			if(empty($this->documentElement))
				throw new \Exception("Document is empty");
			
			return $this->documentElement;
		}
		
		$xpath = new \DOMXPath($this);
		$result = $xpath->query('/html/body');
		
		if($result->length)
			return $result->item(0);
		
		if(!$this->firstChild)
			throw new \Exception("Document is empty");
		
		return $this->firstChild;
	}
	
	/**
	 * This function saves only the inside of the <body> element of this document. This is useful when you want to import a HTML document into another, but you don't want to end up with nested <html> elements. This is equivalent to using the "html" property.
	 * @return string The HTML string
	 */
	public function saveInnerBody()
	{
		$result = '';
		
		if(property_exists($this, 'documentElement'))
			$body = $this->querySelector('body');
		
		if(!$body)
			$body = $this->getDocumentElementSafe();
		
		if(!$body)
			return null;
		
		for($node = $body->firstChild; $node != null; $node = $node->nextSibling)
			$result .= $this->saveHTML($node);
			
		return $result;
	}
	
	/**
	 * This function will import the specified content to be used inside this document.
	 * @param mixed $subject The subject, a HTML fragment string, DOMElement from another document, or another DOMDocument.
	 * @return DOMQueryResults The resulting element(s)
	 */
	public function import($subject)
	{
		if(is_string($subject))
			$fragment = $subject;
		else if($subject instanceof DOMElement || $subject instanceof DOMDocument)
			$fragment = $subject->html;
		
		$temp 		= new DOMDocument();
		$temp->loadHTML($fragment);
		
		$body		= $temp->getDocumentElementSafe();
		$arr		= [];
		
		for($node = $body->firstChild; $node != null; $node = $node->nextSibling)
			$arr []= $this->importNode($node, true);
		
		$results	= new DOMQueryResults($arr);
		
		return $results;
	}
	
	private function assertNotEmpty()
	{
		if(empty($this->getDocumentElementSafe()))
			throw new \Exception('Document is empty');
	}
}
