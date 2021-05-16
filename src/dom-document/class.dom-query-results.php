<?php

namespace PerryRylance\DOMDocument;

use PerryRylance\DOMDocument;

/**
 * This class is used to represent a results set of matched elements, in much the same way as a jQuery array works. Any methods supported by DOMElement can be called on a DOMQueryResults list.
 */
class DOMQueryResults implements \ArrayAccess, \Countable, \Iterator
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
			else if($arr instanceof DOMQueryResults)
				$this->container = $arr->toArray();
			else if($arr instanceof DOMElement)
				$this->container = [$arr];
			else
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMQueryResults, DOMElement, or omitted");

			// NB: Sanity check
			foreach($this->container as $el)
				if(!($el instanceof DOMElement))
					throw new \Exception("All elements must be instances of DOMElement");
		}
	}
	
	public function toArray()
	{
		// TODO: Test that this returns by value and not by reference. External code should NOT manipulate this set
		return $this->container;
	}

	/**
	 * Magic method for function calls, this can be used to call any methods supported by DOMElement on the entire result set stored in this DOMQueryResults set
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
			// NB: PHP >= 8 includes a native remove method, PHP < 8 does not, we use a magic method for compatibility with both. This means the method_exists check below will fail. We do a special check for this case here.
			if(!method_exists($element, $name) && !$element->hasCompatibilityMethod($name))
				throw new \Exception("No such method '$name' on " . get_class($element));
			
			$result = call_user_func_array(
				array($element, $name),
				$arguments
			);
			
			if($result instanceof DOMQueryResults)
			{
				if($set == null)
					$set = [];
				
				foreach($result as $el)
					$set []= $el;
			}
		}
		
		if(is_array($set))
			return new DOMQueryResults($set);
		
		return $this;
	}
	
	public function offsetExists($offset)
	{
		return isset($this->container[$offset]);
	}
	
	public function offsetGet($offset)
	{
		return isset($this->container[$offset]) ? $this->container[$offset] : null;
	}
	
	public function offsetSet($offset, $value)
	{
		if(!($value instanceof DOMElement))
			throw new \Exception("Only DOMElement is permitted in query results");
		
		if(is_null($offset))
			$this->container []= $value;
		else
			$this->container[$offset] = $value;
	}
	
	public function offsetUnset($offset)
	{
		unset($this->container[$offset]);
	}
	
	public function count()
	{
		return count($this->container);
	}
	
	public function current()
	{
		return $this->container[$this->index];
	}
	
	public function next($arg=null)
	{
		if(!empty($arg))
			trigger_error("DOMQueryResults::next is an implementation of Iterator::next, which takes no arguments. Did you mean to call jQuery-like DOMQueryResults::following instead?", E_USER_WARNING);

		$this->index++;
	}
	
	public function key()
	{
		return $this->index;
	}
	
	public function valid()
	{
		return isset($this->container[$this->key()]);
	}
	
	public function rewind()
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
	 * @return DOMQueryResults This result set, for method chaining
	 * @throws \Exception When the supplied argument is not callable
	 */
	public function each($callback)
	{
		if(!is_callable($callback))
			throw new \Exception("Argument must be callable");
		
		foreach($this->container as $element)
			$callback($element);
		
		return $this;
	}
	
	/**
	 * Filters this result set using the given subject
	 * @param string|callable $subject The subject to filter with. A string will be interpreted as a CSS selector, a callback can be used to implement custom filtering logic.
	 * @return DOMQueryResults The filtered result set
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
	
		return new DOMQueryResults($results);
	}
	
	/**
	 * Returns the first result in this set, or null if the set is empty
	 * @return DOMElement|null The first element in the set, or null if the set is empty
	 * @todo Review this, it should return a results set, should it be any empty array on an empty set? I expect so
	 */
	public function first()
	{
		if(count($this->container) == 0)
			return null;
		
		return $this->container[0];
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
	
		return $this->container[count($this->container) - 1];
	}

	/**
	 * Finds any descendant elements which match the supplied CSS selector. Equivalent to querySelectorAll
	 * @param string $selector The CSS selector
	 * @return DOMQueryResults The result set matching the specified selector
	 */
	public function find($selector)
	{
		$result = [];

		$this->each(function($el) use (&$result, $selector) {

			$subset	= $el->querySelectorAll($selector, [
				"sort" => true
			]);

			$result = array_merge($result, $subset->toArray());

		});

		return new DOMQueryResults($result);
	}

	/**
	 * Returns this elements children, or if a selector is supplied, only children of this element which match the selector
	 * @param string $selector The CSS selector to match
	 * @return DOMQueryResults Any children of this element which match the selector, or all children if no selector is specified
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
			return new DOMQueryResults($children);
		
		$results = [];
		
		foreach($children as $child)
		{
			if((new DOMQueryResults($child))->is($selector))
				$results []= $child;
		}
		
		return new DOMQueryResults($results);
	}

	/**
	 * Checks if any element matches the supplied selector.
	 * @param string $selector The CSS selector
	 * @return boolean TRUE if this element matches the supplied selector, FALSE otherwise
	 */
	public function is($selector)
	{
		$matches = $this->parent()->find($selector);

		foreach($matches as $m)
			if(array_search($m, $this->container, true) !== false)
				return true;

		return false;
	}

	public function duplicate()
	{
		$results = [];

		foreach($this->container as $el)
			$results []= $el->cloneNode(true);

		return new DOMQueryResults($results);
	}

	public function contents()
	{
		$results = [];

		foreach($this->container as $el)
			foreach($el->childNodes as $node)
				$results []= $node;
		
		// TODO: Is it sensible to return query results here? How will this handle non-element nodes?
		return new DOMQueryResults($results);
	}

	public function css($arg=null)
	{
		if(is_string($arg))
		{
			if(!$this->first())
				return null;

			return $this->first()->getInlineStyle($arg);
		}
		
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
		
		return $this;
	}

	public function text($text=null)
	{
		if($text == null)
		{
			$result = "";

			foreach($this->container as $el)
				$result .= $el->textContent;
			
			return $result;
		}

		if(!is_string($text))
			throw new \Exception("Input must be text");

		$this->clear();

		foreach($this->container as $el)
		{
			$node = $el->ownerDocument->createTextNode($text);
			$el->appendChild($node);
		}

		return $this;
	}
	
	public function html($html=null)
	{
		// NB: Getter text() returns text for all nodes, html() only returns the HTML string for the first node in jQuery. This library mirrors that behaviour.

		if($html == null)
		{
			if(!$this->first())
				return null; // NB: Undefined in jQuery
			
			return $this->first()->html;
		}

		$temp = new DOMDocument();
		
		$str = "<div id='domdocument-import-payload___'>" . DOMDocument::convertUTF8ToHTMLEntities($html) . "</div>";
		
		$html5 = new \Masterminds\HTML5([
			'target_document' => $temp
		]);
		$html5->loadHTML($str, [
			"disable_html_ns" => true
		]);

		$body = $temp->find('#domdocument-import-payload___')->first();

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

	public function clear()
	{
		foreach($this->container as $el)
			while($el->childNodes->length)
				$el->removeChild($el->firstChild);

		return $this;
	}

	public function wrap($template)
	{
		if(!($template instanceof DOMElement || $template instanceof DOMQueryResults))
			throw new \Exception("Argument must be an instance of DOMElement or DOMQueryResults");
		
		if($template instanceof DOMQueryResults)
			$template = $template->first();	// NB: Wrap in the first node of the set, mirror jQuery behaviour

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

	public function wrapInner($template)
	{
		if(!($template instanceof DOMElement || $template instanceof DOMQueryResults))
			throw new \Exception("Argument must be an instance of DOMElement or DOMQueryResults");

		if($template instanceof DOMQueryResults)
			$template = $template->first();	// NB: Wrap in the first node of the set, mirror jQuery behaviour

		foreach($this->container as $el)
		{
			$wrapper	= $template->cloneNode(true);
			$nodes		= iterator_to_array($el->childNodes);
			
			$el->appendChild($wrapper);
			
			foreach($nodes as $node)
				$wrapper->appendChild($node);
		}

		return $this;
	}

	// NB: This element will NOT work on the document element (HTML)
	public function closest($selector)
	{
		$results			= [];

		foreach($this->container as $el)
		{
			$documentElement = $el->ownerDocument->getDocumentElementSafe();

			for($node = $el; $node !== $documentElement; $node = $node->parentNode)
			{
				if((new DOMQueryResults($node))->is($selector) && array_search($node, $results, true) === false)
					$results []= $node;
			}
		}

		return new DOMQueryResults($results);
	}

	public function parent($selector=null)
	{
		$result = [];

		foreach($this->container as $el)
		{
			if($selector != null && !((new DOMQueryResults($el->parentNode))->is($selector)))
				continue;

			if(array_search($el->parentNode, $result, true) !== false)
				continue;
			
			$result []= $el->parentNode;

			if($el->parentNode instanceof DOMDocument)
			{
				print_r($el->html);
				
				throw new \Exception("Whoops...");
			}
		}

		return new DOMQueryResults($result);
	}

	public function hide()
	{
		$this->css([
			'display' => 'none'
		]);
		
		return $this;
	}

	public function show()
	{
		$this->css([
			'display' => ''
		]);

		return $this;
	}

	public function prev($selector=null)
	{
		if(!$this->last())
			return new DOMQueryResults([]);
		
		$results = [];

		for($node = $this->last()->previousSibling; $node != null; $node = $node->previousSibling)
		{
			if($node->nodeType != XML_ELEMENT_NODE)
				continue;
			
			if($selector == null || (new DOMQueryResults($node))->is($selector))
				$results []= $node;
		}

		return new DOMQueryResults($results);
	}

	public function following($selector=null)
	{
		if(!$this->first())
			return new DOMQueryResults([]);
		
		$results = [];
		
		for($node = $this->first()->nextSibling; $node != null; $node = $node->nextSibling)
		{
			if($node->nodeType != XML_ELEMENT_NODE)
				continue;
			
			if($selector == null || (new DOMQueryResults($node))->is($selector))
				$results []= $node;
		}

		return new DOMQueryResults($results);
	}

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
				
				if($selector && !((new DOMQueryResults($node))->is($selector)))
					continue;
				
				if(array_search($node, $results, true) !== false)
					continue;
				
				$results []= $node;
			}
		}

		return new DOMQueryResults($results);
	}

	public function val($value=null)
	{
		// NB: Getter always returns value for first node
		if($value == null)
		{
			if(!$this->first())
				return null;

			$el = $this->first();

			switch(strtolower($el->nodeName))
			{
				case 'input':
					$type = ($el->hasAttribute('type') ? $el->getAttribute('type') : 'text');
					switch($type)
					{
						case 'radio':
						case 'checkbox':
							return $el->hasAttribute('checked');
							break;
						
						default:
							return $el->getAttribute('value');
							break;
					}
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
				
					$el->clear();
					$el->appendChild( $el->ownerDocument->createTextNode($value) );
					
					break;
				
				case 'select':
					
					$deselect = $el->find('option[selected]');
					foreach($deselect as $d)
						$d->removeAttribute('selected');
					
					if($value === null)
						return $el;
					
					$option = $el->find('option[value="' . $value . '"]')->first();
					
					if(!$option)
						trigger_error('Option with value "' . $value . '" not found in "' . ($el->getAttribute('name')) . '"', E_USER_WARNING);
					else
						$option->setAttribute('selected', 'selected');
					
					break;
					
				case 'input':
					
					if(!$el->hasAttribute('type') || $el->getAttribute('type') == 'text')
					{
						if(is_scalar($value))
							$el->setAttribute('value', $value);
					}
					else switch(strtolower($el->getAttribute('type')))
					{
						case 'radio':
							if($el->hasAttribute('value') && $el->getAttribute('value') == $value)
								$el->setAttribute('checked', 'checked');
							else
								$el->removeAttribute('checked');
							break;
							
						case 'checkbox':
							if(!empty($value) && $value != false)
								$el->setAttribute('checked', 'checked');
							else
								$el->removeAttribute('checked');
							break;
							
						default:
							$el->setAttribute('value', $value);
							break;
					}
					
					break;
				
				default:
					$el->nodeValue = $value;
					break;
			}
		
		return $this;
	}

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

	public function addClass($name)
	{
		foreach($this->container as $el)
		{
			if((new DOMQueryResults([$el]))->hasClass($name))
				continue;
				
			$class = ($el->hasAttribute('class') ? $el->getAttribute('class') : '');
			$el->setAttribute('class', $class . (strlen($class) > 0 ? ' ' : '') . $name);
		}

		return $this;
	}

	public function attr($arg, $val=null)
	{
		if(empty($arg))
			throw new \Exception("Method must be called with at least one argument");
		
		if(!is_string($arg) && !is_array($arg))
			throw new \Exception("First argument must be a string attribute name, or a key value array of attributes to set");
		
		if($val === null && is_string($arg))
		{
			if(!$this->first())
				return null;

			return $this->first()->getAttribute($arg);
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

	public function data($arg=null, $val=null)
	{
		if($arg == null)
		{
			if($val != null)
				throw new \Exception("Argument is null but value is provided, invalid arguments");
			
			if(!$this->first())
				return null;;

			// Both arguments are null, return all data
			$results = [];
			
			foreach($this->first()->attributes as $name => $value)
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
				if(!$this->first())
					return null;

				return $this->first()->getAttribute("data-$arg");
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

	public function after($arg)
	{
		if(!$this->first())
			return $this; // NB: Need ownerDocument below, so if we have no nodes, just bail here.

		if($arg instanceof DOMElement)
			$nodes = [$arg];
		else if(is_array($arg) || $arg instanceof DOMQueryResults)
			$nodes = $arg;
		else if(is_string($arg))
			$nodes = [$this->first()->ownerDocument->createTextNode($arg)];
		else
			throw new \Exception("Invalid argument");
	
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

	public function before($arg)
	{
		if(!$this->first())
			return $this; // NB: Need ownerDocument below, so if we have no nodes, just bail here.

		if($arg instanceof DOMElement)
			$nodes = [$arg];
		else if(is_array($arg) || $arg instanceof DOMQueryResults)
			$nodes = $arg;
		else if(is_string($arg))
			$nodes = [$this->first()->ownerDocument->createTextNode($arg)];
		else
			throw new \Exception("Invalid argument");
		
		foreach($this->container as $el)
			foreach($nodes as $node)
				$el->parentNode->insertBefore($node->cloneNode(true), $el);

		return $this;
	}

	public function append($subject)
	{
		foreach($this->container as $el)
		{
			if(is_array($subject) || $subject instanceof DOMQueryResults)
			{
				foreach($subject as $node)
				{
					if(!($node instanceof DOMElement))
						throw new \Exception("Element must be a DOMElement");

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
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMQueryResults, an instance of DOMElement or a string");
		}

		return $this;
	}

	public function prepend()
	{
		foreach($this->container as $el)
		{
			if(is_array($subject) || $subject instanceof DOMQueryResults)
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
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMQueryResults, an instance of DOMElement or a string");
		}
		
		return $this;
	}

	public function remove()
	{
		foreach($this->container as $el)
		{
			if($el->parentNode)
				$el->parentNode->removeChild($el);
		}

		return $this;
	}

	public function replaceWith($subject)
	{
		foreach($this->container as $el)
		{
			$next = $el->nextSibling;

			if($subject instanceof DOMQueryResults || is_array($subject))
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
				throw new \Exception("Argument must be an array of DOMElements, an instance of DOMQueryResults, an instance of DOMElement or a string");
	
			$el->parentNode->removeChild($el);
		}

		return $this;
	}
}
