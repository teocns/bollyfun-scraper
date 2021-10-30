<?php

class DomParserCallback {

	public $dom;
	public $elements;

	function __construct($dom, $selector) {
		$this->dom = $dom;
		$this->elements = $this->dom->execute($selector);
	}

	function __method($name, $arguments) {
		$elements = $this->dom->execute($selector);
		$values = [];
		foreach($this->elements as $element){
			$values[] = $element->{$name}(...$arguments);
		}
		return $values;
	}

	function __get($name) {
		$values = [];
		foreach($this->elements as $element){
			$values[] = $element->{$name};
		}
		return $values;
	}

}