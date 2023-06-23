<?php

namespace PerryRylance\DOMDocument;

use PerryRylance\DOMDocument;

/**
 * This class is used to represent elements, and implements many jQuery-like functions for the elements
 */
class DOMElement extends \DOMElement
{
	/**
	 * Constructor for a DOMElement. This should not be called directly. Use the document's createElement method instead
	 */
	public function __construct(string $qualifiedName)
	{
		\DOMElement::__construct($qualifiedName);
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
	
	/**
	 * @internal sort function for DOM position sort
	 * @ignore
	 */
	private static function sortByDOMPosition($a, $b)
	{
		return ($a->isBefore($b) ? -1 : 1);
	}
	
	private function implicitCastParentNode(): DOMElement
	{
		return $this->parentNode;
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
			return $this->implicitCastParentNode()->isBefore($other->parentNode);
		
		if($this_depth > $other_depth)
		{
			$ancestor = $this;
			$ancestor_depth = $this_depth;
			
			while($ancestor_depth > $other_depth)
			{
				$ancestor = $ancestor->parentNode;
				$ancestor_depth--;
			}
			
			return ancestor->isBefore($other);
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
	 * @return DOMObject The result set matching the specified selector
	 */
	public function querySelectorAll($selector, array $options=[])
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
		
		return new DOMObject($results);
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
	 * @ignore
	 */
	public function getInlineStyles()
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
	public function getInlineStyle($name)
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
	public function removeInlineStyle($name)
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
	public function setInlineStyle($name, $value)
	{
		$this->removeInlineStyle($name);
		$style = $this->getAttribute('style');
		
		$style = rtrim($style, ";");
		
		$this->setAttribute('style', "$style;$name:$value;");
	}
}
