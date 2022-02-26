<?php declare(strict_types=1);

use PerryRylance\DOMDocument;
use PHPUnit\Framework\TestCase;

final class DOMDocumentTest extends TestCase
{
	private function getDocument()
	{
		$document = new DOMDocument();
		$document->load(__DIR__ . "/sample.html");
		return $document;
	}

	public function testQuerySelector()
	{
		$document = $this->getDocument();

		$this->assertTrue($document->find("body")->is("body"));
	}

	public function testSortAndOrder()
	{
		$document = $this->getDocument();

		$before	= $document->find(".before");
		$after	= $document->find(".after");
		
		if($before->first()->isBefore($after->first()[0]))
		{
			$before->text("This element is before");
			$after->text("This element is after");
		}
		else
		{
			$before->text("This element is before, but isBefore returned false");
			$after->text("This element is after, but the test failed");
		}

		$this->assertTrue(
			$document->find(".before")->text() == "This element is before"
			&&
			$document->find(".after")->text() == "This element is after"
		);
	}

	public function testChildrenFiltering()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
		$elements = $video->children("[type='video/ogg']");
		
		$this->assertTrue( count($elements) === 1 );
	}

	public function testBeforeAfterPrevNext()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
			
		$before = $document->create("<div></div>");
		$before->text("This is before the video");
		$before->addClass("inserted-before");
		
		$after = $document->create("<div></div>");
		$after->text("This is after the video");
		$after->addClass("inserted-after");
		
		$video->before($before);
		$video->after($after);

		$video = $document->find("video");
		
		$this->assertTrue(
			$video->prev(".inserted-before")->is(".inserted-before")
			&&
			$video->following(".inserted-after")->is(".inserted-after")
		);
	}

	public function testClassManipulation()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
		
		$video->addClass("dynamically-added-class");
		$video->addClass("class-to-remove");
		$video->removeClass("class-to-remove");

		$this->assertTrue($video->hasClass("dynamically-added-class") && !$video->hasClass("class-to-remove"));
	}

	public function testInlineCss()
	{
		$document = $this->getDocument();
		
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

		$this->assertTrue($cssTest->text() == "3px solid red" && empty( $document->find("#css-test")->css("filter")));
	}

	public function testTextClear()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
		$video->text("");

		$this->assertTrue( $video->text() == "" );
	}

	public function testHtmlStringManipulation()
	{
		$document = $this->getDocument();
			
		$div = $document->create("<div></div>");
		$div->setAttribute("id", "html-test");
		$document->find("body")->append($div);
		
		$div->html("<div class='html-test-success'>This element will be used to test <span style='background-color: green;'>HTML parsing!</span></div>");

		$this->assertTrue( count($document->find(".html-test-success")) === 1 );
	}

	public function testHtmlClear()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
		$video->html("");

		$this->assertTrue( $video->html() == "" );
	}

	public function testAttributeHandling()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
			
		$video->attr("controls", "true");
		$video->attr([
			"muted"		=> true,
			"autoplay"	=> true
		]);

		$this->assertTrue(
			$video->attr("controls") == "true"
			&& $video->attr("muted")
			&& $video->attr("autoplay")
		);
	}

	public function testDataAttributeInterface()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
		
		$video->data("test", "123");
		$video->data([
			"one" => "1",
			"two" => "2",
			"three" => 3
		]);

		define("DEBUG", true);

		$this->assertEquals("123", $video->data("test"));
		$this->assertEquals("1", $video->data("one"));
		$this->assertEquals("2", $video->data("two"));
		$this->assertEquals("3", $video->data("three"));
	}

	public function testNodeRemoval()
	{
		$document = $this->getDocument();

		$document->find(".node-to-be-removed")->remove();

		$this->assertTrue( count( $document->find(".node-to-be-removed") ) === 0 );
	}

	public function testNodeClearance()
	{
		$document = $this->getDocument();

		$div = $document->create("<div></div>");
			
		$div->id = "clearance-test";
		$div->html("Some test text <span>and a child element</span>");

		$document->find("body")->append($div);
		
		$div->clear();

		$this->assertTrue( $document->find("#clearance-test")->text() == "" );
	}

	public function testWrappingAndClosest()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
			
		$wrapper = $document->create("<div></div>");
		$wrapper->attr("id", "video-wrapper");
		
		$video->wrap($wrapper);
		
		$inner = $document->create("<div></div>");
		$inner->addClass("inner-wrapper");
		
		$wrapper->wrapInner($inner);

		$this->assertTrue( $video->closest("#video-wrapper")->is("#video-wrapper") && $video->parent()->hasClass("inner-wrapper") );
	}

	public function testNodeReplacement()
	{
		$document = $this->getDocument();

		$video = $document->find("video");
			
		$replacement = $document->create("<div></div>");
		$replacement->text("The video was here!");
		
		$video->replaceWith($replacement);

		$video = $document->find("video");

		$this->assertTrue( count($video) === 0 );
	}

	public function testFragmentHandling()
	{
		$fragmentTriggeredError = false;
		
		set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$fragmentTriggeredError) {
			$fragmentTriggeredError = true;
			echo $errstr;
		}, E_ALL);

		$fragment = new DOMDocument();
		$fragment->loadHTML("<div>Test fragment</div>");

		restore_error_handler();

		$this->assertTrue(!$fragmentTriggeredError && $fragment->html == "<div>Test fragment</div>");
	}

	public function testListPopulation()
	{
		$document = $this->getDocument();

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

		$this->assertTrue( count( $container->children() ) == 20 );
	}

	public function testLinkConcatenation()
	{
		$document = $this->getDocument();

		$a		= $document->find("a#blog");
		$href	= $a->attr("href");

		$a->attr("href", "$href/development/journal/domdocument-2-0-0-release");

		$this->assertTrue( $a->attr("href") == "https://perryrylance.com/development/journal/domdocument-2-0-0-release" );
	}

	public function testCheckboxValueHandling()
	{
		$document = $this->getDocument();

		$input = $document->find("input[type='checkbox'][value='4']");
		$input->val("checkbox passed");

		$this->assertEquals( "checkbox passed", $input->val() );
	}

	public function testRadioValueHandling()
	{
		$document = $this->getDocument();

		$input = $document->find("input[type='radio'][value='b']");
		$input->val("radio passed");

		$this->assertTrue( $input->val() == "radio passed" );
	}

	public function testCheckboxPropHandling()
	{
		$document = $this->getDocument();

		$checkbox = $document->find("input[type='checkbox']:checked")->first();

		$checkbox->prop("checked", false);

		$this->assertTrue( $checkbox->prop("checked") === false );
	}

	public function testRadioPropHandling()
	{
		$document = $this->getDocument();

		$radio = $document->find("input[type='radio'][value='a']");
		
		$radio->prop("checked", true);

		$this->assertTrue( $radio->is(":checked") );
	}

	public function testHtmlParsing()
	{
		$document	= $this->getDocument();

		$html		= "<span>I should be a span</span>";
		$el			= $document->find("#html-method-test");
		
		$el->html($html);

		$this->assertEquals( $html, $el->html() );
	}

	public function testShorthandMethod()
	{
		$document	= $this->getDocument();

		$_ = $document->shorthand();

		$document->find("#shorthand-test li")->each(function($el) use ($_) {

			$_($el)->text("All good");

		});

		$failed = false;

		$document->find("#shorthand-test li")->each(function($el) use ($_, &$failed) {

			if($failed)
				return false;

			if($_($el)->text() != "All good")
				$failed = true;

		});

		$this->assertTrue( !$failed );
	}
}
