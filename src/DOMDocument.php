<?php

namespace PerryRylance;

require_once(__DIR__ . '/DOMDocument/DOMElement.php');
require_once(__DIR__ . '/DOMDocument/DOMObject.php');

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMDocument\DOMObject;

/**
 * This is the main class which builds on top of PHP's native DOMDocument
 *
 * @mixin DOMObject
 */
class DOMDocument extends \DOMDocument
{
	const OPTION_EVALUATE_PHP		= 1;
	const OPTION_DISABLE_HTML_NS	= 2; // NB: Prevents the parser from automatically assigning the HTML5 namespace to the DOM document. See Masterminds/html5-php.

	private $constructorCalled = false; // NB: Used to signal bad state, otherwise we get silent failure when trying to loadHTML before calling constructor

	const UNDEFINED = "b0814351-6e51-4134-a77b-8e5fbec4e026"; // NB: Used to differentiate between explicit null and argument not supplied, for example in DOMObject::css

	/**
	 * Constructor for the DOMDocument
	 */
	public function __construct($version='1.0', $encoding='UTF-8')
	{
		\DOMDocument::__construct($version, $encoding);
	
		$this->constructorCalled = true;
		$this->registerNodeClass('DOMElement', DOMElement::class);
	}
	
	/**
	 * Converts UTF8 characters to their HTML entity equivalents
	 * @param string $html A HTML string to perform conversion on
	 * @return string The converted HTML string
	 */
	public static function convertUTF8ToHTMLEntities($html)
	{
		if(function_exists('mb_encode_numericentity'))
		{
			$f = 0xffff;
			$convmap = array(
			/* <!ENTITY % HTMLlat1 PUBLIC "-//W3C//ENTITIES Latin 1//EN//HTML">
			%HTMLlat1; */
			160,  255, 0, $f,
			/* <!ENTITY % HTMLsymbol PUBLIC "-//W3C//ENTITIES Symbols//EN//HTML">
			%HTMLsymbol; */
			402,  402, 0, $f,  913,  929, 0, $f,  931,  937, 0, $f,
			945,  969, 0, $f,  977,  978, 0, $f,  982,  982, 0, $f,
			8226, 8226, 0, $f, 8230, 8230, 0, $f, 8242, 8243, 0, $f,
			8254, 8254, 0, $f, 8260, 8260, 0, $f, 8465, 8465, 0, $f,
			8472, 8472, 0, $f, 8476, 8476, 0, $f, 8482, 8482, 0, $f,
			8501, 8501, 0, $f, 8592, 8596, 0, $f, 8629, 8629, 0, $f,
			8656, 8660, 0, $f, 8704, 8704, 0, $f, 8706, 8707, 0, $f,
			8709, 8709, 0, $f, 8711, 8713, 0, $f, 8715, 8715, 0, $f,
			8719, 8719, 0, $f, 8721, 8722, 0, $f, 8727, 8727, 0, $f,
			8730, 8730, 0, $f, 8733, 8734, 0, $f, 8736, 8736, 0, $f,
			8743, 8747, 0, $f, 8756, 8756, 0, $f, 8764, 8764, 0, $f,
			8773, 8773, 0, $f, 8776, 8776, 0, $f, 8800, 8801, 0, $f,
			8804, 8805, 0, $f, 8834, 8836, 0, $f, 8838, 8839, 0, $f,
			8853, 8853, 0, $f, 8855, 8855, 0, $f, 8869, 8869, 0, $f,
			8901, 8901, 0, $f, 8968, 8971, 0, $f, 9001, 9002, 0, $f,
			9674, 9674, 0, $f, 9824, 9824, 0, $f, 9827, 9827, 0, $f,
			9829, 9830, 0, $f,
			/* <!ENTITY % HTMLspecial PUBLIC "-//W3C//ENTITIES Special//EN//HTML">
			%HTMLspecial; */
			/* These ones are excluded to enable HTML: 34, 38, 60, 62 */
			338,  339, 0, $f,  352,  353, 0, $f,  376,  376, 0, $f,
			710,  710, 0, $f,  732,  732, 0, $f, 8194, 8195, 0, $f,
			8201, 8201, 0, $f, 8204, 8207, 0, $f, 8211, 8212, 0, $f,
			8216, 8218, 0, $f, 8218, 8218, 0, $f, 8220, 8222, 0, $f,
			8224, 8225, 0, $f, 8240, 8240, 0, $f, 8249, 8250, 0, $f,
			8364, 8364, 0, $f);

			return mb_encode_numericentity($html, $convmap, "UTF-8");
		}
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
				return $this->find("body");
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
		$method = $name;

		switch($name)
		{
			case "querySelector":
			case "querySelectorAll":

				$options = [];

				if(count($arguments) > 1)
					$options = $arguments[1];
				
				if(!isset($options['caller']) || !($options['caller'] instanceof DOMObject))
					trigger_error("querySelector and querySelectorAll are deprecated on DOMDocument. It is recommended to use DOMDocument::find instead", E_USER_DEPRECATED);

				$method = "find";	// NB: Backwards compatibility

				break;
			
			default:
				break;
		}

		if(method_exists('\\PerryRylance\\DOMDocument\\DOMObject', $method))
		{
			$el			= $this->getDocumentElementSafe();
			$set		= new DOMObject([$el]);

			$result		= call_user_func_array(array($set, $method), $arguments);

			return $result;
		}
		
		throw new \Exception("No such method $name");
	}

	/**
	 * Returns a "shorthand" function which you can use in a jQuery-like manner to create fragemnts, eg $_ = $doc->shorthand(); $div = $_("<div>Example</div>);
	 * @return callable A convenience function which creates HTML fragments from a string
	 */
	public function shorthand(): callable
	{
		return function($subject) {
			return new DOMObject($subject);
		};
	}
	
	/**
	 * Loads the supplied HTML string
	 * @param string $src The HTML string to parse
	 * @param int $options A bit field of options. Presently only DOMDocument::OPTION_EXECUTE_PHP is supported, this defaults to TRUE and will execute inline PHP
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 * @return bool true
	 */
	public function loadHTML(string $src, int $options = DOMDocument::OPTION_EVALUATE_PHP | DOMDocument::OPTION_DISABLE_HTML_NS): bool
	{
		if(!$this->constructorCalled)
			throw new \Exception("Bad state (did you try to loadHTML before calling constructor?)");

		if($options & DOMDocument::OPTION_EVALUATE_PHP)
		{
			ob_start();
			eval("?>$src");
			$src = ob_get_clean();
		}
		
		// NB: Hack to suppress doctype warning when dealing with fragments. This isn't ideal, but it's safe to assume HTML5 here, so we will simply add a doctype if it's not present.
		if(!preg_match('/^<!DOCTYPE/i', $src))
			$src = "<!DOCTYPE html>$src";
		
		$html5 = new \Masterminds\HTML5([
			'target_document' => $this
		]);

		$html5->loadHTML($src, [
			'disable_html_ns' => $options & DOMDocument::OPTION_DISABLE_HTML_NS
		]);
		
		if($html5->hasErrors())
		{
			foreach($html5->getErrors() as $err)
				trigger_error($err, E_USER_WARNING);
			
			Parent::loadHTML($src);
		}

		$this->onLoaded();
		
		return true;
	}

	/**
	 * Loads the supplied HTML file
	 * @param string $filename The file to parse
	 * @param int $options A bit field of options. Presently only DOMDocument::OPTION_EXECUTE_PHP is supported, this defaults to TRUE and will execute inline PHP
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 * @return bool true
	 */
	public function loadHTMLFile(string $filename, int $options = DOMDocument::OPTION_EVALUATE_PHP | DOMDocument::OPTION_DISABLE_HTML_NS): bool
	{
		return $this->loadHTML($filename, $options);
	}

	/**
	 * Callback which fires after the HTML has been parsed and loaded, but before loadHTML returns. Useful for controlling the execution order for operations spread across an inheritance chain.
	 * @return null
	 */
	protected function onLoaded()
	{
		
	}
	
	/**
	 * Loads the named file
	 * @param string $src The filename of the file to read from
	 * @param int $options A bit field of options. Presently only DOMDocument::OPTION_EVALUATE_PHP is supported, this defaults to TRUE and will execute inline PHP
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 * @return This document, for method chaining
	 */
	public function load(string $filename, int $options = DOMDocument::OPTION_EVALUATE_PHP | DOMDocument::OPTION_DISABLE_HTML_NS): bool
	{
		if(!is_string($filename))
			throw new \Exception('Input must be a string');
		
		if(!is_file($filename))
			throw new \Exception("File $filename not found");
		
		$contents = file_get_contents($filename);
		
		return $this->loadHTML($contents, $options);
	}
	
	/**
	 * Saves the whole document, or specified element, as a HTML string
	 * @param DOMElement|null The element to save, or null to save the entire document
	 * @param array $options An array of options to pass to the HTML5 parser
	 * @see https://github.com/Masterminds/html5-php#options for other options supported by the HTML5 parser
	 */
	public function saveHTML($element=null, $options=array()): string | false
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
	public function save($filename, $options=array()): int | false
	{
		$html5 = new \Masterminds\HTML5();
		$html5->save($this, $filename, $options);
		return filesize($filename);
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
	public function saveInnerBody(): string
	{
		$body = null;
		$result = '';
		
		if(property_exists($this, 'documentElement'))
		{
			$first = $this->find('body')->first();

			if($first->length)
				$body = $first[0];
		}
		
		if(!$body)
			$body = $this->getDocumentElementSafe();
		
		for($node = $body->firstChild; $node != null; $node = $node->nextSibling)
			$result .= $this->saveHTML($node);

		return $result;
	}
	
	/**
	 * This function will import the specified content to be used inside this document.
	 * @param mixed $subject The subject, a HTML fragment string, DOMElement from another document, or another DOMDocument.
	 * @return DOMObject The resulting element(s)
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
		
		$results	= new DOMObject($arr);
		
		return $results;
	}
	
	/**
	 * Creates a HTML fragment from the supplied HTML source string
	 * @param string $html The HTML source string
	 * @return DOMObject The resulting element(s)
	 */
	public function create(string $html): DOMObject
	{
		$div		= $this->createElement("div");
		$set		= new DOMObject($div);
		$arr		= [];

		$set->html( trim($html) );

		// NB: Clone nodes here otherwise when they go out of scope, they're garbage collected

		foreach($set->first()[0]->childNodes as $node)
			$arr	[]= $node->cloneNode(true);

		return		new DOMObject($arr);
	}

	/**
	 * Returns the entire document as a string.
	 * @return string The entire document rendered to a string
	 */
	public function __toString()
	{
		return $this->saveHTML();
	}
}
