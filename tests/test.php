<?php

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../src/DOMDocument.php');

use \PerryRylance\DOMDocument as DOMDocument;

$document = new DOMDocument();
$document->load(__DIR__ . "/sample.html");

$tests = [
	[
		'caption' => 'Selection and query matching',
		
		'assertion' => function() {
			
			global $document;
			return $document->find("body")->is("body");
			
		}
	],

	[
		'caption' => 'DOM sorting and ordering',
	
		'operation' => function() {
			
			global $document;
			
			$before	= $document->find(".before");
			$after	= $document->find(".after");
			
			if($before->first()->isBefore($after->first()))
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
				$document->find(".before")->text() == "This element is before"
				&&
				$document->find(".after")->text() == "This element is after"
			);
			
		}
	],
	
	[
		"caption" => "Filtering with children()",
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->find("video");
			$elements = $video->children("[type='video/ogg']");
			
			return count($elements) == 1;
			
		}
	],
	
	[
		"caption" => "Inserting with before() and after(), selecting with prev() and next()",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
			$before = $document->create("<div></div>");
			$before->text("This is before the video");
			$before->addClass("inserted-before");
			
			$after = $document->create("<div></div>");
			$after->text("This is after the video");
			$after->addClass("inserted-after");
			
			$video->before($before);
			$video->after($after);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
			return (
				$video->prev(".inserted-before")->is(".inserted-before")
				&&
				$video->following(".inserted-after")->is(".inserted-after")
			);
		}
	],
	
	[
		"caption" => "Class manipulation and checking",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
			$video->addClass("dynamically-added-class");
			$video->addClass("class-to-remove");
			$video->removeClass("class-to-remove");
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
			return ($video->hasClass("dynamically-added-class") && !$video->hasClass("class-to-remove"));
			
		}
		
	],
	
	[
		"caption" => "Inline CSS manipulation",
		
		"operation" => function() {
			
			global $document;
			
			$cssTest = $document->create("<div></div>");
			$cssTest->setAttribute("id", "css-test");
			
			$video = $document->find("video");
			$video->parent()->append($cssTest);
			
			$video->css([
				"border" => "3px solid red",
				"margin" => "1em",
				"filter" => "invert(100%)"
			]);

			// NB: Seems to fail text calls on sets created with DOMDocument::create
			$cssTest->text($video->css("border"));

			$video->css("filter", "");

			$cssTest->addClass("pre");
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			return $document->find("#css-test")->text() == "3px solid red" && empty( $document->find("#css-test")->css("filter") );
			
		}
	],
	
	[
	
		"caption" => "HTML string manipulation",
		
		"operation" => function() {
			
			global $document;
			
			$div = $document->create("<div></div>");
			$div->setAttribute("id", "html-test");
			$document->find("body")->append($div);
			
			$div->html("<div class='html-test-success'>This element will be used to test <span style='background-color: green;'>HTML parsing!</span></div>");
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			return $document->find(".html-test-success") != null;
			
		}
		
	],
	
	[
		"caption" => "Attribute handling",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
			$video->attr("controls", "true");
			$video->attr([
				"muted"		=> true,
				"autoplay"	=> true
			]);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
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
			
			$video = $document->find("video");
			
			$video->data("test", "123");
			$video->data([
				"one" => "1",
				"two" => "2",
				"three" => 3
			]);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
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
			
			return count( $document->find(".node-to-be-removed") ) == 0;
			
		}
	
	],
	
	[
		
		"caption" => "Node clearance",
		
		"operation" => function() {
			
			global $document;
			
			$div = $document->create("<div></div>");
			
			$div->id = "clearance-test";
			$div->html("Some test text <span>and a child element</span>");

			$document->find("body")->append($div);
			
			$div->clear();
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			return $document->find("#clearance-test")->text() == "";
			
		}
	
	],
	
	[
		
		"caption" => "Node wrapping, inner wrapping and closest()",
		
		"operation" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
			$wrapper = $document->create("<div></div>");
			$wrapper->attr("id", "video-wrapper");
			
			$video->wrap($wrapper);
			
			$inner = $document->create("<div></div>");
			$inner->addClass("inner-wrapper");
			
			$wrapper->wrapInner($inner);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->find("video");

			return $video->closest("#video-wrapper")->is("#video-wrapper") && $video->parent()->hasClass("inner-wrapper");
			
		}
		
	],
	
	[
		"caption" => "Node replacement",

		"operation" => function()  {
			
			global $document;
			
			$video = $document->find("video");
			
			$replacement = $document->create("<div></div>");
			$replacement->text("The video was here!");
			
			$video->replaceWith($replacement);
			
		},
		
		"assertion" => function() {
			
			global $document;
			
			$video = $document->find("video");
			
			return count($video) == 0;
			
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
	],

	[
		"caption" => "List population",

		"operation" => function() {

			global $document;

			$template = $document->find("li.template");
			$container = $template->parent();
			$template->remove();

			$data = [];

			for($i = 1; $i <= 20; $i++)
				$data []= $i;
			
			foreach($data as $i)
			{
				$li = $template->duplicate();
				$li->text($i);

				$container->append($li);
			}

		},

		"assertion" => function() {

			global $document;

			return count( $document->find("ul")->children() ) == 20;

		}
	],

	[
		"caption" => "Link concatenation",

		"operation" => function() {

			global $document;

			$a		= $document->find("a#blog");
			$href	= $a->attr("href");
			$a->attr("href", "$href/development/journal/domdocument-2-0-0-release");

		},

		"assertion" => function() {

			global $document;

			return $document->find("a#blog")->attr("href") == "https://perryrylance.com/development/journal/domdocument-2-0-0-release";

		}
	],

	[
		"caption" => "Checkbox and radio value handling",

		"operation" => function() {

			global $document;

			$input = $document->find("input[type='checkbox'][value='4']");
			$input->val("checkbox passed");

			$input = $document->find("input[type='radio'][value='b']");
			$input->val("radio passed");

		},

		"assertion" => function() {

			global $document;

			return $document->find("input[type='checkbox'][value='checkbox passed']")->val() == "checkbox passed"
				&&
				$document->find("input[type='radio'][value='radio passed']")->val() == "radio passed";
		}
	],

	[
		"caption" => "Checkbox prop handling",

		"operation" => function() {

			global $document;

			$after = $document->create("<div>These should be unchecked</div>");

			$document->find("input[type='checkbox']:checked")->prop("checked", false)->after($after);

		},

		"assertion" => function() {

			global $document;

			return count($document->find("input[type='checkbox']:checked")) == 0;

		}
	],

	[
		"caption" => "Radio prop handling",

		"operation" => function() {

			global $document;

			$document->find("input[type='radio'][value='a']")->prop("checked", true);

		},

		"assertion" => function() {

			global $document;

			return $document->find("input[type='radio'][value='a']")->is(":checked");

		}
	],

	[
		"caption" => "html() method",

		"operation" => function() {

			global $document;

			$document->find("#html-method-test")->html("<span>I should be a span</span>");

		},

		"assertion" => function() {

			global $document;

			return $document->find("#html-method-test")->html() == "<span>I should be a span</span>";

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
