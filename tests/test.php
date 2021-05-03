<?php

require_once('../vendor/autoload.php');
require_once('../src/class.dom-document.php');

use \PerryRylance\DOMDocument as DOMDocument;

$document = new DOMDocument();
$document->load("sample.html");

$tests = [
	[
		'caption' => 'Selection and query matching',
		
		'assertion' => function() {
			
			global $document;
			return $document->querySelector("body")->is("body");
			
		}
	],

	[
		'caption' => 'DOM sorting and ordering',
	
		'operation' => function() {
			
			global $document;
			
			$before	= $document->querySelector(".before");
			$after	= $document->querySelector(".after");
			
			if($before->isBefore($after))
			{
				$before->text("This element is before");
				$after->text("This element is after");
			}
			else
			{
				$before->text("This element is before, but isBefore returned false");
				$after->text("This element is after, but the test failed");
			}
			
		},
		'assertion' => function() {
			
			global $document;
			
			return (
				$document->querySelector(".before")->text() == "This element is before"
				&&
				$document->querySelector(".after")->text() == "This element is after"
			);
			
		}
	],
	
	[
		"caption" => "Filtering with children()",
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			$elements = $video->children("[type='video/ogg']");
			
			return count($elements) == 1;
			
		}
	],
	
	[
		"caption" => "Inserting with before() and after(), selecting with prev() and next()",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			$before = $document->createElement("div");
			$before->text("This is before the video");
			$before->addClass("inserted-before");
			
			$after = $document->createElement("div");
			$after->text("This is after the video");
			$after->addClass("inserted-after");
			
			$video->before($before);
			$video->after($after);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			return (
				$video->prev(".inserted-before")->is(".inserted-before")
				&&
				$video->next(".inserted-after")->is(".inserted-after")
			);
		}
	],
	
	[
		"caption" => "Class manipulation and checking",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			$video->addClass("dynamically-added-class");
			$video->addClass("class-to-remove");
			$video->removeClass("class-to-remove");
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			return ($video->hasClass("dynamically-added-class") && !$video->hasClass("class-to-remove"));
			
		}
		
	],
	
	[
		"caption" => "Inline CSS manipulation",
		
		"operation" => function() {
			
			global $document;
			
			$cssTest = $document->createElement("div");
			$cssTest->setAttribute("id", "css-test");
			
			$video = $document->querySelector("video");
			$video->parentNode->append($cssTest);
			
			$video->css([
				"border" => "3px solid red",
				"margin" => "1em",
				"filter" => "invert(100%)"
			]);
			
			$cssTest->text($video->css("border"));
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			return $document->querySelector("#css-test")->text() == "3px solid red";
			
		}
	],
	
	[
	
		"caption" => "HTML string manipulation",
		
		"operation" => function() {
			
			global $document;
			
			$div = $document->createElement("div");
			$div->setAttribute("id", "html-test");
			$document->querySelector("body")->append($div);
			
			$div->html("<div class='html-test-success'>This element will be used to test <span style='background-color: green;'>HTML parsing!</span></div>");
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			return $document->querySelector(".html-test-success") != null;
			
		}
		
	],
	
	[
		"caption" => "Attribute handling",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			$video->attr("controls", "true");
			$video->attr([
				"muted"		=> true,
				"autoplay"	=> true
			]);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			return (
				$video->attr("controls") == "true"
				&& $video->attr("muted")
				&& $video->attr("autoplay")
			);
			
		}
		
	],
	
	[
	
		"caption" => "data- attribute interface",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			$video->data("test", "123");
			$video->data([
				"one" => "1",
				"two" => "2",
				"three" => 3
			]);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			return (
				$video->data("test") == "123"
				&& $video->data("one") == "1"
				&& $video->data("two") == "2"
				&& $video->data("three") == "3"
			);
			
		}
	
	],
	
	[
		
		"caption" => "Node removal",
		
		"operation" => function() {
			
			global $document;
			
			$document->find(".node-to-be-removed")->remove();
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			return $document->querySelector(".node-to-be-removed") == null;
			
		}
	
	],
	
	[
		
		"caption" => "Node clearance",
		
		"operation" => function() {
			
			global $document;
			
			$div = $document->createElement("div");
			
			$div->id = "clearance-test";
			$div->html("Some test text <span>and a child element</span>");
			
			$document->body->appendChild($div);
			
			$div->clear();
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			return $document->querySelector("#clearance-test")->text() == "";
			
		}
	
	],
	
	[
		
		"caption" => "Node wrapping, inner wrapping and closest()",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			$wrapper = $document->createElement("div");
			$wrapper->id = "video-wrapper";
			
			$video->wrap($wrapper);
			
			$inner = $document->createElement("div");
			$inner->addClass("inner-wrapper");
			
			$wrapper->wrapInner($inner);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			return $video->closest("#video-wrapper") != null && $video->parentNode->hasClass("inner-wrapper");
			
		}
		
	],
	
	[
		"caption" => "Node replacement",

		"operation" => function()  {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			$replacement = $document->createElement("div");
			$replacement->text("The video was here!");
			
			$video->replaceWith($replacement);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->querySelector("video");
			
			return $video === null;
			
		}
	],
	
	[
		"caption" => "Fragment handling",
		
		"operation" => function() {
			
			global $fragmentTriggeredError;
			$fragmentTriggeredError = false;
			
			set_error_handler(function($errno, $errstr, $errfile, $errline) {
				
				global $fragmentTriggeredError;
				$fragmentTriggeredError = true;
				
				echo $errstr;
				
			}, E_ALL);
			
			global $fragment;
			$fragment = new DOMDocument();
			$fragment->loadHTML("<div>Test fragment</div>");
			
			restore_error_handler();
			
		},
		
		"assertion" => function() {
			
			global $fragmentTriggeredError;
			global $fragment;
			
			return !$fragmentTriggeredError && $fragment->html == "<div>Test fragment</div>";
			
		}
	]
	
];

// TODO: Getting and setting node values
// TODO: Siblings by selector

echo "<pre>";
foreach($tests as $test)
{
	if(isset($test['operation']))
		$test['operation']();
	
	echo $test['caption'] . " :- ";
	
	if(!$test['assertion']())
		echo "FAILED";
	else
		echo "Passed";
	
	echo "\r\n";
}
echo "</pre>";

echo $document->saveHTML();
