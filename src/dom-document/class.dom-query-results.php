<?php

namespace PerryRylance\DOMDocument;

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
	public function __construct(array $arr = null)
	{
		if(!empty($arr))
			$this->container = $arr;
		else
			$this->container = array();
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
			if(!method_exists($element, $name))
			{
				if( version_compare(phpversion(), '8.0.0', '>=') || $name != 'remove' )
					throw new \Exception("No such method '$name' on " . get_class($element));
			}
			
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
			$this->container[] = $value;
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
	
	public function next()
	{
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
	 */
	public function last()
	{
		if(count($this->container) == 0)
			return null;
	
		return $this->container[count($this->container) - 1];
	}
}
