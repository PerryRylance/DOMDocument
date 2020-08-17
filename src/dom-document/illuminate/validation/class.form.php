<?php

namespace PerryRylance\DOMDocument\Illuminate\Validation;

use Illuminate\Validation\Rule;
use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMDocument\DOMQueryResults;

class Form
{
	public static function getValidationRules($arg)
	{
		if($arg instanceof DOMQueryResults)
		{
			if(empty($arg))
				throw new \Exception("Result set is empty");
			
			$form = $arg[0];
		}
		else
			$form = $arg;
		
		if(!($form instanceof DOMElement))
			throw new \Exception("No DOMElement found in argument");
		
		if(!$form->is("form"))
			throw new \Exception("Method can only be used on form elements");
		
		$inputs		= $form->find("input, textarea, select");
		$rules		= [];
		
		foreach($inputs as $input)
		{
			$rule	= [];
			$name	= $input->attr("name");
			
			if(empty($name))
				continue;
			
			if($json = $input->attr("data-laravel-rules"))
			{
				if(!($arr = json_decode($json)))
					throw new \Exception("Invalid JSON in data-laravel-rules");
				
				foreach($arr as $value)
					$rule []= $value;
			}
			
			if($input->is("[required]"))
				$rule []= "required";
			
			if($input->is("[pattern]"))
				$rule []= "regex:/" . $input->attr("pattern") . "/";
			
			if($input->attr("type") == "number")
				$rule []= "numeric";
			
			if($input->is("select"))
			{
				$values = [];
				
				foreach($input->children("option") as $option)
					$values []= $option->val();
				
				$rule []= Rule::in($values);
			}
			
			if(empty($rule))
				continue;
			
			$rules[$name] = $rule;
		}
		
		return $rules;
	}
}