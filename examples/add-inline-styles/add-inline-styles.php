<?php

use PerryRylance\DOMDocument;

require_once "../../src/DOMDocument.php";
require_once "../../vendor/autoload.php";

$css			= file_get_contents("style.css");
$parser			= new \Sabberworm\CSS\Parser($css);
$stylesheet		= $parser->parse();

$document		= new DOMDocument();
$document->load("content.html");

foreach ($stylesheet->getAllDeclarationBlocks() as $block) {

	$pairs		= [];

	foreach($block->getRules() as $rule)
	{
		$key	= $rule->getRule();
		$value	= $rule->getValue();

		$pairs[$key] = $value;
	}
	
    foreach ($block->getSelectors() as $selector)
	{
		$document
			->find($selector->getSelector())
			->css($pairs);
	}

}

echo $document->saveHTML();