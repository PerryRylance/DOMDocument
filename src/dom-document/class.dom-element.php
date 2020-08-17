<?php

namespace PerryRylance\DOMDocument;

/**
 * This class is used to represent elements, and implements many jQuery-like functions for the elements
 */
class DOMElement extends \DOMElement
{
	/**
	 * Constructor for a DOMElement. This should not be called directly. Use the document's createElement method instead
	 */
	public function __construct()
	{
		\DOMElement::__construct();
	}
	
	/**
	 * @internal sort function for DOM position sort
	 * @ignore
	 */
	private static function sortByDOMPosition($a, $b)
	{
		return ($a->isBefore($b) ? -1 : 1);
	}
	
	/**
	 * Test if this element comes before the other element in the DOM tree
	 * @param DOMElement $other The element to compare positions with
	 * @return boolean TRUE if this element comes before the other, FALSE if not
	 */
	public function isBefore(DOMElement $other)
	{
		if($this->parentNode === $other->parentNode)
			return ($this->getBreadth() < $other->getBreadth());
		
		$this_depth = $this->getDepth();
		$other_depth = $other->getDepth();
		
		if($this_depth == $other_depth)
			return $this->parentNode->isBefore($other->parentNode);
		
		if($this_depth > $other_depth)
		{
			$ancestor = $this;
			$ancestor_depth = $this_depth;
			
			while($ancestor_depth > $other_depth)
			{
				$ancestor = $ancestor->parentNode;
				$ancestor_depth--;
			}
			
			return $ancestor->isBefore($other);
		}
		
		if($this_depth < $other_depth)
		{
			$ancestor = $other;
			$ancestor_depth = $other_depth;
			
			while($ancestor_depth > $this_depth)
			{
				$ancestor = $ancestor->parentNode;
				$ancestor_depth--;
			}
			
			return $this->isBefore($ancestor);
		}
	}
	
	/**
	 * Returns the breadth (sometimes called child index) of this node in regards to it's siblings
	 * @return int The index of this node
	 */
	public function getBreadth()
	{
		$breadth = 0;
		for($node = $this->previousSibling; $node != null; $node = $node->previousSibling)
			$breadth++;
		return $breadth;
	}
	
	/**
	 * Returns the depth of this node in regards to it's ancestors
	 * @return int The depth of this node
	 */
	public function getDepth()
	{
		$depth = 0;
		for($node = $this->parentNode; $node != null; $node = $node->parentNode)
			$depth++;
		return $depth;
	}
	
	/**
	 * Getter. The only supported property is html
	 * @param string $name The name of the property to get
	 * @property-read The HTML string representing this element
	 */
	public function __get($name)
	{
		switch($name)
		{
			case "html":
				return $this->ownerDocument->saveHTML( $this );
				break;
				
			case "id":
				return $this->getAttribute("id");
				break;
		}
	}
	
	public function __set($name, $value)
	{
		switch($name)
		{
			case "id":
				$this->setAttribute("id", $value);
				break;
		}
	}
	
	/**
	 * Equivalent of JavaScripts querySelectorAll, takes a CSS selector, and optionally an array of options. 
	 * @param string	$selector The CSS selector
	 * @param array		$options An array of options. The only supported option is "sort" which is true by default. This can be set to false for improved performance, where that is desirable over ordered results.
	 * @return DOMQueryResults The result set matching the specified selector
	 */
	public function querySelectorAll($selector, array $options=array())
	{
		if(!isset($options['sort']))
			$options['sort'] = true;
		
		$results	= array();
		
		$converter	= new \Symfony\Component\CssSelector\CssSelectorConverter();
		$xpath		= new \DOMXPath($this->ownerDocument);
		$expr		= $converter->toXPath($selector);
		
		foreach($this->childNodes as $node)
		{
			foreach($xpath->query($expr, $node) as $el)
				$results []= $el;
		}
		
		if($options['sort'])
			usort($results, array('PerryRylance\\DOMDocument\\DOMElement', 'sortByDOMPosition'));
		
		return new DOMQueryResults($results);
	}
	
	/**
	 * Equivalent of JavaScripts querySelector. This will return the first element matching the specified selector, or NULL when no elements match
	 * @param string $selector The CSS selector
	 * @return DOMElement|NULL The element matching the selector, or NULL if none is found
	 */
	public function querySelector($selector)
	{
		$results = $this->querySelectorAll($selector);		
		
		if(empty($results))
			return null;
		
		return $results[0];
	}
	
	/**
	 * Finds any descendant elements which match the supplied CSS selector. Equivalent to querySelectorAll
	 * @param string $selector The CSS selector
	 * @return DOMQueryResults The result set matching the specified selector
	 */
	public function find($selector)
	{
		return $this->querySelectorAll($selector);
	}
	
	/**
	 * Checks if this element matches the supplied selector.
	 * @param string $selector The CSS selector
	 * @return boolean TRUE if this element matches the supplied selector, FALSE otherwise
	 */
	public function is($selector)
	{
		$matches = $this->parentNode->querySelectorAll($selector);
		
		foreach($matches as $el)
			if($el === $this)
				return true;
		
		return false;
	}
	
	/**
	 * Returns this elements children, or if a selector is supplied, only children of this element which match the selector
	 * @param string $selector The CSS selector to match
	 * @return DOMQueryResults Any children of this element which match the selector, or all children if no selector is specified
	 */
	public function children($selector=null)
	{
		$children = $this->contents();
		
		if(!$selector)
			return $children;
		
		$results = [];
		
		foreach($children as $child)
		{
			if($child->nodeType != XML_ELEMENT_NODE)
				continue;
			
			if($child->is($selector))
				$results []= $child;
		}
		
		return new DOMQueryResults($results);
	}
	
	/**
	 * Inserts elements after this element
	 * @param DOMElement|DOMQueryResults|array $arg The element(s) to insert
	 * @return DOMElement This element, for method chaining
	 * @throws \Exception When the supplied argument is not an element or result set
	 */
	public function after($arg)
	{
		if($arg instanceof DOMElement)
			$nodes = [$arg];
		else if(is_array($arg) || $arg instanceof DOMQueryResults)
			$nodes = $arg;
		else
			throw new \Exception("Invalid argument");
		
		if($this->nextSibling)
		{
			$before = $this->nextSibling;
			
			foreach($nodes as $node)
				$this->parentNode->insertBefore($node, $before);
		}
		else
			foreach($nodes as $node)
				$this->parentNode->appendChild($node);
		
		return $this;
	}
	
	/**
	 * Inserts elements before this element
	 * @param DOMElement|DOMQueryResults|array $arg The element(s) to insert
	 * @return DOMElement This element, for method chaining
	 * @throws \Exception When the supplied argument is not an element or result set
	 */
	public function before($arg)
	{
		if($arg instanceof DOMElement)
			$nodes = [$arg];
		else if(is_array($arg) || $arg instanceof DOMQueryResults)
			$nodes = $arg;
		else
			throw new \Exception("Invalid argument");
		
		foreach($nodes as $node)
			$this->parentNode->insertBefore($node, $this);
		
		return $this;
	}
	
	/**
	 * Appends content inside this element
	 * @param DOMElement|DOMQueryResults|array|string $subject The content to insert, can be an element, an array or result set of elements, or a string
	 * @return DOMElement This element, for method chaining
	 */
	public function append($subject)
	{
		if(is_array($subject) || $subject instanceof DOMQueryResults)
		{
			foreach($subject as $el)
				$this->appendChild($el);
		}
		else if(is_string($subject))
		{
			$this->appendChild( $this->ownerDocument->createTextNode( $subject ) );
		}
		else
			$this->appendChild($subject);
		
		return $this;
	}
	
	/** 
	 * Prepends the subject to this element.
	 * @param DOMElement|DOMQueryResults|array|string $subject The content to insert, can be an element, an array or result set of elements, or a string
	 * @return DOMElement This element, for method chaining
	 */
	public function prepend($subject)
	{
		if(is_array($subject) || $subject instanceof DOMQueryResults)
		{
			$originalFirst = $this->firstChild;
			
			foreach($subject as $el)
				$this->insertBefore($el, $originalFirst);
		}
		else if(is_string($subject))
		{
			$this->insertBefore( $this->ownerDocument->createTextNode( $subject ), $this->firstChild );
		}
		else
			$this->insertBefore($subject, $this->firstChild);
		
		return $this;
	}
	
	/**
	 * Returns a deep clone of this element, equivalent to jQuery's clone method
	 * @return DOMElement The cloned element
	 */
	public function duplicate()
	{
		return $this->cloneNode(true);
	}
	
	/**
	 * Returns all children of this element
	 * @return DOMQueryResults The children of this element
	 */
	public function contents()
	{
		$results = [];
		
		foreach($this->childNodes as $node)
			$results []= $node;
			
		return new DOMQueryResults($results);
	}
	
	/**
	 * @ignore
	 */
	private function getInlineStyles()
	{
		if(!$this->hasAttribute('style'))
			return [];
		
		$results	= [];
		$style		= $this->getAttribute('style');
		
		// $style		= preg_replace('/\s+/', ' ', $style);
		// $style		= preg_replace('/!important/', ' !important', $style);
		$rules		= preg_split('/\s*;\s*/', $style);
		
		foreach($rules as $rule)
		{
			if(empty($rule))
				continue;
			
			$parts = preg_split('/:/', $rule, 2);
			$results[$parts[0]] = $parts[1];
		}
		
		return $results;
	}
	
	/**
	 * @ignore
	 */
	private function getInlineStyle($name)
	{
		if(!$this->hasAttribute("style"))
			return null;
		
		$styles = $this->getInlineStyles();
		
		if(!isset($styles[$name]))
			return null;
		
		return $styles[$name];
	}
	
	/**
	 * @ignore
	 */
	private function removeInlineStyle($name)
	{
		if(!$this->hasAttribute('style'))
			return;
		
		$pairs	= [];
		$styles	= $this->getInlineStyles();
		
		if(isset($styles[$name]))
			unset($styles[$name]);
		
		foreach($styles as $key => $value)
			$pairs []= "$key:$value";
		
		$this->setAttribute("style", implode(';', $pairs));
	}
	
	/**
	 * @ignore
	 */
	private function setInlineStyle($name, $value)
	{
		$this->removeInlineStyle($name);
		$style = $this->getAttribute('style');
		
		$style = rtrim($style, ";");
		
		$this->setAttribute('style', "$style;$name:$value;");
	}
	
	/**
	 * Gets or sets inline styles on this element. Please note that this function, unlike jQuery, cannot be used to get computed styles. Only inline styles are supported.
	 * @param array|string|null $arg An array of properties to set, a string to get, or null to get all inline CSS rules on this element
	 * @return DOMElement|string Will return this element for method chaining if $arg is an array, returns the CSS property value as a string if $arg is a string
	 * @throws \Exception When the supplied argument is neither a string nor an array
	 */
	public function css($arg=null)
	{
		if(is_string($arg))
			return $this->getInlineStyle($arg);
		
		if(!is_array($arg))
			throw new \Exception("Invalid argument");
		
		foreach($arg as $key => $value)
		{
			if(empty($value))
				$this->removeInlineStyle($key);
			else
				$this->setInlineStyle($key, $value);
		}
		
		return $this;
	}
	
	/**
	 * Gets or sets the text value of this node
	 * @param string|null $text Sets the value if a string is provided, gets if null is supplied
	 * @return DOMElement|string This element for method chaining if $text is not null, the textContent of the element if $text is null
	 */
	public function text($text=null)
	{
		if($text == null)
			return $this->textContent;
		
		$this->clear();
		$this->append($text);
		
		return $this;
	}
	
	/**
	 * Gets or sets the HTML value of this node
	 * @param string|null $html Sets this elements HTML if $html is a string, gets if null is supplied
	 * @return DOMElement|string This element for method chaining if $html is not null, the HTML string representing this node if $html is null
	 */
	public function html($html=null)
	{
		if($html == null)
			return $this->html;
		
		$temp = new DOMDocument();
		
		$str = "<div id='domdocument-import-payload___'>" . DOMDocument::convertUTF8ToHTMLEntities($html) . "</div>";
		
		$html5 = new \Masterminds\HTML5([
			'target_document' => $temp
		]);
		$html5->loadHTML($str, [
			"disable_html_ns" => true
		]);
		
		$body = $temp->querySelector('#domdocument-import-payload___');
		for($child = $body->firstChild; $child != null; $child = $child->nextSibling)
		{
			$node = $this->ownerDocument->importNode($child, true);
			$this->appendChild($node);
		}
		
		return $this;
	}
	
	/**
	 * Method for working with attributes on this element
	 * @param string|array A string to get or set single a attribute, an array of key value pairs to set multiple attributes
	 * @param null|string $val A string, if the first argument is a string, or NULL if the first argument is an array
	 * @return string|DOMElement A string when retrieving data, this element when setting data
	 * @throws \Exception When $arg is not supplied
	 * @throws \Exception When first argument is neither a string nor an array
	 * @throws \Exception When the first argument is a string, and the second argument is provided but not a string
	 * @throws \Exception When the first argument is a key value array, but the second argument is also set
	 * @throws \Exception When the supplied key value array has a non-string key
	 * @throws \Exception When the supplied key value array has a non-string value
	 */
	public function attr($arg, $val=null)
	{
		if(empty($arg))
			throw new \Exception("Method must be called with at least one argument");
		
		if(!is_string($arg) && !is_array($arg))
			throw new \Exception("First argument must be a string attribute name, or a key value array of attributes to set");
		
		if($val === null)
			return $this->getAttribute($arg);
		
		if(is_string($arg))
		{
			if(!is_string($val))
				throw new \Exception("When the first argument is a string, and a second argument is provided, the second argument must also be a string, to set a single attribute");
			
			$this->setAttribute($arg, $val);
		}
		else
		{
			if($val !== null)
				throw new \Exception("A second argument cannot be provided when the first argument is a key value array of attributes to set");
			
			foreach($arg as $key => $value)
			{
				if(!is_string($key))
					throw new \Exception("Key must be a string");
				
				if(!is_string($value))
					throw new \Exception("Value must be a string");
				
				$this->setAttribute($key, $value);
			}
		}
		
		return $this;
	}
	
	/**
	 * Method for working with data- attributes on this element
	 * @param null|string|array $arg If both arguments are null / not provided, this function will return all data- attributes as an associative array. If a string is provided, it will be treated as a name and the value of the relevant data- attribute will be returned. If an array is provided, it will be used to set multiple data- attributes on the element.
	 * @param null|string $val A second argument, this can only be used if $arg is a string
	 * @return string|DOMElement A string when retrieving data, this element when setting data
	 * @throws \Exception When $arg is null, but $val is non-null. This is an invalid combination of arguments
	 * @throws \Exception When $arg is a string, but $val is not a string or null
	 * @throws \Exception When $arg is an array of key value pairs to set, but $val is not null
	 * @throws \Exception When the combination of arguments supplied is invalid, or one or more types in the arguments are invalid
	 */
	public function data($arg=null, $val=null)
	{
		if($arg == null)
		{
			if($val != null)
				throw new \Exception("Argument is null but value is provided, invalid arguments");
			
			// Both arguments are null, return all data
			$results = [];
			
			foreach($this->attributes as $name => $value)
				if(preg_match('/^data-/', $name))
					$results[preg_replace('/^data-/', '', $name)] = $value;
			
			return $results;
		}
		
		if(is_string($arg))
		{
			if(is_string($val))
			{
				$this->setAttribute("data-$arg", $val);
				return $this;
			}
			else if($val == null)
				return $this->getAttribute("data-$arg");
			else
				throw new \Exception("Invalid arguments");
		}
		else if(is_array($arg))
		{
			if($val != null)
				throw new \Exception("Argument is an array, a second argument should not be provided");
			
			// Looking to set multiple data- attributes here
			foreach($arg as $name => $value)
				$this->setAttribute("data-$name", $value);
			
			return $this;
		}
		
		throw new \Exception("Invalid arguments");
	}
	
	/**
	 * Removes this element from the DOM tree
	 * @return DOMElement This element, for method chaining
	 */
	public function remove()
	{
		if($this->parentNode)
			$this->parentNode->removeChild($this);
		
		return $this;
	}
	
	/**
	 * Empties this element by removing all the elements children, equivalent to jQuery's empty method
	 * @return DOMElement This element, for method chaining
	 */
	public function clear()
	{
		while($this->childNodes->length)
			$this->removeChild($this->firstChild);
		
		return $this;
	}
	
	/**
	 * Wraps this element in the specified element, then replaces this element with the wrapper. This does not presently support a function as input, like it's jQuery counterpart.
	 * @param DOMElement $wrapper The element to wrap this element with
	 * @return DOMElement This element, for method chaining
	 */
	public function wrap(DOMElement $wrapper)
	{
		$this->parentNode->replaceChild($wrapper, $this);
		$wrapper->appendChild($this);
		
		return $this;
	}
	
	/**
	 * Wraps the contents of this element inside the specified element
	 * @param DOMElement $wrapper The element to wrap this elements children with
	 * @return DOMElement This element, for method chaining
	 */
	public function wrapInner($wrapper)
	{
		$nodes = $this->contents();
		
		$this->append($wrapper);
		$wrapper->append($nodes);
		
		return $this;
	}
	
	/**
	 * Returns the closest ancestor of this element which matches the given selector
	 * @param string $selector The CSS selector to match
	 * @return DOMElement|null The element, if a match is found, or NULL if no matching ancestor is found
	 * @throws \Exception When attempting to call this function on the documents root element
	 */
	public function closest($selector)
	{
		if($this === $this->ownerDocument->getDocumentElementSafe())
			throw new \Exception('Method not valid on document element');
		
		for($el = $this; $el->parentNode != null; $el = $el->parentNode)
		{
			if($el->is($selector))
				return $el;
		}
		
		return null;
	}
	
	/**
	 * Hides this element
	 * @return DOMElement This element, for method chaining
	 */
	public function hide()
	{
		$this->css([
			'display' => 'none'
		]);
		
		return $this;
	}
	
	/**
	 * Shows this element
	 * @return DOMElement This element, for method chaining
	 */
	public function show()
	{
		$this->css([
			'display' => ''
		]);
	}
	
	/**
	 * Find out if this element contains another element. Please note this is implemented on the element, rather than statically like jQuery's implementation
	 * @param DOMElement $element The element to search for
	 * @return boolean TRUE if the supplied element is a descendant of this element, FALSE if not
	 */
	public function contains(DOMElement $element)
	{
		for($el = $element->parentNode; $el != null; $el = $el->parentNode)
		{
			if($el === $this)
				return true;
		}
		
		return false;
	}
	
	/**
	 * Returns the previous sibling of this element, optionally taking a selector to match against
	 * @param string|null $selector A CSS selector to match the sibling against, or null to get the sibling immediately previous to this element
	 * @return DOMElement|null The matching sibling DOMElement, or null if none is found
	 */
	public function prev($selector=null)
	{
		for($node = $this->previousSibling; $node != null; $node = $node->previousSibling)
		{
			if($node->nodeType != XML_ELEMENT_NODE)
				continue;
			
			if($selector == null || $node->is($selector))
				return $node;
		}
		
		return null;
	}
	
	/**
	 * Returns the next sibling of this element, optionally taking a selector to match against
	 * @param string|null $selector A CSS selector to match the sibling against, or null to get the sibling immediately next to this element
	 * @return DOMElement|null The matching sibling DOMElement, or null if none is found
	 */
	public function next($selector=null)
	{
		for($node = $this->nextSibling; $node != null; $node = $node->nextSibling)
		{
			if($node->nodeType != XML_ELEMENT_NODE)
				continue;
			
			if($selector == null || $node->is($selector))
				return $node;
		}
		
		return null;
	}
	
	/**
	 * Adds a class to this elements class attribute. It will be ignored if the class already exists
	 * @param string $name The classname
	 * @return DOMElement This element, for method chaining
	 */
	public function addClass($name)
	{
		if($this->hasClass($name))
			return;
			
		$class = ($this->hasAttribute('class') ? $this->getAttribute('class') : '');
		$this->setAttribute('class', $class . (strlen($class) > 0 ? ' ' : '') . $name);
		
		return $this;
	}
	
	/**
	 * Removes the specified class from this nodes class attribute
	 * @param string $name The classname
	 * @return DOMElement This element, for method chaining
	 */
	public function removeClass($name)
	{
		if(!$this->hasAttribute('class'))
			return;
			
		$class = trim(
				preg_replace('/\s{2,}/', ' ',
					preg_replace('/\\b' . $name . '\\b/', ' ', $this->getAttribute('class'))
				)
			);
			
		$this->setAttribute('class', $class);
		
		return $this;
	}
	
	/**
	 * Tests if the specified class exists on this elements class attribute
	 * @param string $name The classname
	 * @return boolean FALSE if this node does not have the specified classname, true if it does
	 */
	public function hasClass($name)
	{
		if(!$this->hasAttribute('class'))
			return false;
			
		return preg_match('/\\b' . $name . '\\b/', $this->getAttribute('class'));
	}
	
	/**
	 * Replaces this element with the specified element
	 * @param DOMElement $node The element to replace this element with
	 * @return DOMElement This element, for method chaining
	 */
	public function replaceWith(DOMElement $node)
	{
		// TODO: Support sets of elements as well as single nodes
		$next	= $this->nextSibling;
		
		if(!$next)
			$this->parentNode->append($node);
		else
			$this->parentNode->insertBefore($node, $next);
		
		return $this->remove();
	}
	
	/**
	 * Returns all this nodes siblings, optionally only siblings which match the provided CSS selector
	 * @param string|null The selector to match sibilings against
	 * @return DOMQueryResults The matching siblings, or all siblings if no selector is provided
	 */
	public function siblings($selector=null)
	{
		$results	= [];
		$nodes		= $this->parentNode->children();
		
		foreach($nodes as $node)
		{
			if($node === $this)
				continue;
			
			if($selector && !$node->is($selector))
				continue;
			
			$results []= $node;
		}
		
		return new DOMQueryResults($results);
	}
	
	/**
	 * Gets or sets the value of form elements
	 * @param string|null $value NULL to get the value of this element, a string to set the value of this element
	 * @return DOMElement This element, for method chaining
	 */
	public function val($value=null)
	{
		if($value == null)
		{
			switch(strtolower($this->nodeName))
			{
				case 'input':
					$type = ($this->hasAttribute('type') ? $this->getAttribute('type') : 'text');
					switch($type)
					{
						case 'radio':
						case 'checkbox':
							return $this->hasAttribute('checked');
							break;
						
						default:
							return $this->getAttribute('value');
							break;
					}
					break;
					
				case 'select':
					$option = $this->querySelector('option[selected]');
					if(!$option)
						return null;
					
					if($option->hasAttribute('value'))
						return $option->getAttribute('value');
					
					return $option->nodeValue;
					break;
					
				case 'option':
					if($this->hasAttribute("value"))
						return $this->getAttribute("value");
					
				default:
					return $this->nodeValue;
					break;
			}
		}
		else
		{
			switch(strtolower($this->nodeName))
			{
				case 'textarea':
				
					$this->clear();
					$this->appendText( $value );
					
					break;
				
				case 'select':
					
					$deselect = $this->querySelectorAll('option[selected]');
					foreach($deselect as $d)
						$d->removeAttribute('selected');
					
					if($value === null)
						return $this;
					
					$option = $this->querySelector('option[value="' . $value . '"]');
					
					if(!$option)
						trigger_error('Option with value "' . $value . '" not found in "' . ($this->getAttribute('name')) . '"', E_USER_WARNING);
					else
						$option->setAttribute('selected', 'selected');
					
					break;
					
				case 'input':
					
					if(!$this->hasAttribute('type') || $this->getAttribute('type') == 'text')
					{
						if(is_string($value))
							$this->setAttribute('value', $value);
					}
					else switch(strtolower($this->getAttribute('type')))
					{
						case 'radio':
							if($this->hasAttribute('value') && $this->getAttribute('value') == $value)
								$this->setAttribute('checked', 'checked');
							else
								$this->removeAttribute('checked');
							break;
							
						case 'checkbox':
							if(!empty($value) && $value != false)
								$this->setAttribute('checked', 'checked');
							else
								$this->removeAttribute('checked');
							break;
							
						default:
							$this->setAttribute('value', $value);
							break;
					}
					
					break;
				
				default:
					$this->nodeValue = $value;
					break;
			}
		}
	}
}
