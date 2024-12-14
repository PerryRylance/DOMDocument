<?php

namespace PerryRylance\DOMDocument;

use PerryRylance\DOMDocument;

/**
 * This class is used to represent a results set of matched elements, in much the same way as a jQuery array works. Any methods supported by DOMElement can be called on a DOMObject list.
 */
class DOMObject implements \ArrayAccess, \Countable, \Iterator
{
	private $index = 0;
	private $container;
	
	/**
	 * Constructor. This should never be invoked outside of DOMElement
	 */
	public function __construct($arr = null)
	{
		$this->container = [];

		if(!empty($arr))
		{
			if(is_array($arr))
				$this->container = $arr;
			else if($arr instanceof DOMObject)
				$this->container = $arr->toArray();
			else if($arr instanceof DOMElement || $arr instanceof DOMDocument)
				$this->container = [$arr];
			else
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMObject, DOMElement, or omitted");

			// NB: Sanity check
			foreach($this->container as $el)
				if(!($el instanceof \DOMNode))
				{
					$className = get_class($el);
					throw new \Exception("All elements must be instances of DOMElement, $className given");
				}
		}
	}

	private function implicitOwnerDocumentCast(): ?DOMDocument
	{
		// TODO: Keep an eye on this, might be better to set this at construction time rather than conditionally throwing this. This will make testing potentially difficult.
		if(empty($this->container))
			throw new \Exception("Cannot get ownerDocument on DOMObject with empty container");

		return $this->container[0]->ownerDocument;
	}

	public static function contains(DOMElement $container, DOMDocument $contained)
	{
		for($el = $contained->parentNode; $el != null; $el = $el->parentNode)
		{
			if($el === $container)
				return true;
		}

		return false;
	}
	
	public function toArray()
	{
		// TODO: Test that this returns by value and not by reference. External code should NOT manipulate this set
		return $this->container;
	}

	/**
	 * Magic method for function calls, this can be used to call any methods supported by DOMElement on the entire result set stored in this DOMObject set
	 * @param string $name The name of the method to call
	 * @param array $arguments The arguments which will be passed on to the corresponding method on DOMElement
	 * @method mixed Mixed See DOMElement for a list of supported methods
	 * @return This results set for functions which return $this (eg attr), a new results set for functions which return result sets (eg find)
	 * @see DOMElement for a list of supported methods
	 */
	public function __call($name, $arguments)
	{
		$set = null;
		
		foreach($this->container as $element)
		{
			// TODO: We've made a special exception for querySelectorAll here so that "is" can work and so that the "closest" test passes. "is" uses the parent of the subject element and performs a query selector all / find with the selector passed to "is". If the element calling "is" matches itself in the results from it's parents query selector all, then "is" returns true. The first expression here stops the exception from being raised and allows this to continue working. This should be removed when querySelectorAll is removed.
			if(!($element instanceof DOMDocument && $name == "querySelectorAll") && !method_exists($element, $name))
				throw new \Exception("No such method '$name' on " . get_class($element));
			
			$result = call_user_func_array(
				array($element, $name),
				$arguments
			);
			
			if($result instanceof DOMObject)
			{
				if($set == null)
					$set = [];
				
				foreach($result as $el)
					$set []= $el;
			}
		}
		
		if(is_array($set))
			return new DOMObject($set);
		
		return $this;
	}
	
	public function __get($name)
	{
		switch($name)
		{
			case "length":
				return count($this->container);
				break;
			
			case "html":
				$result = "";

				foreach($this->container as $el)
					$result .= $el->html;

				return $result;
				break;
		}
	}

	public function offsetExists($offset): bool
	{
		return isset($this->container[$offset]);
	}
	
	public function offsetGet($offset): mixed
	{
		return isset($this->container[$offset]) ? $this->container[$offset] : null;
	}
	
	public function offsetSet($offset, $value): void
	{
		if(!($value instanceof DOMElement))
			throw new \Exception("Only DOMElement is permitted in query results");
		
		if(is_null($offset))
			$this->container []= $value;
		else
			$this->container[$offset] = $value;
	}
	
	public function offsetUnset($offset): void
	{
		unset($this->container[$offset]);
	}
	
	public function count(): int
	{
		return count($this->container);
	}
	
	public function current(): mixed
	{
		return $this->container[$this->index];
	}
	
	public function next($arg=null): void
	{
		if(!empty($arg))
			trigger_error("DOMObject::next is an implementation of Iterator::next, which takes no arguments. Did you mean to call jQuery-like DOMObject::following instead?", E_USER_WARNING);

		$this->index++;
	}
	
	public function key(): mixed
	{
		return $this->index;
	}
	
	public function valid(): bool
	{
		return isset($this->container[$this->key()]);
	}
	
	public function rewind(): void
	{
		$this->index = 0;
	}
	
	public function reverse()
	{
		$this->container = array_reverse($this->container);
		$this->rewind();
	}
	
	/**
	 * Executes the supplied callback on the elements in this result set
	 * @param callable $callback Any callable function
	 * @return DOMObject This result set, for method chaining
	 * @throws \Exception When the supplied argument is not callable
	 */
	public function each($callback)
	{
		if(!is_callable($callback))
			throw new \Exception("Argument must be callable");
		
		foreach($this->container as $element)
			$callback(new DOMObject($element));
		
		return $this;
	}
	
	/**
	 * Filters this result set using the given subject
	 * @param string|callable $subject The subject to filter with. A string will be interpreted as a CSS selector, a callback can be used to implement custom filtering logic.
	 * @return DOMObject The filtered result set
	 * @throws \Exception When the supplied subject is neither a string nor callable
	 */
	public function filter($subject)
	{
		$results = [];
		
		if(is_string($subject))
		{
			$callback = function($el) use ($subject) {
				return $el->is($subject);
			};
		}
		else if(is_callable($subject))
		{
			$callback = $subject;
		}
		else
			throw new \Exception("Invalid filter subject");
		
		foreach($this->container as $el)
		{
			if($callback($el))
				$results []= $el;
		}
	
		return new DOMObject($results);
	}
	
	/**
	 * Returns the first result in this set, or null if the set is empty
	 * @return DOMElement|null The first element in the set, or null if the set is empty
	 * @todo Review this, it should return a results set, should it be any empty array on an empty set? I expect so
	 */
	public function first()
	{
		if(count($this->container) == 0)
			return new DOMObject();
		
		return new DOMObject( $this->container[0] );
	}
	
	/**
	 * Returns the last result in this set, or null if the set is empty
	 * @return DOMElement|null The last element in the set, or null if the set is empty
	 * @todo Review this, it should return a results set, should it be any empty array on an empty set? I expect so
	 */
	public function last()
	{
		if(count($this->container) == 0)
			return null;
	
		return new DOMObject( $this->container[count($this->container) - 1] );
	}

	/**
	 * Finds any descendant elements which match the supplied CSS selector within this set.
	 * @param string $selector The CSS selector
	 * @return DOMObject The result set matching the specified selector
	 */
	public function find($selector)
	{
		$result = [];

		$this->each(function($el) use (&$result, $selector) {

			$subset	= $el->querySelectorAll($selector, [
				"sort" => true,
				"caller" => $this
			]);

			$result = array_merge($result, $subset->toArray());

		});

		return new DOMObject($result);
	}

	/**
	 * Returns this sets children, or if a selector is supplied, only children of this set  which match the selector
	 * @param string $selector The CSS selector to match
	 * @return DOMObject Any children of this set which match the selector, or all children if no selector is specified
	 */
	public function children($selector=null)
	{
		$children = [];

		foreach($this->container as $el)
		{
			for($node = $el->firstChild; $node != null; $node = $node->nextSibling)
			{
				if($node->nodeType == XML_ELEMENT_NODE)
					$children []= $node;
			}
		}
		
		if(!$selector)
			return new DOMObject($children);
		
		$results = [];
		
		foreach($children as $child)
		{
			if((new DOMObject($child))->is($selector))
				$results []= $child;
		}
		
		return new DOMObject($results);
	}

	/**
	 * Checks if any element within this set matches the supplied selector
	 * @param string $selector The CSS selector
	 * @return boolean TRUE if this set contains an element which matches the supplied selector, FALSE otherwise
	 */
	public function is($selector)
	{
		$parent = $this->parent();

		$matches = $this->parent()->find($selector);

		foreach($matches as $m)
			if(array_search($m, $this->container, true) !== false)
				return true;

		return false;
	}

	/**
	 * Checks if this object contains the document exclusively
	 * @return boolean TRUE if this set contains the document exclusively
	 */
	public function isDocument()
	{
		return (count($this->container) === 1 && $this->container[0] instanceof DOMDocument);
	}

	/**
	 * Returns a deep clone of all elements in this set, equivalent to jQuery's clone method - clone is a reserved word in PHP
	 * @return DOMObject The cloned element
	 */
	public function duplicate()
	{
		$results = [];

		foreach($this->container as $el)
			$results []= $el->cloneNode(true);

		return new DOMObject($results);
	}

	/**
	 * Returns all direct child elements of this set
	 * @return DOMObject The children of this set
	 */
	public function contents()
	{
		$results = [];

		foreach($this->container as $el)
			foreach($el->childNodes as $node)
				$results []= $node;
		
		// TODO: Is it sensible to return query results here? How will this handle non-element nodes?
		return new DOMObject($results);
	}

	/**
	 * Gets or sets inline styles on this set. Please note that this function, unlike jQuery, cannot be used to get computed styles. Only inline styles are supported.
	 * @param array|string|null $arg An array of properties to set, a or string to get from the first element in the set
	 * @return DOMObject|string|null Will return this set for method chaining if $arg is an array, returns the CSS property value as a string if $arg is a string, returns null if $arg is a string and $val is not supplied, and the set is empty
	 * @throws \Exception When the supplied argument is neither a string nor an array
	 */
	public function css($arg=null, $val=DOMDocument::UNDEFINED)
	{
		if(is_string($arg))
		{
			if(!$this->first()->length)
				return null;
			
			if($val == DOMDocument::UNDEFINED)
				return $this->first()[0]->getInlineStyle($arg);
			
			if(!is_string($val) && !is_null($val))
				throw new \Exception("When a string is supplied as the first argument, the second argument must be a string or null");

			foreach($this->container as $el)
			{
				if(empty($val))
					$el->removeInlineStyle($arg);
				else
					$el->setInlineStyle($arg, $val);
			}
		}
		else
		{
			if(!is_array($arg))
				throw new \Exception("Invalid argument");
			
			foreach($this->container as $el)
				foreach($arg as $key => $value)
				{
					if(empty($value))
						$el->removeInlineStyle($key);
					else
						$el->setInlineStyle($key, $value);
				}
		}
		
		return $this;
	}

	/**
	 * Gets the text of all elements in the set, or sets the tewxt of all elements in the set
	 * @param string|null $text Sets the value if a string is provided, gets if null is supplied
	 * @return DOMObject|string This set for method chaining if $text is not null, the textContent of all elements in the set if $text is null
	 */
	public function text($text=null)
	{
		if($text === null)
		{
			$result = "";

			foreach($this->container as $el)
				$result .= $el->textContent;
			
			return $result;
		}

		if(!is_scalar($text))
		{
			var_dump($text);
			exit;

			throw new \Exception("Input must be scalar");
		}

		$this->clear();

		if($text == "")
			return $this;

		foreach($this->container as $el)
		{
			$node = $el->ownerDocument->createTextNode($text);
			$el->appendChild($node);
		}

		return $this;
	}
	
	/**
	 * Gets the HTML of the first node in the set, or sets the HTML of all nodes in the set
	 * @param string|null $html Sets the HTML for all elements in the set if $html is a string, gets the HTML of the first element in this set if null is supplied
	 * @return DOMObject|string This set for method chaining if $html is not null, the HTML string representing the first element in this set if $html is null
	 */
	public function html($html=null)
	{
		// NB: Getter text() returns text for all nodes, html() only returns the HTML string for the first node in jQuery. This library mirrors that behaviour.

		if(is_string($html) && $html == "")
		{
			$this->clear();
			return $this;
		}

		if($html === null)
		{
			if(!$this->length)
				return null; // NB: Undefined in jQuery
			
			$result = "";

			$subject = new DOMObject($this->first());

			foreach($subject->children() as $child)
				$result .= $child->html;

			return $result;
		}

		$temp = new DOMDocument();
		
		$str = "<div id='domdocument-import-payload___'>" . DOMDocument::convertUTF8ToHTMLEntities($html) . "</div>";
		
		$html5 = new \Masterminds\HTML5([
			'target_document' => $temp
		]);
		$html5->loadHTML($str, [
			"disable_html_ns" => true
		]);

		$body = $temp->find('#domdocument-import-payload___')->first()[0];

		foreach($this->container as $el)
		{
			while($el->childNodes->length)
				$el->removeChild($el->firstChild);

			for($child = $body->firstChild; $child != null; $child = $child->nextSibling)
			{
				$node = $el->ownerDocument->importNode($child, true);
				$el->appendChild($node);
			}
		}

		return $this;
	}

	/**
	 * Empties all elements in this set by removing all the elements children, equivalent to jQuery's empty method - empty is a reserved word in PHP.
	 * @return DOMObject This set, for method chaining
	 */
	public function clear()
	{
		foreach($this->container as $el)
		{
			while($el->childNodes->length)
				$el->removeChild($el->firstChild);
		}

		return $this;
	}

	/**
	 * Wraps this set with the specified element or set, then replaces the elements with the wrapper. This does not presently support a function as input, like it's jQuery counterpart. If this set only contains a single node and $template is a DOMElement, $template will be injected directly into the DOM. If this set contains multiple elements then the wrapper will be cloned. If $template is a DOMObject, only the first element will be used.
	 * @param DOMElement|DOMObject $wrapper The element or set to wrap this element with
	 * @return DOMObject This set, for method chaining
	 */
	public function wrap($template)
	{
		if(!($template instanceof DOMElement || $template instanceof DOMObject))
			throw new \Exception("Argument must be an instance of DOMElement or DOMObject");
		
		if($template instanceof DOMObject)
			$template = $template->first()[0];	// NB: Wrap in the first node of the set, mirror jQuery behaviour

		foreach($this->container as $el)
		{
			// NB: Only clone nodes when appending to multiple targets
			if(count($this->container) == 1)
				$wrapper = $template;
			else
				$wrapper = $template->cloneNode(true);

			$el->parentNode->replaceChild($wrapper, $el);
			$wrapper->appendChild($el);
		}

		return $this;
	}

	/**
	 * Wraps the contents of this set with the specified element or set. This does not presently support a function as input, like it's jQuery counterpart. If this set only contains a single node and $template is a DOMElement, $template will be injected directly into the DOM. If this set contains multiple elements then the wrapper will be cloned. If $template is a DOMObject, only the first element will be used.
	 * @param DOMElement $wrapper The element to wrap this elements children with
	 * @return DOMElement This element, for method chaining
	 */
	public function wrapInner($template)
	{
		if(!($template instanceof DOMElement || $template instanceof DOMObject))
			throw new \Exception("Argument must be an instance of DOMElement or DOMObject");

		if($template instanceof DOMObject)
			$template = $template->first()[0];	// NB: Wrap in the first node of the set, mirror jQuery behaviour

		foreach($this->container as $el)
		{
			// NB: Only clone nodes when appending to multiple targets
			if(count($this->container) == 1)
				$wrapper = $template;
			else
				$wrapper = $template->cloneNode(true);
			
			$nodes		= iterator_to_array($el->childNodes);
			
			$el->appendChild($wrapper);
			
			foreach($nodes as $node)
				$wrapper->appendChild($node);
		}

		return $this;
	}

	/**
	 * Returns closest ancestors of this set which matches the given selector. Please note this will NOT work on the document element presently
	 * @param string $selector The CSS selector to match
	 * @return DOMObject The results set of ancestors matching $selector
	 * @throws \Exception When $selector is empty
	 */
	public function closest($selector)
	{
		if(empty($selector))
			throw new \Exception("Argument cannot be empty");

		$results			= [];

		foreach($this->container as $el)
		{
			$documentElement = $this->implicitOwnerDocumentCast();

			for($node = $el; $node !== $documentElement; $node = $node->parentNode)
			{
				if((new DOMObject($node))->is($selector) && array_search($node, $results, true) === false)
					$results []= $node;
			}
		}

		return new DOMObject($results);
	}

	/**
	 * Returns the direct parents of all elements within this set
	 * @param string $selector The CSS selector to match
	 * @return DOMObject The results set of direct parents matching $selector (if specified)
	 */
	public function parent($selector=null)
	{
		$result = [];

		foreach($this->container as $el)
		{
			if($selector != null && !((new DOMObject($el->parentNode))->is($selector)))
				continue;

			if(array_search($el->parentNode, $result, true) !== false)
				continue;
			
			$result []= $el->parentNode;
		}

		return new DOMObject($result);
	}

	/**
	 * Hides elements within this set with inline CSS
	 * @return DOMObject This set, for method chaining
	 */
	public function hide()
	{
		$this->css([
			'display' => 'none'
		]);
		
		return $this;
	}

	/**
	 * Unhides elements within this set by removing inline CSS
	 * @return DOMObject This set, for method chaining
	 */
	public function show()
	{
		$this->css([
			'display' => ''
		]);

		return $this;
	}

	/**
	 * Returns the previous siblings of this sets elements, optionally taking a selector to match against
	 * @param string|null $selector A CSS selector to match the sibling against, or null to get the sibling immediately previous to this element
	 * @return DOMObject Set of the matching elements
	 */
	public function prev($selector=null)
	{
		if(!$this->last())
			return new DOMObject([]);
		
		$results = [];

		for($node = $this->last()[0]->previousSibling; $node != null; $node = $node->previousSibling)
		{
			if($node->nodeType != XML_ELEMENT_NODE)
				continue;
			
			if($selector == null || (new DOMObject($node))->is($selector))
				$results []= $node;
		}

		return new DOMObject($results);
	}

	/**
	 * Returns the following siblings of this sets elements, optionally taking a selector to match against. PLEASE NOTE that this is the equivalent of jQuery's "next". "next" is implemented by the Iterator interface, and cannot be used. Please use following instead of next.
	 * @param string|null $selector A CSS selector to match the sibling against, or null to get the sibling immediately previous to this element
	 * @return DOMObject Set of the matching elements
	 */
	public function following($selector=null)
	{
		if(!$this->length)
			return new DOMObject([]);
		
		$results = [];
		
		for($node = $this->first()[0]->nextSibling; $node != null; $node = $node->nextSibling)
		{
			if($node->nodeType != XML_ELEMENT_NODE)
				continue;
			
			if($selector == null || (new DOMObject($node))->is($selector))
				$results []= $node;
		}

		return new DOMObject($results);
	}

	/**
	 * Returns all siblings of elements within this set, optionally only siblings which match the provided CSS selector
	 * @param string|null The selector to match sibilings against
	 * @return DOMObject The matching siblings, or all siblings if no selector is provided
	 */
	public function siblings($selector=null)
	{
		$results	= [];

		foreach($this->container as $el)
		{
			$nodes	= iterator_to_array( $el->parentNode->childNodes );

			foreach($nodes as $node)
			{
				if($node === $el)
					continue;
				
				if($selector && !((new DOMObject($node))->is($selector)))
					continue;
				
				if(array_search($node, $results, true) !== false)
					continue;
				
				$results []= $node;
			}
		}

		return new DOMObject($results);
	}

	/**
	 * Gets or sets the value of form elements
	 * @param string|null $value NULL to get the value of this element, a string to set the value of this element
	 * @return DOMQueryResult|string|null When setting values, this DOMObject set is returned for method chaining. When getting, the value of the input, or null if no value is present
	 */
	public function val($value=null)
	{
		// NB: Getter always returns value for first node
		if($value == null)
		{
			if(!$this->length)
				return null;

			$el = $this->first()[0];

			switch(strtolower($el->nodeName))
			{
				case 'input':
					$value = $el->getAttribute('value');

					if(empty($value))
						return null; // NB: jQuery will return null when no attribute is present, where \DOMElement::getAttribute would return an empty string. Mirror jQuery behaviour here.
					
					return $value;
					break;
					
				case 'select':
					$option = $el->querySelector('option[selected]');
					if(!$option)
						return null;
					
					if($option->hasAttribute('value'))
						return $option->getAttribute('value');
					
					return $option->nodeValue;
					break;
					
				case 'option':
					if($el->hasAttribute("value"))
						return $el->getAttribute("value");
					
				default:
					return $el->nodeValue;
					break;
			}

			// NB: Never reached. nodeValue will be returned instead
			throw new \Exception("Unknown state");
		}

		// NB: Setter always sets value for all nodes
		foreach($this->container as $el)
			switch(strtolower($el->nodeName))
			{
				case 'textarea':

					$set = new DOMObject($el);
				
					$set->clear();
					$el->appendChild( $el->ownerDocument->createTextNode($value) );
					
					break;
				
				case 'select':

					$set = new DOMObject($el);
					
					$deselect = $set->find('option[selected]');
					foreach($deselect as $d)
						$d->removeAttribute('selected');
					
					if($value === null)
						return $el;
					
					$option = $set->find('option[value="' . $value . '"]')->first();
					
					if(!$option)
						trigger_error('Option with value "' . $value . '" not found in "' . ($el->getAttribute('name')) . '"', E_USER_WARNING);
					else
						$option->setAttribute('selected', 'selected');
					
					break;
					
				case 'input':
					$el->setAttribute("value", $value);
					break;
				
				default:
					$el->nodeValue = $value;
					break;
			}
		
		return $this;
	}

	/**
	 * Tests if the specified class exists on at least one elements class attribute wihtin this set
	 * @param string $name The classname
	 * @return boolean FALSE if no nodes in this set have the specified classname, TRUE if at least one node does
	 */
	public function hasClass($name)
	{
		foreach($this->container as $el)
		{
			if(!$el->hasAttribute("class"))
				continue;
			
			if(preg_match('/\\b' . $name . '\\b/', $el->getAttribute('class')))
				return true;
		}

		return false;
	}

	/**
	 * Removes the specified class from all elements within this set
	 * @param string $name The classname
	 * @return DOMObject This set, for method chaining
	 */
	public function removeClass($name)
	{
		foreach($this->container as $el)
		{
			if(!$el->hasAttribute("class"))
				continue;
			
			$class = trim(
				preg_replace('/\s{2,}/', ' ',
					preg_replace('/\\b' . preg_quote($name) . '\\b/', ' ', $el->getAttribute('class'))
				)
			);
				
			$el->setAttribute('class', $class);
		}

		return $this;
	}

	/**
	 * Adds class(es) to all elements within this set
	 * @param string $name The classname
	 * @return DOMObject This set, for method chaining
	 */
	public function addClass($name)
	{
		foreach($this->container as $el)
		{
			if((new DOMObject([$el]))->hasClass($name))
				continue;
				
			$class = ($el->hasAttribute('class') ? $el->getAttribute('class') : '');
			$el->setAttribute('class', $class . (strlen($class) > 0 ? ' ' : '') . $name);
		}

		return $this;
	}

	/**
	 * Method for working with attributes on this set
	 * @param string|array A string to get or set single a attribute, an array of key value pairs to set multiple attributes
	 * @param null|string $val A string, if the first argument is a string, or NULL if the first argument is an array
	 * @return string|DOMObject A string when retrieving data, this set for method chaining when setting
	 * @throws \Exception When $arg is not supplied
	 * @throws \Exception When first argument is neither a string nor an array
	 * @throws \Exception When the first argument is a string, and the second argument is provided but not scalar
	 * @throws \Exception When the first argument is a key value array, but the second argument is also set
	 * @throws \Exception When the supplied key value array has a non-string key
	 * @throws \Exception When the supplied key value array has a non-scalar value
	 */
	public function attr($arg, $val=null)
	{
		if(empty($arg))
			throw new \Exception("Method must be called with at least one argument");
		
		if(!is_string($arg) && !is_array($arg))
			throw new \Exception("First argument must be a string attribute name, or a key value array of attributes to set");
		
		if($val === null && is_string($arg))
		{
			if(!$this->first()->length)
				return null;
			
			$first = $this->first()[0];

			// NB: If the attribute doesn't exist, return null, as the implementation of the PHP \DOMElement class would return an empty string. This is not how jQuery behaves. With thanks to https://github.com/warhuhn/
			if(!$first->hasAttribute($arg))
				return null;

			return $first->getAttribute($arg);
		}

		if(is_string($arg))
		{
			if(!is_scalar($val))
				throw new \Exception("When the first argument is a string, and a second argument is provided, the second argument must also be scalar, to set a single attribute");
			
			foreach($this->container as $el)
				$el->setAttribute($arg, $val);
		}
		else
		{
			if($val !== null)
				throw new \Exception("A second argument cannot be provided when the first argument is a key value array of attributes to set");
			
			foreach($this->container as $el)
				foreach($arg as $key => $value)
				{
					if(!is_string($key))
						throw new \Exception("Key must be a string");
					
					if(!is_scalar($value))
						throw new \Exception("Value must be scalar");
					
					$el->setAttribute($key, $value);
				}
		}

		return $this;
	}

	/**
	 * Method for working with props (eg checkbox and radio)
	 * @param string|array $name Name of the property, usually "checked"
	 * @param null|string $val A string, value to set to the prop
	 * @return string|DOMObject $this DOMObject for method chaining
	 */
	public function prop($name, $value=null)
	{
		if(empty($this->container))
			return null; // NB: jQuery would return undefined here
		
		$el = $this->first()[0];

		if($value === null)
		{
			$result = preg_match('/' . preg_quote($name) . '/i', $el->getAttribute($name));

			if($result === false)
				throw new \Exception("Error in pattern matching in prop");

			return $result == 1;
		}

		foreach($this->container as $el)
		{
			if($value)
				$el->setAttribute($name, $name);
			else
				$el->removeAttribute($name);
		}

		return $this;
	}

	/**
	 * Removes the named attribute
	 * @param string $name Name of the attribute to remove
	 */
	public function removeAttr($name)
	{
		foreach($this->container as $node)
			if($node instanceof DOMElement)
				$node->removeAttribute($name);
		
		return $this;
	}

	/**
	 * Method for working with data- attributes on this set
	 * @param null|string|array $arg If both arguments are null / not provided, this function will return all data- attributes as an associative array. If a string is provided, it will be treated as a name and the value of the relevant data- attribute will be returned. If an array is provided, it will be used to set multiple data- attributes on the set.
	 * @param null|string $val A second argument, this can only be used if $arg is a string
	 * @return string|DOMObject A string when retrieving data, this set for method chaining when setting data
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
			
			if(!$this->length)
				return null;

			// Both arguments are null, return all data
			$results = [];
			
			foreach($this->first()[0]->attributes as $name => $value)
				if(preg_match('/^data-/', $name))
					$results[preg_replace('/^data-/', '', $name)] = $value;
			
			return $results;
		}
		
		if(is_string($arg))
		{
			if(is_scalar($val))
			{
				foreach($this->container as $el)
					$el->setAttribute("data-$arg", $val);
				
				return $this;
			}
			else if($val == null)
			{
				if(!$this->length)
					return null;

				return $this->first()[0]->getAttribute("data-$arg");
			}
			else
				throw new \Exception("Invalid arguments");
		}
		else if(is_array($arg))
		{
			if($val != null)
				throw new \Exception("Argument is an array, a second argument should not be provided");
			
			// Looking to set multiple data- attributes here
			foreach($this->container as $el)
				foreach($arg as $name => $value)
					$el->setAttribute("data-$name", $value);
			
			return $this;
		}

		throw new \Exception("Invalid arguments");
	}

	/**
	 * Inserts conmtent after the elements within this set
	 * @param DOMElement|DOMObject|array|string $arg The element(s) or text to insert
	 * @return DOMObject This set, for method chaining
	 * @throws \Exception When the supplied argument is not a DOMElement, DOMObject, array or string
	 * @throws \Exception When an array is supplied with a non-DOMElement element
	 */
	public function after($arg)
	{
		if(!$this->length)
			return $this; // NB: Need ownerDocument below, so if we have no nodes, just bail here.

		if($arg instanceof DOMElement)
			$nodes = [$arg];
		else if(is_array($arg) || $arg instanceof DOMObject)
			$nodes = $arg;
		else if(is_string($arg))
			$nodes = [$this->first()[0]->ownerDocument->createTextNode($arg)];
		else
			throw new \Exception("Invalid argument");
	
		foreach($nodes as $node)
			if(!($node instanceof DOMElement))
				throw new \Exception("Non-DOMElement argument supplied in array");

		foreach($this->container as $el)
		{
			if($el->nextSibling)
			{
				$before = $el->nextSibling;

				foreach($nodes as $node)
					$el->parentNode->insertBefore($node->cloneNode(true), $before);
			}
			else
				foreach($nodes as $node)
					$el->parentNode->appendChild($node->cloneNode(true));
		}

		return $this;
	}

	/**
	 * Inserts conmtent before the elements within this set
	 * @param DOMElement|DOMObject|array|string $arg The element(s) or text to insert
	 * @return DOMObject This set, for method chaining
	 * @throws \Exception When the supplied argument is not a DOMElement, DOMObject, array or string
	 * @throws \Exception When an array is supplied with a non-DOMElement element
	 */
	public function before($arg)
	{
		if(!$this->length)
			return $this; // NB: Need ownerDocument below, so if we have no nodes, just bail here.

		if($arg instanceof DOMElement)
			$nodes = [$arg];
		else if(is_array($arg) || $arg instanceof DOMObject)
			$nodes = $arg;
		else if(is_string($arg))
			$nodes = [$this->first()[0]->ownerDocument->createTextNode($arg)];
		else
			throw new \Exception("Invalid argument");

		foreach($nodes as $node)
			if(!($node instanceof DOMElement))
				throw new \Exception("Non-DOMElement argument supplied in array");
		
		foreach($this->container as $el)
			foreach($nodes as $node)
				$el->parentNode->insertBefore($node->cloneNode(true), $el);

		return $this;
	}

	/**
	 * Appends content inside the elements within this set
	 * @param DOMElement|DOMObject|array|string $subject The content to insert, can be an element, an array or result set of elements, or a string
	 * @return DOMObject This set, for method chaining
	 * @throws \Exception When the supplied argument is not a DOMElement, DOMObject, array or string
	 * @throws \Exception When an array is supplied with a non-DOMElement element
	 */
	public function append($subject)
	{
		foreach($this->container as $el)
		{
			if(is_array($subject) || $subject instanceof DOMObject)
			{
				foreach($subject as $node)
				{
					if(!($node instanceof \DOMNode))
						throw new \Exception("Element must be a DOMNode");

					// NB: Only clone nodes when appending to multiple targets
					if(count($this->container) == 1)
						$el->appendChild( $node );
					else
						$el->appendChild( $node->cloneNode(true) );
				}
			}
			else if(is_string($subject))
			{
				$el->appendChild( $el->ownerDocument->createTextNode( $subject ) );
			}
			else if($subject instanceof DOMElement)
				$el->appendChild($subject->cloneNode(true));
			else
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMObject, an instance of DOMElement or a string");
		}

		return $this;
	}

	/**
	 * Prepends content inside the elements within this set
	 * @param DOMElement|DOMObject|array|string $subject The content to insert, can be an element, an array or result set of elements, or a string
	 * @return DOMObject This set, for method chaining
	 * @throws \Exception When the supplied argument is not a DOMElement, DOMObject, array or string
	 * @throws \Exception When an array is supplied with a non-DOMElement element
	 */
	public function prepend($subject)
	{
		foreach($this->container as $el)
		{
			if(is_array($subject) || $subject instanceof DOMObject)
			{
				$originalFirst = $el->firstChild;
				
				foreach($subject as $node)
				{
					if(!($node instanceof DOMElement))
						throw new \Exception("Element must be a DOMElement");

					// NB: Only clone nodes when appending to multiple targets
					if(count($this->container) == 1)
						$el->insertBefore($node, $originalFirst);
					else
						$el->insertBefore($node->cloneNode(true), $originalFirst);
				}
			}
			else if(is_string($subject))
			{
				$el->insertBefore( $el->ownerDocument->createTextNode( $subject ), $el->firstChild );
			}
			else if($subject instanceof DOMElement)
			{
				if(count($this->container) == 1)
					$el->insertBefore($subject, $el->firstChild);
				else
					$el->insertBefore($subject->cloneNode(true), $el->firstChild);
			}
			else
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMObject, an instance of DOMElement or a string");
		}
		
		return $this;
	}

	/**
	 * Removes all elements within this set from the DOM tree
	 * @return DOMObject This set, for method chaining
	 */
	public function remove()
	{
		foreach($this->container as $el)
		{
			if($el->parentNode)
				$el->parentNode->removeChild($el);
		}

		return $this;
	}

	/**
	 * @param DOMElement|DOMObject|array|string $arg The element(s) or text to insert
	 * @return DOMObject This set, for method chaining
	 * @throws \Exception When the supplied argument is not a DOMElement, DOMObject, array or string
	 * @throws \Exception When an array is supplied with a non-DOMElement element
	 */
	public function replaceWith($subject)
	{
		foreach($this->container as $el)
		{
			$next = $el->nextSibling;

			if($subject instanceof DOMObject || is_array($subject))
			{
				foreach($subject as $node)
				{
					if(!($node instanceof DOMElement))
						throw new \Exception("Element must be a DOMElement");

					if(!$next)
						$el->parentNode->appendChild($node->cloneNode(true));
					else
						$el->parentNode->insertBefore($node->cloneNode(true), $next);
				}
			}
			else if($subject instanceof DOMElement || is_string($subject))
			{
				if(is_string($subject))
					$node = $el->ownerDocument->createTextNode($subject);
				else
					$node = $subject->cloneNode(true);

				if(!$next)
					$el->parentNode->appendChild($node);
				else
					$el->parentNode->insertBefore($node, $next);
			}
			else
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMObject, an instance of DOMElement or a string");
	
			$el->parentNode->removeChild($el);
		}

		return $this;
	}

	/**
	 * Imports the supplied subject and appends it to this set. Noop when this set is empty.
	 * @param string|DOMElement|DOMObject The subject to import to this set
	 */
	public function import($subject)
	{
		if(empty($this->container))
			return $this;
		
		$document = $this->implicitOwnerDocumentCast();
		$imported = $document->import($subject);

		$this->append($imported);

		return $this;
	}

	/**
	 * Returns this object as a HTML fragment string.
	 * @return string Elements in this object rendered to a HTML string fragment
	 */
	public function __toString()
	{
		return $this->html;
	}
}

class_alias(DOMObject::class, '\PerryRylance\DOMDocument\DOMQueryResults');